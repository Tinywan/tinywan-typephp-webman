# AOT 编译速度优化研究笔记

本文档用于记录 Swoole-Compiler AOT 编译速度的现状判断、瓶颈分析与后续研究方向，暂不直接绑定某个具体 PR。

## 目标

降低以下两类耗时：

1. **冷启动全量构建**：`./bin/tpc.php project.yml`
2. **热启动增量构建**：只改少量 PHP 文件后再次编译

重点关注大型项目和编译器自举场景。

## 当前流水线

主流程位于 `src/Php/Translator.php`：

1. `prepare()`：扫描文件、解析 AST、收集符号、排序依赖
2. `convert()`：生成每个 PHP 文件对应的 `.cc`
3. `genStubFile()`：生成 arginfo / class register 头文件
4. `genFunctionDeclarations()` / `genDataDeclarations()`：生成构建期内部声明头
5. `genExtension()`：生成单个 `extension-<target>.cc`
6. `compile()`：把所有 `.cc/.c/...` 编译为 `.o`
7. `build()`：链接为最终可执行文件或扩展

## 主要瓶颈判断

### 1. 公共头文件抖动导致全量重编

当前所有翻译单元都会包含：

- `php_<target>_func_decl.h`
- `php_<target>_data_decl.h`

只要任何函数声明、默认参数 helper、全局符号声明发生变化，就会导致大量 `.cc` 重新编译。

这是大型项目增量构建性能差的关键原因之一。

### 2. `extension-<target>.cc` 单文件过大

扩展主文件承载了：

- class entry 注册
- 函数表
- literal strings
- 模块初始化
- 静态属性初始化
- 常量初始化

项目越大，这个单 TU 越大，容易成为编译尾部瓶颈，即使其它文件可以并行编译，也会卡在这一个大文件上。

### 3. 缺少通用增量缓存

目前只有 `phpx/src/misc` 的 object cache：

- `hasMiscObjectFileCache()`

用户项目自身生成的 `.cc`、公共头、arginfo 头、extension 文件基本仍是全量重建、全量重编。

### 4. clang-format 开销固定且串行

`formatCppCode()` 会对每个生成文件执行一次：

```bash
clang-format -i <file>
```

这会引入：

- 额外进程启动开销
- 大量磁盘 I/O
- 串行格式化等待

对大项目尤其明显。

### 5. arginfo / stub 每次重生成

`generateStubFile()` 当前每次都会跑，即使输入 PHP 文件未变化，也会重新生成头文件，进一步放大头文件抖动问题。

### 6. 仅编译阶段并行，前置阶段大多串行

当前 `compileWithPcntl()` 只并行 `.cc -> .o`：

- prepare
- convert
- stub generation
- format

这些阶段仍然大多是串行的。

## 优先级最高的优化方向

## P0：内容未变化时不重写文件

这是最值得优先做的基础改造。

### 思路

对所有生成文件：

- `.cc`
- arginfo `.h`
- `php_<target>_func_decl.h`
- `php_<target>_data_decl.h`
- `extension-<target>.cc`

在写盘前先比较内容：

- 内容相同：**不写文件**
- 内容不同：再写入

### 价值

避免仅因为 mtime 变化触发下游全量重编。

---

## P0：通用 object cache / 增量编译

把当前只针对 `phpx/src/misc` 的缓存思路扩展到用户生成代码。

### 建议缓存判断条件

对每个目标 `.o`：

1. `.o` 存在
2. `.o` 新于对应源文件
3. `.o` 新于它依赖的头文件
4. 编译选项签名未变化（优化级别、debug、sanitize、cxxflags、PHP/ZTS 等）

满足时直接跳过编译。

### 配套要求

需要一个明确的“构建签名”机制，例如：

- compiler backend
- cpp compiler path
- C++ 标准
- optimize/debug/sanitize
- build mode
- PHP/ZTS 信息

---

## P0：默认关闭 clang-format

建议让格式化成为显式能力，而不是默认编译路径的一部分。

### 建议

- 默认关闭
- 增加 `--format` 或 debug/dev 模式开启
- 或仅格式化 changed file

### 价值

这是低风险、立刻见效的优化。

---

## P1：拆公共声明头

### 当前问题

复杂默认参数 helper 也进入了公共 `func_decl.h`，会扩大头文件变化的影响范围。

### 可选方向

1. **按源文件拆分声明头**
2. **把 helper 从公共头拆到局部头/局部 `.cc`**
3. **只有 truly cross-TU 的声明才放公共头**

### 目标

减少“一处改动，全项目重编”。

---

## P1：拆分 `extension-<target>.cc`

### 可拆分模块

1. `extension-main.cc`
2. `extension-class-register-*.cc`
3. `extension-function-table.cc`
4. `extension-const-init.cc`
5. `extension-static-init.cc`

### 价值

- 降低单 TU 体积
- 增强并行编译收益
- 降低大项目尾部等待

---

## P1：arginfo / stub 缓存

### 方向

对 `generateStubFile()` 引入基于输入内容的缓存：

- 源 PHP 内容 hash
- gen_stub 版本签名
- PHP 版本签名

内容不变时不覆盖输出头文件。

### 价值

减少头文件抖动，配合增量构建效果明显。

---

## P2：prepare / convert / stub generation 并行化

当前主要只并行了 compile 阶段。后续可以探索：

1. 文件扫描后按依赖拓扑分层
2. 同层文件并行 convert
3. 同层文件并行 stub generation

### 风险点

- 共享状态较多（literalStrings、classMap、funcMap、propMap、符号表等）
- 需要先梳理哪些状态可以分片，哪些必须归并

因此这个方向收益大，但实现复杂度也更高。

---

## P2：符号依赖驱动的最小重编译

理想增量构建不应只基于文件时间戳，而应基于：

- 哪些符号发生变化
- 哪些文件依赖这些符号

### 目标

修改一个 PHP 文件时，仅重建：

1. 该文件自身
2. 依赖其导出符号的文件
3. 必要的 extension / declaration 模块

这会显著提升大型项目热构建速度。

---

## P2：工具链层优化

### 编译缓存

- `ccache`
- `sccache`

### 更快链接器

- `mold`
- `lld`

### 预编译头

对稳定的大头文件尝试 PCH，例如：

- `phpx.h`
- `phpx_helper.h`
- `phpx_std.h`

这类优化实现成本较低，可以和编译器选项层一起推进。

## 关于字面量数组的特殊约束

字面量数组与字面量字符串不同：

- **字面量字符串** 可以利用永久字符串，绕开 Zend request 生命周期
- **字面量数组** 必须存在于 `module_init()` 到 `module_clean()`，即 PHP 的 `RINIT/RSHUTDOWN` 之间

因此后续所有“数组初始化缓存”研究都必须遵守：

1. **不能把 PHP 数组持久化成进程级永久对象**
2. 只能缓存“初始化计划”或“生成代码模板”
3. 真正数组对象必须在 request 生命周期内构造

当前已经引入的 `ArrayInitPlan` 就属于这类安全抽象：

- 只保存 `expr/init/clean`
- 不保存跨 request 的数组对象实例

## 建议的落地顺序

### 第一阶段（最快见效）

1. 内容未变化不写文件
2. 通用 `.o` 缓存
3. 默认关闭 clang-format
4. arginfo/stub 内容缓存

### 第二阶段（结构性收益）

5. 拆公共头
6. 拆 `extension-<target>.cc`
7. 缩小默认参数 helper 的可见范围

### 第三阶段（长期优化）

8. prepare/convert 并行
9. 符号依赖驱动的最小重编译
10. PCH / ccache / mold / sccache

## 建议优先研究的代码位置

- `src/Php/Translator.php`
  - `formatCppCode()`
  - `compile()`
  - `compileSourceFile()`
  - `compileWithPcntl()`
  - `genFunctionDeclarations()`
  - `genDataDeclarations()`
  - `genExtension()`
  - `genStubFile()`
- `src/Php/Backend/*`
  - 编译/链接命令构造，便于接入 `ccache` / `mold` / `lld`

## 一个现实判断

对大型项目来说，AOT 编译慢通常不是单纯 “g++ 慢”，而是以下几项叠加：

1. 全量重生成
2. 头文件抖动导致全量重编
3. 单一超大 extension TU
4. 每文件格式化
5. 缺少真正的增量缓存

因此最有效的方向也不是先调编译 flags，而是优先做：

- **增量**
- **拆分**
- **减少公共依赖面**

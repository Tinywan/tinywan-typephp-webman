# KPHP 编译器 Review：设计、优化、工具链与语法兼容性分析

> 2026-06-11 · 基于 /home/swoole/workspace/cpp/kphp 源码审查

---

## 一、架构概览

| 维度 | KPHP | AOT Compiler |
|------|------|-------------|
| 编译目标 | PHP → C++ → 二进制 | PHP → C++ → 二进制 |
| IR 形式 | 自定义 vertex (op_*) 树 | PHP-Parser AST → C++ 字符串 |
| 类型推理 | 迭代收敛型图推断（收敛于泛化方向） | SSA + 手动类型注释 |
| 中间优化 | 多 pass AST 重写（~60+ pipe） | 直接 AST → C++ 翻译 + 少量优化 |
| 运行时 | 自研（allocator/string/array/mixed） | phpx（C++ RAII 包装 Zend API） |
| 并发模型 | 单线程 + reactor/epoll | 依赖 Zend/TSRM |
| 线程安全 | 无（内存分配器无锁） | Zend TSRM |
| 代码量（编译器） | ~18k 行 pipe 代码 | ~6k 行 CompilerBase |

---

## 二、可借鉴的创新设计

### 2.1 Rewrite Rules DSL（模式匹配优化规则）

**文件**: `compiler/rewrite-rules/early_opt.rules`

声明式 DSL 描述 AST 重写，编译期生成 C++ 优化代码。规则格式为 `(pattern) => (replacement)`，支持条件子句和 C++ 表达式嵌入：

```lisp
;; strlen 常量折叠
(op_func_call {"strlen"} arg:(op_string))
  => (op_int_const { std::to_string(arg->str_val.size()) })

;; explode 索引直接访问 → 特化版本
(op_index (op_func_call {"explode"} delim s) k:(op_int_const))
  => (op_func_call {"_explode_nth"} delim s k)

;; ("" . $x) → (string)$x — 消除无意义拼接
(op_concat (op_string {""}) x) => (op_conv_string x)

;; 子串类型优化：conv(substr(...)) → conv(_tmp_substr(...))
(op_conv_int x) if let x2 { to_tmp_string_expr(x) } => (op_conv_int x2)
```

**AOT 借鉴优先级: P0**

当前 `FuncCallOptimizer` 的 `strlen`/`count` 等优化是硬编码的。引入类似规则引擎可以：
- 声明式添加优化，降低维护成本
- 通过 `if let` 条件做局部模式变量绑定
- 规则文件与编译器分离，热加载可行

实现建议：PHP 层面实现一套 `RewriteRule` 类，在 `FuncCallOptimizer` 中加载并匹配。

---

### 2.2 Smart instanceof / Smart Casts（类型收窄）

**文件**: `compiler/pipes/transform-to-smart-instanceof.cpp`

`if ($x instanceof A)` 之后，`$x` 在 if 体内自动重命名为 `instance_cast<A>($x)`。核心创新在于**在类型推断之前**做变量拆分：

```php
// PHP 源码
if ($x instanceof A) {
    $x->methodOfA();  // $x 自动变为 instance_cast<A>($x)
}

// 反向守卫模式
if (!($x instanceof A)) return;
// 此后 $x 全函数范围替换为 instance_cast<A>($x)
```

同时处理 `catch (SomeClass $e)` 中同名变量在不同 catch 块间的重命名，防止 assumption 混淆。

**AOT 借鉴优先级: P0**

当前 `SsaTypeOptimizer` 只做 int/float/string 基本类型收窄。可以增加对象类型收窄：
1. 在 SSA builder 中识别 `instanceof` 守卫
2. 在 then/else 分支中替换为目标子类类型
3. 结合现有的 `stableObjects` 机制进行 devirtualization

---

### 2.3 流水线并行编译

**文件**: `compiler/compiler.cpp`

编译过程以函数为粒度，通过 `operator>>` 链接管道，多线程并行处理：

```cpp
SchedulerConstructor{scheduler}
    >> PipeC<LoadFileF>{}
    >> PipeC<FileToTokensF>{}
    >> PipeC<ParseF>{}
    >> PassC<GenTreePostprocessPass>{}
    /* ... 60+ pipes */;
```

三种管道类型：
- **PipeC\<T\>**: 通用转换，输入→输出
- **PassC\<T\>**: 函数级变换，遍历所有 AST 顶点
- **SyncC\<T\>**: 同步点，所有输入处理完毕才输出

不同函数可在不同阶段同时处理，全局存储使用线程安全或无锁结构。

**AOT 借鉴优先级: P3**

当前 `Preprocessor` → `CompilerBase` 是串行执行。对于大型项目可引入函数级并行：
- 类/函数粒度独立编译
- `SyncC` 同步点用于合并全局符号表

---

### 2.4 Switch 拆分（状态机变换）

**文件**: `compiler/pipes/split-switch.cpp`

将 switch 的每个 case 分支提取为独立函数，用状态变量驱动：

```cpp
// 每个 case 变成：
int case_state = 0;
auto case_res = switch_func_N(&case_state);
if (case_state == 1) return case_res;     // 正常返回
if (case_state == -1) break;              // break 语义
```

`break N` 和 `continue N` 被转换为状态变量 `-1` 设置 + return，与之前 AOT 实现的 `_brk_flag` / `_cnt_flag` 方案思路一致。

**AOT 借鉴优先级: P2**

大型 switch 可以拆分为独立函数，降低单函数复杂度，使 GCC 内联/优化更有空间。

---

### 2.5 常量不可变标记与 init-once

**文件**: `compiler/pipes/collect-const-vars.cpp`、`runtime-common/core/memory-resource/`

编译期常量数组/字符串使用特殊的 refcount 标记：

```cpp
ExtraRefCnt::for_global_const   // 不可变，修改时触发 COW
ExtraRefCnt::for_instance_cache // 跨请求共享，不可修改
```

这些常量存放在 data section，服务器启动时初始化一次，后续请求只读使用。任何修改操作自动触发 COW。

**AOT 借鉴优先级: P1**

当前 AOT 已将常量数组提升为 static 变量，但可以引入更细粒度的不可变标记机制，减少不必要的 COW 拷贝（当编译期可证明变量从未被修改时）。

---

### 2.6 函数特化（多版本生成）

**文件**: `compiler/pipes/early-optimization.cpp`

在类型推断之前根据参数进行函数特化：

- `microtime()` → `_microtime_float()` 或 `_microtime_string()`（根据参数 true/false）
- `list() + explode()` → `_explode_tupleN()`（精确 N 元组类型）
- `explode()[N]` → `_explode_nth()`（O(1) 直接访问第 N 个元素）
- `substr()` 在函数参数位置 → `_tmp_substr()`（避免字符串拷贝）

关键是**特殊化版本返回更精确的类型**。例如 `microtime()` 返回 `mixed`，而 `_microtime_float()` 返回 `float`。

**AOT 借鉴优先级: P1**

`FuncCallOptimizer` 目前只做常量折叠，可以扩展为**多版本特化**：

```php
// 当前
$result = strlen($s);  // 返回 mixed/int

// 优化后
$result = _strlen_string($s);  // 编译期确定返回 int
```

---

### 2.7 Class Assumptions：先验类型预测

**文件**: `compiler/class-assumptions.cpp`

解决**类型推断和调用图构建的循环依赖**：

```
$obj->method()  需要 $obj 的类型才能绑定 method()
               但类型推断需要完整调用图
               → Assumption 打破循环
```

Assumption 来源：
- `@param ClassName $x` — 参数类型
- `@return ClassName` — 返回类型
- `@var ClassName` — 局部变量
- 构造函数调用 `new ClassName()` → 直接得到类型

Assumption 在类型推断**之前**进行，用于绑定调用图。类型推断**之后**进行校验——若不匹配则报错。

**AOT 借鉴优先级: P2**

对于 method call devirtualization：assumption 比纯 SSA 分析更早可用，可作为 devirtualization 的第一阶段（在 SSA 不可用时回退）。

---

### 2.8 虚拟方法自动生成

**文件**: `compiler/pipes/generate-virtual-methods.cpp`

当方法被子类覆盖时，基类方法自动成为分发器：

```cpp
ReturnType f$Base$$method(instance_var, args...) {
    if (instance_var.ce() == Child1::ce)
        return f$Child1$$method(instance_cast<Child1>(instance_var), args...);
    if (instance_var.ce() == Child2::ce)
        return f$Child2$$method(instance_cast<Child2>(instance_var), args...);
    // ... fallback to self
    return f$Base$$method$$Base(instance_var, args...);
}
```

同时做 PHP 7.4+ 类型变体检查（参数逆变、返回协变）。

**AOT 借鉴优先级: P2**

当前 devirtualization plan 中的 "runtime exact-type guard" 与此思路一致。可借鉴其自动生成全部分发分支 + variance 检查。

---

### 2.9 性能检查注解（Performance Inspections）

**文件**: `docs/kphp-language/best-practices/performance-inspections.md`

编译期性能分析，通过注解激活：

```php
/** @kphp-warn-performance implicit-array-cast */
function businessLogic() { ... }
```

支持的检查项：
- `implicit-array-cast` — 检测 `array<int>` → `array<mixed>` 隐式转换（昂贵拷贝）
- `array-merge-into` — 检测可通过 `array_merge_into` 优化的合并
- `array-reserve` — 检测可预分配大小的数组
- `constant-execution-in-loop` — 检测循环中的常量表达式

注解通过调用链传播到所有可达函数。

**AOT 借鉴优先级: P3**

与函数着色类似，可作为一种编译期静态分析插件。`implicit-array-cast` 对类型化数组的性能影响极大，值得单独检测。

---

### 2.10 池式内存分配器

**文件**: `runtime-common/core/memory-resource/unsynchronized_pool_resource.h`

- 预分配固定大小 buffer
- 小块 (<16KB): slab 分配，按大小分级（`free_chunks_[chunk_id]`），O(1) 分配/释放
- 大块 (≥16KB): 红黑树管理（`huge_pieces_`），支持碎片整理
- **每个请求结束后硬重置**（`hard_reset()`），无需逐个释放
- 支持 OOM handling memory 预留

**AOT 借鉴优先级: P4**

当前依赖 Zend MM。对于长时间运行的 CLI 模式，pool allocator 可显著降低碎片和分配开销。但需要替换整个内存管理层，工程量大。

---

## 三、工具链分析

### 3.1 测试基础设施

KPHP 有三层测试体系：

| 层级 | 目录 | 用途 |
|------|------|------|
| PHPT 测试 | `tests/phpt/` (75+ 子目录) | PHP 行为兼容性测试 |
| C++ 单元测试 | `tests/cpp/compiler/` `tests/cpp/runtime/` `tests/cpp/server/` | 编译器/运行时/服务器组件测试 |
| Python 集成测试 | `tests/python/tests/` | HTTP/RPC/多进程集成测试 |

测试运行器 `tests/kphp_tester.py` 支持：
- 标签机制（`@ok`, `@kphp_should_fail`, `@kphp_should_warn` 等）
- PHP 版本选择（`@php7.4`, `@php8`）
- 多进程并行执行（基于 ThreadPool）
- TCP server 管理
- k2 模式（组件编译）兼容
- 增量编译支持（nocc 分布式编译）

**AOT 借鉴**:
- 当前 AOT 只有 `phpunit/` (PHPUnit) 和 `tests/compiler/` (PHPT) 两层，缺少编译器内部单元测试和集成测试
- 标签机制比纯 PHPT 更灵活——可以标记预期编译失败、预期警告等
- Python 测试 runner 提供了更好的 CI 集成能力

### 3.2 基准测试框架

**文件**: `tests/benchmarks/`

使用 Go 编写的 `ktest` 工具做 KPHP vs PHP 性能对比：

```
$ KPHP_ROOT=/path/to/repo/kphp ./ktest bench-vs-php tests/benchmarks/
```

基准测试覆盖：
- `BenchmarkBasic.php` — 基础操作
- `BenchmarkConcat.php` — 字符串拼接
- `BenchmarkExplode.php` — explode 性能
- `BenchmarkMultiSwitch.php` — 大型 switch
- `BenchmarkTmpString.php` — 临时字符串优化效果
- `BenchmarkJson.php` / `BenchmarkFFI.php` — 特定功能

**AOT 借鉴**:
- 可以建立类似的 AOT vs PHP 基准对比套件
- 特别关注 AOT 编译器声称优化的场景（如 typed property access、devirtualized calls）

### 3.3 IDE 集成

KPHP 提供 **kphpstorm** IDE 插件（`docs/kphp-language/kphpstorm-ide-plugin/`），支持：
- `@kphp-*` 注解语法高亮
- 类型标注补全
- KPHP 特有类型的提示

**AOT 借鉴**:
- 当前 AOT 编译器使用 `@phpstan-*` 等注解，可考虑提供 VSCode/JetBrains 插件

### 3.4 增量编译

KPHP 仅重新编译变更的文件（基于 CRC64 哈希）：

```cpp
// 每个生成文件开头
//crc64      <content_hash>
//crc64_with_comments <hash_with_comments>
```

比较这些哈希与上次生成结果，确定需要重编译的文件，包括所有依赖此文件的上游文件。

**AOT 借鉴**:
- 当前 `build/` 目录全量重新生成，可引入类似的增量机制加速大型项目迭代

---

## 四、语法兼容性分析

### 4.1 KPHP 支持的 PHP 版本

KPHP 瞄准 **PHP 7.4** 语言级别，部分 8.0/8.1 特性正在添加。

### 4.2 不支持的特性（架构原因）

| 特性 | 原因 |
|------|------|
| 动态函数/方法调用 (`call_user_func`) | 编译期无法解析符号 |
| `eval()` | 编译期不可知 |
| 动态类/函数声明 | 符号表必须在编译期完整 |
| Reflection | 需要运行时元数据 |
| Mock (PHPUnit) | 依赖 Reflection + 动态重定义 |
| 数组内部指针 (`reset`/`current`/`next`) | 不符合引用语义 |
| PHP 扩展互操作 | 自研运行时替代 |

### 4.3 不支持的特性（未实现）

| 特性 | 状态 |
|------|------|
| 嵌套 `list()` | 未实现 |
| 生成器 (`yield`) | 未实现 |
| 匿名类 | 未实现 |
| Group use declarations | 未实现 |
| finally | 未实现 |
| `func_get_args` | 未实现 |
| 引用（除 foreach by ref 和引用参数外） | 部分支持 |
| 接口在父链中出现多次 | 不支持 |
| `insteadof` / traits 重命名 | 不支持 |

### 4.4 KPHP 特有注解

```php
// 函数注解
@kphp-inline                  // 强制内联（GCC inline）
@kphp-flatten                 // 激进内联所有 callee
@kphp-required                // 强制编译（用于字符串回调）
@kphp-sync                    // 禁止成为 resumable
@kphp-no-return               // 永不返回（优化 CFG）
@kphp-pure-function           // 纯函数（常量数组可调用）
@kphp-warn-unused-result      // 未使用返回值时报错
@kphp-should-not-throw        // 禁止抛异常
@kphp-throws {Class}          // 受检异常
@kphp-generic T1, T2          // 泛型函数
@kphp-color {color}           // 能力标注
@kphp-warn-performance {...}  // 性能检查
@kphp-disable-warnings {...}  // 抑制特定警告
@kphp-profile                 // 嵌入 profiler

// 类注解
@kphp-serializable            // 可序列化
@kphp-immutable-class         // 不可变类
@kphp-json {attr}={value}     // JSON 配置
```

### 4.5 与 AOT 编译器的语法差异

| 特性 | KPHP | AOT Compiler |
|------|------|-------------|
| 基础 PHP 版本 | 7.4 | 8.2+ |
| 枚举 | 不支持 | 支持 (PHP 8.1 enum) |
| 命名参数 | 不支持 | 支持 |
| Match 表达式 | 不支持 | 支持 |
| 联合类型 | 部分支持 | 支持 |
| Nullsafe `?->` | 不支持 | 支持 |
| 属性提升 (constructor promotion) | 不支持 | 支持 |
| `list()` 解构 | 部分 | 完整支持 |
| `break N` / `continue N` | 部分支持 | 已支持 |
| 类型化数组 | 自定义语法 `array<T>` | 无（使用 phpstan 标注） |
| 泛型函数 | `@kphp-generic` | 无 |
| 元组 / Shapes | 自定义语法 | 无 |
| FFI | 支持（自定义 FFI） | 无 |

### 4.6 类型系统的关键差异

KPHP 的类型系统比 PHP 严格得多：
- **不允许类型混用**: `f(42); f("string")` 对同一个 `$arg` 是编译错误
- **数组类型化**: `array<int>` vs `array<string>` 是不同的类型，转换需要显式或隐式 cast
- **mixed 代价高昂**: 16 字节 tagged union + switch-case 分发
- **泛型函数**: 通过 `@kphp-generic` 实现编译期特化，类似 C++ template
- **变量分裂**: 同一变量名可能在不同的 CFG 路径上分裂为不同名称（如 `$x` → `$x$v1`）

---

## 五、总结与优先级建议

| 优先级 | 技术 | 难度 | 收益 | 说明 |
|--------|------|------|------|------|
| **P0** | Rewrite Rules DSL | 中 | 高 | 声明式优化规则，可扩展性极强 |
| **P0** | Smart instanceof casts | 低 | 高 | 直接提升对象类型收窄 + devirtualization |
| **P1** | 函数特化（多版本） | 中 | 高 | 更精确的返回类型，消除 mixed 污染 |
| **P1** | 不可变常量标记 | 低 | 中 | 减少 COW，已有常量提升基础 |
| **P2** | Switch 拆分 | 中 | 中 | 已有多级 break 基础，特定场景优化 |
| **P2** | Class Assumptions | 高 | 高 | 需要 PHPDoc 解析基础设施 |
| **P2** | 虚拟方法自动生成 | 中 | 高 | 与 devirtualization plan 互补 |
| **P3** | 性能检查注解 | 低 | 中 | 辅助开发者发现隐藏性能问题 |
| **P3** | 流水线并行 | 高 | 中 | 大型项目编译提速 |
| **P3** | 函数着色 | 低 | 低 | 辅助安全/IO 审计 |
| **P4** | 增量编译 | 中 | 中 | 开发体验优化 |
| **P5** | Resumable 状态机 | 高 | 中 | 需实际 async 需求 |
| **P5** | Pool allocator | 极高 | 高 | 需替换整个内存管理 |

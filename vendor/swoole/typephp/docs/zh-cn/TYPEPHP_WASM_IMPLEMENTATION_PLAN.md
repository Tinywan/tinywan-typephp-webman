# TypePHP WASM 技术方案与实施计划

> 状态：WASI 0.2 Component 与 Chrome Worker 原型已实现
> 调研日期：2026-08-07  
> 当前目标：WASI 0.2（Preview 2），NTS，单线程；不支持 WASI 0.1

## 1. 文档目的

本文记录 TypePHP 支持 WebAssembly 的技术决策、功能边界、运行时架构、主要风险、验证方法和分阶段实施计划。

2026-08-07 的实现验证已经证明：精简 PHP 8.5、PHPX 核心、TypePHP 生成代码、GMP、MPFR 和 mpdecimal 可以通过 WASI SDK 静态链接为单个模块，并在 Wasmtime 中运行。可复现构建方法见 [构建 TypePHP WASI 程序](WASI_BUILD.md)。本文余下内容同时保留浏览器阶段的设计目标。

## 2. 核心结论

首个 TypePHP WASM 版本采用以下路线：

```text
PHP 源码
    -> TypePHP 编译器
    -> TypePHP 生成的 C++
    -> WASI SDK 编译和静态链接
       + PHP NTS
       + PHPX
       + TypePHP runtime
       + GMP / MPFR / mpdecimal
       + PHP embed/WASI 运行时
    -> typephp.wasm（WASI 0.2 command component）
```

具体决策如下：

1. 第一版复用当前 C++/Zend 后端，不直接生成 WAT/WASM，也不重新实现 PHP 运行时。
2. 使用 WASI SDK 的 `wasm32-wasip2` sysroot 直接生成 Component；Chrome 由 Jco 转译为 ESM，不维护第二套 Emscripten ABI。
3. PHP、PHPX、TypePHP 生成代码和高精度库全部静态链接到一个 `.wasm` 模块。
4. Wasmtime 和 Chrome 共同提供 CLI、stdio、exit、clocks、random 和受控文件系统；Chrome host 固定运行在 Worker 中。
5. 仅支持 PHP NTS，不支持线程。
6. 禁用 Fiber 和 Generator。
7. 必须支持 C++ 异常以及 Zend bailout 所需的 `setjmp/longjmp`。
8. 保留 PHP stream 框架和本地 stream，禁用网络 transport 和依赖操作系统进程能力的功能。
9. WordPress Playground 和其他 PHP-WASM 项目只作为补丁与移植经验来源，不作为 TypePHP 的依赖或代码基础。

本文描述的是最短可落地路径。长期的后端中立方案参见 [BACKEND_NEUTRAL_IR.md](BACKEND_NEUTRAL_IR.md)。WASI 原型证明无需为了 WASM 重写 TypePHP 前端和语义层。

## 3. 为什么不采用 WordPress Playground

WordPress Playground 是一个成熟的浏览器 WordPress 产品，但不是小型 PHP-WASM 移植层。其仓库和构建体系同时服务于：

- 多个 PHP 版本和扩展组合；
- WordPress 发行版及其资源；
- 浏览器、Web Worker 和 Node.js 运行时；
- 虚拟文件系统、挂载和持久化；
- 网络代理和浏览器 HTTP 适配；
- NPM 包、网站、开发工具及集成测试；
- WordPress 特有的 API 和产品功能。

TypePHP 无法直接复用 Playground 发布的 PHP-WASM 二进制，因为 TypePHP 需要把 PHPX、编译生成的 C++ 和高精度库一起静态链接。若 fork Playground，TypePHP 还会被其 monorepo、Node/NPM 构建、版本矩阵和产品发布周期绑定。

因此采用以下原则：

- 不 fork WordPress Playground；
- 不把 `@php-wasm/*` 作为 TypePHP 的运行时依赖；
- 不复制其 WordPress、网络代理、文件同步和 UI 层；
- 仅研究 PHP configure 参数、php-src 补丁、Emscripten 兼容处理和最小 C API；
- 所有借用补丁必须拆分、注明来源，并验证是否仍适用于 TypePHP 固定的 PHP/Emscripten 版本。

`seanmorris/php-wasm`、`soyuka/php-wasm` 等项目也遵循相同原则：可用作构建参考和问题索引，但不成为 TypePHP 的基础仓库。

## 4. 目标与非目标

### 4.1 当前 WASI 目标

- 在 Wasmtime 等 WASI runtime 中加载 TypePHP 编译产物。
- 执行静态编译的 TypePHP 应用入口。
- 保持 TypePHP 当前基于 Zend 和 PHPX 的主要语言语义。
- 正确处理 PHP request 生命周期、C++ 异常和 Zend bailout。
- 支持 GMP、MPFR 和 mpdecimal 高精度类型。
- 支持 WASI 文件系统和必要的本地 PHP stream。
- 对不支持的功能给出确定、可测试的错误，而不是链接失败或运行时崩溃。
- 构建过程可复现，php-src、WASI SDK 和数值库版本固定。

### 4.2 首期非目标

- 无宿主适配的浏览器直接运行。
- pthread、Web Worker 并行 PHP 或共享内存。
- Fiber 和 Generator。
- 动态扩展加载。
- PHP 源码的运行时编译或通用 `eval()`。
- TCP、UDP、Unix socket 和监听端口。
- MySQL、PostgreSQL、Redis 等网络客户端。
- `curl`、FTP、SMTP 等网络协议实现。
- `fork`、`exec`、`system`、`shell_exec`、`proc_open` 和信号处理。
- FFI、JIT、opcache 和调试器。
- 完整 WordPress 兼容性。
- 在第一阶段实现异步宿主调用。

## 5. 目标平台选择

### 5.1 当前使用 WASI SDK

当前先建立命令行可验证基线。WASI SDK 已经验证可以同时提供：

- C/C++ 到 WebAssembly 的完整工具链；
- 标准 Wasm C++ exception handling；
- Zend bailout 所需的 SJLJ；
- capability-based 文件系统；
- libc、时间和随机数接口。

PHP、PHPX 和所有 TypePHP C++ 翻译单元必须使用一致的 Wasm EH/SJLJ 参数。链接器必须将函数签名不一致视为致命错误。

### 5.2 Chrome Component host

Chrome 当前不能原生实例化 Component。构建器使用 Jco 将同一份 WASI 0.2 Component 转译为 core Wasm 与 ESM，并由 `examples/wasm-hello/typephp-worker.mjs` 演示宿主入口。浏览器适配不包含 PHP、PHPX 或高精度类型语义。

## 6. 产物和运行模型

### 6.1 发布产物

建议最小发布物为：

```text
dist/
├── typephp.wasm
└── typephp-wasm.mjs
```

所有 C/C++ 代码进入 `typephp.wasm`。浏览器自身不会自动提供 WASI imports；`typephp-wasm.mjs` 作为 WASI host/adapter 的装载入口，只负责：

- 获取和实例化 `.wasm`；
- 提供 stdout/stderr；
- 初始化内存文件系统；
- 实现或接入 WASI clocks、随机数等宿主能力；
- 调用导出的 TypePHP 生命周期接口；
- 把状态码和错误信息转换为 JavaScript 结果。

不应把 PHP 语义、Zend 对象操作或 TypePHP 业务逻辑放入 JavaScript loader。

### 6.2 生命周期

建议采用“模块启动一次、请求可重复执行”的模型：

```text
instantiate wasm
    -> typephp_wasm_module_startup()
    -> typephp_wasm_request_startup()
    -> TypePHP AOT entry
    -> typephp_wasm_request_shutdown()
    -> 可再次执行 request
    -> typephp_wasm_module_shutdown()
```

每次请求必须有独立的 PHP request 内存池。执行成功、PHP 异常、C++ 异常和 Zend bailout 都必须进入统一的清理路径。

模块导出 API 可从以下最小集合开始，名称以实际实现为准：

```c
int typephp_wasm_module_startup(void);
int typephp_wasm_run(int argc, const char **argv);
const char *typephp_wasm_last_error(void);
void typephp_wasm_module_shutdown(void);
```

`typephp_wasm_run()` 执行已经静态链接的 AOT 入口，不负责在运行时解析和编译任意 PHP 源码。

## 7. PHP 构建策略

### 7.1 基础配置

- 固定一个明确的 php-src commit，而不是只固定分支名。
- NTS 构建。
- 禁用 CLI、CGI、FPM、Apache 等现有 SAPI。
- 新增最小 `typephp_wasm` SAPI，或先用极小的 embed 原型验证生命周期，再收敛为专用 SAPI。
- 禁用 opcache/JIT。
- 所有扩展静态链接。
- 关闭不需要的扩展和自动探测，避免宿主机环境改变构建结果。
- 用 `config.site` 和独立 patch 目录记录交叉编译结论。

首期不要直接复制其他项目的完整 configure 参数。应从最小 PHP core 启动，根据 TypePHP PHPT 和运行时依赖逐项增加扩展。

### 7.2 扩展分层

建议把扩展分为三组：

1. **必须启用**：TypePHP 和 Zend 基本运行所需的 core、standard、SPL、date、pcre、hash、json 等，最终以实际链接和测试结果为准。
2. **可选本地扩展**：ctype、filter、mbstring、tokenizer、fileinfo、zlib 等，无操作系统网络依赖，但会增加体积。
3. **首期禁用**：sockets、curl、mysqli、PDO 网络驱动、pcntl、posix、FFI、shm、sysv、readline、opcache/JIT 等。

GMP、MPFR 和 mpdecimal 首先作为 PHPX/TypePHP 高精度实现的静态依赖处理，不要求启用 PHP `ext/gmp`。

## 8. PHP stream 和操作系统能力

### 8.1 不关闭整个 stream 子系统

PHP 标准库大量依赖 stream。完全关闭 stream 会破坏文件读写、`php://`、include 路径处理以及部分标准扩展，收益小而兼容成本高。

首期保留：

- 普通文件 stream，底层使用 Emscripten MEMFS；
- `php://memory`；
- `php://temp`；
- `php://stdin`、`php://stdout`、`php://stderr` 的宿主映射；
- `data://` 是否启用由体积和安全评估决定；
- 纯内存 stream filter 可按需启用。

### 8.2 禁用网络 stream

应在 PHP 构建和运行时注册阶段禁用或不注册：

- TCP、UDP 和 Unix socket transport；
- socket 扩展；
- `http://`、`https://`、`ftp://` 等依赖网络的 wrapper；
- `fsockopen()`、`pfsockopen()`、`stream_socket_*()`；
- 网络数据库和网络客户端扩展。

首期不应通过同步 XHR 或隐式 JavaScript fetch 模拟 PHP socket。若未来需要 HTTP，应设计显式、可授权的异步宿主 API，而不是伪造 POSIX socket。

### 8.3 其他 OS 相关功能

以下能力需要禁用、降级或由宿主注入：

| 能力 | 首期策略 |
|---|---|
| 文件系统 | MEMFS；可选只读预加载文件 |
| 当前目录和路径 | 虚拟根目录，禁止泄漏宿主路径 |
| 环境变量 | loader 注入白名单 |
| 时间 | WASI clocks；浏览器宿主使用浏览器时钟实现该接口 |
| 随机数 | WASI random；浏览器宿主使用安全随机源实现，不使用弱伪随机替代 |
| DNS、socket | 不支持 |
| 进程、shell | 不支持 |
| 信号 | 不支持 |
| 用户、组、权限 | 固定值或明确报错 |
| 文件锁 | 首期不支持跨实例锁；单实例内按需降级 |
| 持久化 | 默认关闭；Chrome 可显式启用 OPFS 文件系统快照 |

编译器应逐步增加 WASM target capability 检查：静态可识别的不支持函数在编译期报错；动态调用无法静态判断时，由运行时返回确定错误。禁止让这些调用表现为链接期缺失符号、空函数或未定义行为。

## 9. 异常、bailout 和清理

这是项目的首要技术风险，必须早于完整 PHP 功能移植进行验证。

### 9.1 编译选项

使用原生 WebAssembly exceptions 时，C 和 C++ 对 `setjmp/longjmp` 的模式必须一致。原型建议验证以下组合：

```text
C 编译：
  -sSUPPORT_LONGJMP=wasm

C++ 编译：
  -fwasm-exceptions
  -sSUPPORT_LONGJMP=wasm

最终链接：
  -fwasm-exceptions
  -sSUPPORT_LONGJMP=wasm
```

所有 PHP、PHPX、TypePHP 和第三方 C/C++ 对象必须使用同一套 ABI 和异常配置。不能只在最终链接阶段补开 C++ exception catching。

如果目标浏览器兼容性不允许原生 Wasm EH，可研究 Emscripten JavaScript exception 模式作为备选，但不得在同一发布物中混用两套模型。

### 9.2 边界规则

- C++ 异常不得未经处理地穿过导出函数进入 JavaScript。
- Zend bailout 必须被 request 顶层捕获，并进入 request shutdown。
- bailout 后不能继续析构依赖已销毁 request 内存池的悬空 PHPX 对象。
- 栈上的 PHPX `Variant`、`Object`、`Array` 和高精度对象必须在内存池仍有效时完成析构，或由专门的 bailout 安全边界接管。
- 一个请求失败后，下一次请求必须仍可执行；否则运行时只能定义为一次性实例，并在 API 中明确。

### 9.3 必测场景

- PHP 正常返回。
- PHP `throw` 被 TypePHP 代码捕获。
- PHP 未捕获异常到达请求顶层。
- `fatalError`/Zend bailout。
- C++ `throw` 和 `catch`。
- PHP 调用 C++、C++ 再调用 PHP 时抛出异常。
- bailout 发生时栈上存在 PHPX 对象和高精度对象。
- 连续执行成功、失败、成功三个请求。
- 内存增长后再次执行请求。

## 10. 内存和高精度库

### 10.1 WASM 内存

首期使用单一线性内存，并验证 `-sALLOW_MEMORY_GROWTH`。需要记录：

- 初始内存；
- 最大内存；
- PHP memory_limit；
- request 结束后的 Zend 内存回收；
- Emscripten allocator 的实际峰值；
- 多次 request 后是否持续增长。

不要在没有基准测试前选择 `emmalloc`。PHP、GMP、MPFR 和 mpdecimal 都是分配密集型组件，应在 `dlmalloc`、`emmalloc` 等候选之间测试体积与运行时间。

### 10.2 GMP、MPFR 和 mpdecimal

- 全部使用 Emscripten 工具链静态编译。
- 禁用汇编和宿主 CPU 专用优化。
- 固定 limb、整数宽度和 ABI 检测结果。
- 不依赖运行时动态库搜索。
- 运行现有 BigInt、BigFloat、Decimal PHPT，并增加最大内存、除零、精度、舍入和异常路径测试。
- 验证库异常或分配失败不会绕过 PHP request 清理。

## 11. 建议的仓库结构

建议在实现阶段增加独立目录，不把 Emscripten 条件散落到现有构建代码中：

```text
wasm/
├── README.md
├── build.sh
├── versions.env
├── config.site
├── cmake/
│   └── TypePhpWasmToolchain.cmake
├── patches/
│   ├── php-src/
│   ├── gmp/
│   ├── mpfr/
│   └── mpdecimal/
├── sapi/
│   └── typephp_wasm/
├── runtime/
│   └── typephp-wasm.mjs
└── tests/
```

维护原则：

- patch 应小而独立，一项兼容问题一个 patch；
- 每个 patch 记录上游版本、来源、原因和可删除条件；
- 下载缓存不提交到 Git；
- php-src、Emscripten 和第三方库使用校验和锁定；
- 构建产物不进入源码仓库；
- CI 至少保留 debug 和 release 两种构建。

## 12. 分阶段实施计划

### 阶段 0：工具链风险验证

目标：不接入完整 TypePHP，先证明关键底层机制可行。

- 固定 Emscripten 版本。
- 编译最小 C/C++ 混合程序。
- 验证 C++ exception。
- 验证 `setjmp/longjmp`。
- 验证两者嵌套和重复调用。
- 验证主流浏览器支持情况。

退出条件：异常和 longjmp 行为稳定，没有不可接受的浏览器缺口。

### 阶段 1：最小 PHP NTS

目标：PHP core 在浏览器中完成模块和请求生命周期。

- 交叉编译最小 php-src。
- 实现最小 WASM SAPI 或 embed 验证层。
- 支持 stdout/stderr 和 MEMFS。
- 执行固定入口。
- 验证 fatal error、异常和 request shutdown。

退出条件：连续执行“成功、失败、成功”请求无崩溃、无持续内存增长。

### 阶段 2：接入 PHPX 和 TypePHP

目标：现有 TypePHP C++ 后端可以由 `em++` 编译并静态链接。

- 为编译器增加 WASM platform/backend 配置。
- 统一 PHPX、TypePHP 和第三方库编译 flags。
- 链接一个最小 TypePHP `main()`。
- 建立 WASM smoke PHPT 子集。
- 为不支持的系统 API 增加 capability diagnostics。

退出条件：基础类型、函数、类、异常、数组和对象测试通过。

### 阶段 3：高精度与本地 stream

目标：支持 TypePHP 关键运行时能力。

- 静态链接 GMP、MPFR、mpdecimal。
- 运行高精度完整运算符和边界测试。
- 支持必要的 `file://` 和 `php://` stream。
- 增加预加载只读资源机制。
- 明确所有被禁用的 wrapper、transport 和扩展。

退出条件：高精度测试通过，本地文件行为确定，网络 API 全部可预测地失败。

### 阶段 4：体积、性能和发布

目标：形成可分发的 TypePHP WASM SDK。

- release 优化和 dead-code elimination。
- 检查导出符号白名单。
- 比较 allocator 和内存增长配置。
- 建立下载体积、启动时间和峰值内存基准。
- 生成 `typephp.wasm` 和薄 `.mjs` loader。
- 编写用户侧功能与限制文档。

退出条件：产物可复现，兼容性清单完整，性能达到预设基线。

### 阶段 5：可选宿主能力

后续按真实需求选择，不作为基础运行时默认能力：

- IDBFS 或 OPFS 持久化；
- 显式 HTTP host API；
- Node.js 宿主；
- WASI 原型；
- 多实例隔离；
- Web Worker 并行实例。

每项能力都必须通过显式 capability 开启，不能让 PHP 代码默认获得宿主全部权限。

## 13. 测试策略

### 13.1 测试层次

1. **工具链测试**：exception、longjmp、静态库、链接和导出符号。
2. **PHP 生命周期测试**：module/request startup、shutdown、bailout 和重复请求。
3. **PHPX 测试**：Variant、Object、Array、引用、异常和资源析构。
4. **TypePHP PHPT**：选择不依赖 OS 的现有测试，并维护 WASM 跳过原因。
5. **高精度测试**：完整运算符、边界、错误和内存压力。
6. **能力限制测试**：网络、进程、线程和动态扩展必须稳定拒绝。
7. **浏览器测试**：Chrome、Firefox、Safari 的最低支持版本。

### 13.2 关键指标

- `.wasm` 原始大小和压缩大小；
- 首次实例化时间；
- module startup 和 request startup 时间；
- 简单 TypePHP 程序执行时间；
- 初始、峰值和多请求后的线性内存；
- 异常和 bailout 后的可恢复性；
- JavaScript loader 大小；
- 相同输入的可复现构建校验和。

## 14. Go/No-Go 条件

出现以下任一情况，应暂停完整移植并重新评估架构：

- Zend bailout 与 C++ 栈析构无法建立安全边界；
- 请求失败会稳定破坏后续请求，且不能接受一次性实例模型；
- GMP、MPFR 或 mpdecimal 需要大规模侵入式 fork；
- `.wasm` 体积或浏览器峰值内存明显超出目标场景可接受范围；
- Safari、Firefox、Chrome 需要互不兼容的异常 ABI；
- PHPX 中依赖原生线程、动态链接或 OS 资源的假设无法隔离。

如果最短路径不可行，再评估 [BACKEND_NEUTRAL_IR.md](BACKEND_NEUTRAL_IR.md) 所述的独立 WASM runtime/backend，不应在没有原型数据时提前启动该重写。

## 15. 外部参考

- [PHP 源码仓库](https://github.com/php/php-src)
- [Emscripten：C setjmp/longjmp 支持](https://emscripten.org/docs/porting/setjmp-longjmp.html)
- [Emscripten：C/C++ 可移植性说明](https://emscripten.org/docs/porting/guidelines/portability_guidelines.html)
- [Emscripten：代码与内存优化](https://emscripten.org/docs/optimizing/Optimizing-Code.html)
- [WordPress Playground：编译 PHP 到 WebAssembly](https://developer.wordpress.org/playground/developers/architecture/wasm-php-compiling/)
- [WordPress Playground 架构](https://wordpress.github.io/wordpress-playground/developers/architecture/)
- [seanmorris/php-wasm](https://github.com/seanmorris/php-wasm)
- [soyuka/php-wasm](https://github.com/soyuka/php-wasm)

这些链接用于追踪上游行为和已知移植问题。TypePHP 的最终实现和兼容性必须由自己的构建、测试及基准验证，不能直接继承其他项目的结论。

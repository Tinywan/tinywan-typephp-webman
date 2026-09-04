# 构建 TypePHP WASI 程序

TypePHP 使用稳定的 WASI 0.2（Preview 2）和 Component Model。TypePHP 生成的 C++、PHPX 核心、精简的 PHP 8.5 NTS、GMP、MPFR 和 mpdecimal 会静态链接为单个 `.wasm` command 或 library component。WASI 0.1（Preview 1）不受支持。

## 环境要求

- WASI SDK 33 或更高版本（LLVM/Clang/LLD 22 或更高）
- PHP 8.4 或更高版本，用于运行 TypePHP 编译器
- Wasmtime 47 或更高版本，用于运行和测试产物
- Jco 1 或更高版本，用于 browser profile；component profile 不需要 Jco
- wit-bindgen-cli 0.60.0，用于 library/WasmExport 模式；command 模式不需要
- 与当前 TypePHP 版本绑定的 `wasm32-wasip2` 集成 SDK

WASI SDK 的 `bin` 目录和 Wasmtime 必须加入系统 `PATH`。编译器不会探测或使用 `/opt` 等约定安装目录，也不接受专用的工具目录配置。WASI 静态库和头文件统一安装到 PHPX 的 `wasm/wasm32-wasip2/`：

```bash
export PATH="<wasi-sdk-bin>:<wasmtime-bin>:$PATH"
```

TypePHP 使用现有的 PHPX 定位规则：优先读取 `PHPX_HOME`，其次读取 Composer 的 `swoole/phpx` 安装位置，最后使用 `vendor/swoole/phpx`。不新增 WASI 专用环境变量。

WASI 构建会检查 `wasm32-wasip2-clang`、`wasm32-wasip2-clang++`、`llvm-ar`、`llvm-ranlib`、`llvm-nm`、`wasm-component-ld` 和 `wasmtime`，并确认目标是 `wasm32-unknown-wasip2`。browser profile 另外检查 `jco`，library 模式另外检查固定版本的 `wit-bindgen`。所有工具只从 `PATH` 查找；npm script 会自动将项目本地的 `node_modules/.bin` 加入 `PATH`。

## 一条命令构建

command 模式的源文件必须提供 `main(): void`：

```php
<?php
function main(): void
{
    echo "Hello from TypePHP/WASI\n";
}
```

执行：

```bash
php bin/tpc.php --wasm hello.php
```

单文件输入默认只生成当前目录下可由 Wasmtime 执行的 `hello.wasm` Component，不要求安装 Jco。生成的 `.cc` 与 host 模式使用相同的 build 目录规则，默认位于 TypePHP 根目录的 `build/`；可以使用 `--build-dir <directory>` 覆盖。

项目可以直接使用 `project.yml`：

```yaml
name: wasm-hello
mode: bin
wasm: component
build-dir: build
output: component/wasm-hello.wasm
sources:
  - src
```

`wasm` 只接受 `component` 或 `browser`，不接受布尔值。配置后直接执行 `php bin/tpc.php project.yml` 即可进入 WASI 构建，无需重复传入 `--wasm`。WASM 项目未配置 `target-platform` 时默认使用 `wasm32-wasip2`；`build-dir`、`output` 和 `wasm-browser-dir` 都相对于项目文件解析。完整浏览器应用见 `examples/wasm-hello/`，它显式使用 `wasm: browser`。

需要生成浏览器模块时，配置 `wasm: browser` 和 `wasm-browser-dir`，并确保 Jco 位于 `PATH`。

命令行也可以显式选择产物：

- `--wasm` 或 `--wasm=component`：仅生成可由 Wasmtime 运行的 Component，不检测 Jco。
- `--wasm=browser`：生成 Component 和 Jco 浏览器模块，需要 `jco` 位于 `PATH`。

路径、sources 等详细配置继续放在 `project.yml`，不通过 `--wasm=` 传递。

PHP、PHPX、TypePHP runtime、GMP、MPFR 和 mpdecimal 由 SDK 发布阶段预编译为 WASI 静态库。应用构建只编译 TypePHP 为当前程序生成的 C++，然后链接这些 `.a`。`tpc --wasm` 不会下载源码，也不会调用 PHP、PHPX 或高精度库的构建脚本。library 模式会调用 `PATH` 中的 `wit-bindgen-cli 0.60.0` 生成当前应用的 Canonical ABI 绑定。

PHP/WASI 当前静态内建 `date`、`pcre`、`hash`、`json`、`lexbor`、`random`、`Reflection`、`SPL`、`standard`、`uri`、`ctype`、`calendar`、`bcmath`、`filter`、`tokenizer`、`mbstring`、`zlib`、`fileinfo`、`sodium`、`openssl`、`libxml`、`dom`、`SimpleXML`、`xml`、`xmlreader`、`xmlwriter`、`PDO`、`pdo_sqlite`、`zip`、`bz2` 和 `exif` 扩展。OpenSSL 采用 crypto-only 构建，不包含 TLS stream transport；HTTP/HTTPS 仍由 WASI HTTP Component 提供。

每个 C/C++ 翻译单元统一使用标准 Wasm C++ exceptions 和 WASI SJLJ；链接阶段将 ABI 警告视为错误，旧的 32 位 `zend_long` 缓存也会自动失效。

运行：

```bash
wasmtime hello.wasm
```

Chrome Demo：

```bash
cd examples/wasm-hello
npm ci
npm run wasm
npm run dev
```

浏览器端始终在专用 Worker 中执行 Component。默认使用内存文件系统；发送给 Worker 的启动消息设置 `persistent: true` 后，会在启动和退出时通过 OPFS 恢复、保存文件系统快照。程序执行期间仍使用同步内存文件系统，避免每次 PHP 文件访问跨越异步 JS 边界。

## Command 与 Library 的 ZendVM 生命周期

### Command 模式

command 模式具有生成的 C++ `main()` 入口。入口依次调用：

```text
typephp_<project>_runtime_init(argc, argv)
    → php_embed_init()
    → PHP/SAPI module startup 与 MINIT
    → PHP request startup 与 RINIT
    → 注册并启动当前 TypePHP 应用模块
    → 当前应用的 MINIT 与 RINIT

执行 TypePHP main()

typephp_<project>_runtime_shutdown()
    → 当前应用的 RSHUTDOWN 与模块清理
    → php_embed_shutdown()
    → PHP request/module/SAPI shutdown
```

调用者不需要感知这些步骤，因为生成的原生 `main()` 会自动包围整个程序生命周期。

### Library 模式必须先创建 runtime resource

library component 没有可自动执行的 `main()`，单纯实例化 `.wasm` 只完成 Component 和 C/C++ Runtime 的实例化，不代表 ZendVM request 已经可用。Host 必须先调用生成的 WIT 函数：

```wit
create-runtime: func() -> result<runtime, typephp-error>;
```

浏览器中对应的调用为：

```js
const component = await instantiate(null, wasi.getImportObject());
const runtime = await component.api.createRuntime();

try {
    const result = await runtime.someExportedFunction();
} finally {
    runtime[Symbol.dispose]();
}
```

`createRuntime()` 内部通过 `TYPEPHP_RUNTIME_INIT(<project>)(1, argv)` 调用项目级初始化符号。Host
只需要调用这一层稳定接口，不应直接调用 `php_embed_init()`、MINIT、RINIT 或任何 Zend C API。

当前初始化顺序如下：

1. `php_embed_init()` 初始化 Embed SAPI、PHP 核心和静态扩展，并启动 PHP request；PHP 核心与已经注册的静态扩展在这里完成 MINIT/RINIT。
2. 设置 PHPX 的异常桥接，使 PHP 异常可以安全返回到生成的 WIT `result`。
3. 取得当前 TypePHP 应用的 `zend_module_entry`，调用 `zend_register_module_ex()` 和 `zend_startup_module_ex()`，完成应用模块注册与 MINIT。
4. 注册标准流并设置请求路径等 SAPI 请求信息。
5. 因为 Embed request 和请求内存池此时已经启动，生成代码会显式调用当前应用模块的 `request_startup_func`，补做该模块的 RINIT；RINIT 再初始化 TypePHP 请求级全局变量和类静态数据，完成后才返回 `runtime` resource。

这里“手动”调用的是 Host 可见的 `create-runtime()`，而不是让用户手动拼装 ZendVM 生命周期。MINIT/RINIT 的具体调用及其先后顺序全部封装在 PHPX 和生成的 Component adapter 中。

### 导出调用共享同一个 request

同一 `runtime` resource 上的所有 `#[WasmExport]` 调用共享一次 RINIT 建立的 Zend request：

- 不会在每次函数调用前后重复执行 RINIT/RSHUTDOWN。
- PHP request 内存池、请求级全局变量和静态状态会持续到 resource 被释放。
- 当前仅支持 NTS；同一个 runtime 上的调用必须串行，生成的 adapter 会拒绝并发或重入调用。
- 普通 PHP 异常会被转换为 WIT `result` 错误，runtime 仍然可以继续使用。
- Zend bailout 表示请求状态可能已经损坏，adapter 会将 runtime 标记为 failed，后续调用会被拒绝，直到 resource 被释放。

### 释放 resource 才会执行 RSHUTDOWN

释放 WIT `runtime` resource 会通过 `TYPEPHP_RUNTIME_SHUTDOWN(<project>)()` 调用项目级关闭符号：

1. 调用当前 TypePHP 应用模块的 RSHUTDOWN，清理 TypePHP 请求级对象和全局数据。
2. 注销并关闭当前应用模块，执行相应模块清理。
3. 调用 `php_embed_shutdown()`，完成其余扩展的 request shutdown、module shutdown 和 SAPI shutdown。
4. 最后释放 request 内存池，避免 PHP/CPP 包装对象在内存池消失后继续析构。

不要只依赖 JavaScript GC 触发 resource finalizer。浏览器和 Node Host 应在 `finally` 中显式调用 `runtime[Symbol.dispose]()`；Wasmtime 或其他 Host binding 也应显式 drop resource。直接终止 Worker 或进程会回收整个 Wasm 实例，但不保证 PHP 的 RSHUTDOWN/MSHUTDOWN 回调得到执行，因此不能把必须持久化的数据只放在关闭回调中。

一个 Component 实例当前只允许同时存在一个活动的 runtime resource。释放完成后可以重新创建；初始化失败或发生 Zend bailout 时，应先释放旧 resource，而不是继续调用导出函数。

## 高精度类型

WASI 产物包含 TypePHP 的三种语言级高精度类型：

- `BigInt`：GMP 6.3.0
- `BigFloat`：MPFR 4.2.2
- `Decimal`：mpdecimal 4.0.1

完整示例位于 [high-precision.php](../examples/high-precision.php)。构建并运行：

```bash
php bin/tpc.php --wasm examples/high-precision.php
wasmtime -S http high-precision.wasm
```

预期输出：

```text
1111111101111111110111111111010
1000000000000000000000000000001
12348.14159265358979324
```

wasm32 使用 32 位指针，但 PHP 的 `zend_long` 保持 64 位，以维持 TypePHP 与 64 位 PHP 的整数语义。GMP 和 mpdecimal 使用 32 位 limb；这不改变任意精度语义，但大数吞吐量低于具有汇编优化的原生 64 位构建。

## 当前平台边界

- 仅支持 NTS、单线程。
- Fiber 和 Generator 被禁用；编译器在发现 `yield` 时直接报致命错误。
- PHPX Facade API 在 `__wasi__` 下整体禁用。PHPX 核心类型和 `phpx_std` 仍可使用。
- 不支持动态扩展、网络 socket、进程、shell 和信号。静态可识别的调用会在编译期报致命错误。
- 保留 PHP stream 框架、本地文件能力以及由 WASI host 提供的时间和随机数能力。
- command component 可由 Wasmtime 直接运行；library component 需要 Host 按 WIT 接口调用 `create-runtime()` 和导出函数。Chrome 使用 Jco 生成的 ESM 和 `examples/wasm-hello/typephp-worker.mjs` 中的 Worker host。

PHPX Facade 只是为 PHP 可选扩展生成的便捷包装，并非 TypePHP ABI 的组成部分。WASI 下整体关闭它，可以避免把 curl、socket、Swoole 等不可用 API 暴露为“可编译但链接失败”的接口；PHP/WASI 静态内建扩展本身不受 Facade 开关影响。

## WASI SDK 目录

集成 SDK 使用唯一、完整的前缀，位于 PHPX 根目录的 `wasm/wasm32-wasip2/`：

```text
phpx/wasm/wasm32-wasip2/
├── include/php/             # PHP 安装头文件
├── include/phpx/            # PHPX 和 TypePHP runtime 头文件
├── include/gmp.h ...
├── lib/libphp.a
├── lib/libphpx.a
├── lib/libgmp.a
├── lib/libgmpxx.a
├── lib/libmpfr.a
├── lib/libmpdec.a
├── lib/libmpdec++.a
└── .typephp-wasi-sdk-abi
```

普通用户通过 TypePHP/PHPX 集成安装包获得该目录。TypePHP 开发者需要自行 clone 与当前版本绑定的 `php-8.5.9-wasm` 和 PHPX 源码，并通过 `wasm/build-sdk.sh` 组装完整 SDK。PHP/WASI 只负责 PHP；PHPX 负责 GMP、MPFR、其专属的 mpdecimal 以及 PHPX runtime。所有产物安装到同一个 PHPX checkout。若 PHPX 不在 `vendor/swoole/phpx`，继续使用已有的 `PHPX_HOME` 指向该 checkout。

不提供单独覆盖 `libphp.a`、`libphpx.a` 或数值库的路径；所有库、头文件和 `.typephp-wasi-sdk-abi` 必须来自同一次兼容构建，避免混用不同的 `zend_long`、C++ exceptions、SJLJ 或 Component Model ABI。

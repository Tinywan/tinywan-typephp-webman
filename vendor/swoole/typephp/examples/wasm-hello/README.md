# TypePHP WASI Browser Lab

这是一个由 `project.yml` 构建的完整 TypePHP/WASI 0.2 浏览器应用。PHP 代码编译成一个自包含的 Component，Jco 将同一个 Component 转译为浏览器 ESM，页面通过 module Worker 加载它。

Demo 展示以下已支持能力：

- 命令行参数、环境变量与标准输入/输出
- WASI wall clock 和 PHP `time()` / `date()`
- 安全随机数 `random_int()` / `random_bytes()`
- 内存文件系统，以及可选的 OPFS 快照持久化
- 通过同步 `file_get_contents()` 发起 HTTP/HTTPS GET；浏览器等待期间由 JSPI 挂起 Wasm 调用栈
- PHP 8.5 runtime 信息
- 由 `get_loaded_extensions()` 动态读取的 PHP/WASI 内置扩展列表；点击扩展后，JavaScript 调用 `#[WasmExport]` 导出的函数读取版本、函数、类、常量和 INI 配置
- TypePHP 语言级 BigInt、Decimal、BigFloat 高精度计算

原始 socket、进程、shell、信号、Fiber 和 Generator 明确不支持。

浏览器构建要求支持 `WebAssembly.Suspending` 和 `WebAssembly.promising`
的 JSPI 实现。HTTP 请求仍受浏览器 CORS、CSP 和 Mixed Content 策略约束。
`file_get_contents()` 当前支持 GET、`http.timeout` 和
`http.ignore_errors`；不提供 Curl API，也不会退化为忙等待。

## 构建

先确保 WASI SDK 和 Wasmtime 已加入 `PATH`，然后在本目录执行：

```bash
npm ci
npm run wasm
```

等价的仓库根目录命令是：

```bash
php bin/tpc.php examples/wasm-hello/project.yml
```

`project.yml` 控制全部项目路径：

- `sources: src`：TypePHP 源码
- `mode: library`：生成可由 JavaScript 多次调用的 Component，而不是运行一次即退出的命令
- `build-dir: build`：生成的 C++ 与目标文件
- `output: component/wasm-hello.wasm`：WASI 0.2 Component
- `wasm: browser`：显式生成浏览器模块；简单项目使用 `wasm: component` 只生成 Component
- 未配置 `target-platform` 时，WASM 项目默认使用 `wasm32-wasip2`
- `wasm-browser-dir: generated`：Jco 浏览器模块
- `wasm-package` 和 `wasm-world`：定义导出接口的稳定 WIT 名称

Jco 是本项目的开发依赖。先执行 `npm ci`，之后通过 `npm run wasm` 构建时，npm 会自动把本地 `node_modules/.bin/jco` 加入 `PATH`。

若只需要供 Wasmtime 使用的 Component，可以绕过 Jco：

```bash
php ../../bin/tpc.php project.yml --wasm=component
```

## 浏览器运行

```bash
npm run dev
```

打开终端显示的本地地址。可以修改参数、环境变量和 stdin 后重复运行；勾选 OPFS 后，PHP 写入虚拟文件系统的运行次数会跨页面刷新保存。点击任意 PHP 扩展名称，页面会向 Worker 发送请求，Worker 调用 Wasm `runtime.getExtensionInfo()` 导出函数，最后由 JavaScript 解析返回的 JSON 并渲染扩展详情。

生产构建：

```bash
npm run build
```

`src/` 是 TypePHP 应用，`typephp-worker.mjs` 是浏览器 WASI host，`main.js` 和 `style.css` 负责交互界面。`build/`、`component/`、`dist/`、`generated/` 均为可重新生成的输出。

WASI 运行库、PHPX 和生成的 C++ 均使用 `-O2` 编译。最终链接会移除调试与符号段，以减少浏览器下载、解析和编译 Wasm 的开销；构建过程不会自动调用系统中的 `wasm-opt`，避免旧版 Binaryen 与 WASI SDK 生成的 Wasm 异常指令不兼容。

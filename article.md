# 将 Webman 打包为独立原生 .exe：基于 TypePHP 的 AOT 静态编译实践

> **作者**：Tinywan  
> **开源项目**：[Tinywan/tinywan-typephp-webman](https://github.com/Tinywan/tinywan-typephp-webman)

在上一篇关于 TypePHP 基础编译与入门实践的文章发布后，不少读者朋友留言交流：**像 Webman 这样重度依赖常驻内存、事件循环和底层网络驱动的现代高性能 PHP 框架，真的能够编译成完全脱离 PHP 解释器的原生二进制独立程序吗？**

答案是：**完全可以，且能稳定提供 HTTP 服务！**

但在将动态解释型常驻内存框架编译为 C++ 原生机器码的过程中，会触发一系列此前在 PHP-CLI 解释模式下从未暴露过的底层机制冲突与强类型校验。本文将核心编译工作流、深水区踩坑要点及解决方案进行完整复盘。

## 一、核心工作流：从源码到独立 Dist 分发包

在 Windows 平台下，我们通过两条批处理脚本实现从源码编译到发布包制作的工业化流程：

### 1. 源码编译阶段 (`build.bat`)

调用 `tpc.exe project.yml`，由 MSVC（`cl.exe` + `link.exe`）将 Webman AST 翻译出的 C++ 代码与 `phpx`、`libphp8embed` 静态链接，生成核心二进制：`build/webman_server.exe`。

### 2. 自动化组装分发包 (`package.bat`)

自动将可执行文件、运行时核心动态库（`php8ts.dll`、`phpx.dll`）、扩展目录（`ext/`）、配置文件（`config/`）与静态模板（`public/`、`view/`）自动组装至 `dist/` 独立目录。

同时配套提供 `dist/run.bat`，在切入执行目录的同时自动注入 `PHPRC` 与临时 `PATH`，确保在任何终端或工作路径下均可双击直接拉起。

## 二、静态编译特有冲突与深度适配

PHP-CLI 解释器的动态容错机制掩盖了许多底层边界问题，而 TypePHP 的 AOT（Ahead-Of-Time）静态编译则要求符号、形参与作用域在编译期绝对确定：

### 1. 消除 AST 属性注解与路由器的符号二义性

在 `App.php` 中同时引入了路由注解与路由器：
```php
use support\annotation\route\Route as RouteAttribute;
use Webman\Route\Route as RouteObject;
```
常规 PHP 在执行时能结合上下文动态区分，但 AOT 编译器在静态符号绑定阶段，极易将内部的 `Route::dispatch()` 误绑定到注解类。
* **解决方案**：分发器与兜底逻辑内统一显式指定全限定类名 `\Webman\Route::dispatch()`、`\Webman\Route::getFallback()` 以及 `static::setCollector()`，消除静态分析工具和编译器的二义性。

### 2. 闭包形参严格校验 (`ArgumentCountError`)

Workerman 源码中多处采用了简写的静默错误处理闭包：
```php
set_error_handler(static fn (): bool => true);
```
在官方 PHP 解释器中，底层触发错误传递 4 个参数时，解释器会自动丢弃多余实参；但在 TypePHP 生成的强类型 C++ 运行时中，会严格校验形参期望，导致抛出 `ArgumentCountError (expects 0, 4 given)`。
* **解决方案**：全局规范为变长参数签名 `static fn (...$args): bool => true`，同理修正 `Worker::stopAll()` 中 `array_walk` 闭包的接收签名。

### 3. 内嵌运行模式支持 (`embed` SAPI)

编译生成的独立 `.exe` 本质是通过 `libphp8embed` 承载的独立宿主，运行时 `PHP_SAPI` 常量为 `'embed'`。Workerman 启动环境校验默认仅允许 `'cli'` 和 `'micro'`。
* **解决方案**：在 `Worker::checkSapiEnv()` 白名单中补充加入 `'embed'` 判定。

### 4. Windows 事件驱动与信号回调可见性 (`TypeError`)

在 Windows Select 事件轮询以及退出生命周期中，外部驱动层会直接回调 `acceptTcpConnection()`、`acceptUdpConnection()`、`checkErrors()` 与 `signalHandler()`。原代码中的 `protected` 访问修饰符在跨类/跨作用域回调时会被底层拦截。
* **解决方案**：将上述回调方法统一调整为 `public`，并采用 PHP 8.1+ 第一型可调用语法 `static::signalHandler(...)`，彻底解决 `TypeError: Argument must be of type callable, array given`。

### 5. 动态语言特性的静态化重构

视图引擎 `Raw.php` 原生依赖 `extract()` 向局部作用域动态注入变量，且可能伴随 `$$` 动态变量语法。静态编译器无法在编译期推导其栈帧布局。
* **解决方案**：对视图引擎进行静态化改造，使用确定性的遍历占位替换方案替代原生动态 `extract()`。

## 三、最终运行与接口全量验证

经过全面适配，独立分发包在无任何 PHP 环境的裸机环境中运行测试：

```powershell
cd dist
.\webman-server.exe
```

控制台正常输出 Workerman 启动界面：
```text
---------------------------------------------- WORKERMAN -----------------------------------------------
Workerman/5.2.2         PHP/8.5.10 (JIT off)          Windows NT/6.2
----------------------------------------------- WORKERS ------------------------------------------------
worker                  listen                              processes   status
webman                  http://0.0.0.0:8787                 1           [ok]
--------------------------------------------------------------------------------------------------------
Press Ctrl+C to stop. Start success.
```

### 核心路由与接口测试结果

* **欢迎首页 (HTML)**：`GET http://127.0.0.1:8787/` ➜ **HTTP 200 OK**（输出欢迎界面）
* **JSON API**：`GET http://127.0.0.1:8787/index/json` ➜ **HTTP 200 OK**（输出 `{"code":0,"msg":"ok"}`）
* **视图渲染**：`GET http://127.0.0.1:8787/index/view` ➜ **HTTP 200 OK**（输出 `hello webman`）
* **404 兜底路由**：`GET http://127.0.0.1:8787/not-found` ➜ **HTTP 404 Not Found**（标准 404 错误页）

## 四、开源贡献与官方 PR

本次在二进制编译中沉淀的代码优化，同时显著增强了框架在 PHP 8 严格模式与现代静态分析工具（PHPStan/Psalm）下的健壮性。目前已分别向官方提交了 PR：

* **Workerman 核心库**：`fix: improve PHP 8 strict callback compatibility, visibility, and allow embed SAPI`
* **Webman 框架**：`fix: eliminate symbol ambiguity and improve strict callback compatibility`

完整工程配置、批处理打包脚本以及所有适配细节均已开源，欢迎体验交流与探讨！

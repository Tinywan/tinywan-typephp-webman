# TypePHP (TPC) Webman 编译与原生二进制打包实践

本项目是基于 **TypePHP**（将 PHP 代码静态 AOT 编译为 C++ 原生机器码）对 **Webman / Workerman** 进行独立二进制程序编译、打包与运行的实践方案。

---

## 1. 快速构建与运行

### 1.1 纯代码编译 (Build)
```cmd
build.bat
```
- 调用 `tpc.exe project.yml`，通过 MSVC `cl.exe` + `link.exe` 编译生成 `build/webman_server.exe`。

### 1.2 自动打包为独立分发包 (Package)
```cmd
package.bat
```
- 先执行 `build.bat`，随后将可执行文件、核心动态库 DLL、PHP 扩展（ext）、配置文件与视图模板等自动同步打包至 `dist/` 目录。

### 1.3 启动服务 (Run)
```powershell
# 方式 1：进入 dist 目录直接启动二进制
cd dist
.\webman-server.exe

# 方式 2：执行启动脚本（自动配置 PHPRC 与 PATH）
.\dist\run.bat
```

---

## 2. 踩坑与问题解决汇总 (Troubleshooting)

### 一、环境配置问题 (Environment)
1. **PHP_HOME 环境变量指向错误**
   - **现象**：构建时找不到 `php8ts.lib` / `phpx.lib`，或 DLL 缺失导致链接失败。
   - **解决**：在 `build.bat` 和 `package.bat` 中强制显式指定 `set "PHP_HOME=D:\workspace\tpc_v0.6.5_windows_x86_64"`，不再依赖可能冲突的系统环境变量。
2. **Windows CMD 字符编码冲突**
   - **现象**：批处理文件在 Windows 默认 GBK 终端下因 UTF-8 中文注释乱码直接中断退出。
   - **解决**：脚本统一使用标准英文日志与 ASCII 编码注释。
3. **工作目录 (CWD) 与动态库加载路径问题**
   - **现象**：在项目根目录下直接运行 `.\dist\webman-server.exe` 报错找不到配置或 DLL。
   - **解决**：在 `run.bat` 中通过 `cd /d "%~dp0"` 切入 `dist/`，并设置 `PHPRC` 与 `PATH`。

---

### 二、编译阶段问题 (Compile / Build)
1. **重复类/函数定义冲突 (Duplicate class / Cannot redeclare function)**
   - **现象**：`support\Request`、FastRoute `simpleDispatcher()` 等提示重复定义。
   - **解决**：在 `project.yml` 中忽略根目录冗余的 `support`，只保留 `vendor/workerman/webman-framework/src/support`；在 `main.php` 中移除动态 `require_once $autoload;`，由 TypePHP 统一进行静态符号管理。
2. **不支持动态语言特性 `extract()` 与 `$$` 语法**
   - **现象**：`Raw.php` 模板引擎编译时报 `Unsupported function: extract` 或 `The $$ syntax is not supported`。
   - **原因**：C++ 强类型静态编译器无法在编译期为动态变量注入确定栈帧。
   - **解决**：重构 `Raw.php` 的模板变量渲染逻辑，采用静态友好的内容占位替换方案。

---

### 三、运行阶段问题 (Runtime)
1. **SAPI 运行模式拦截 (`checkSapiEnv`)**
   - **现象**：Workerman 抛出异常强制退出，因为当前运行在 `embed` SAPI（内嵌 PHP 运行时）下。
   - **解决**：修改 `Worker.php` 的 `checkSapiEnv()`，允许 `PHP_SAPI === 'embed'`。
2. **无参闭包传参严格报错 (`ArgumentCountError`)**
   - **现象**：`set_error_handler(static fn (): bool => true)` 与 `Worker::stopAll()` 的 `array_walk` 抛出 `expects exactly 0 arguments, 4 given`。
   - **解决**：将所有错误处理闭包声明为变长参数 `static fn (...$args): bool => true`。
3. **Windows 下回调方法的访问控制权限错误 (`TypeError`)**
   - **现象**：外部事件循环与注册的 shutdown 函数调用 `checkErrors()`、`acceptTcpConnection()`、`acceptUdpConnection()` 时报 `cannot access protected method`。
   - **解决**：将这些跨上下文调用的类方法访问级别从 `protected` 改为 `public`。
4. **命名空间与别名歧义导致的方法未定义 (`Invalid callback`)**
   - **现象**：`support\App::request`、`Webman\Route\Route::dispatch`、`Webman\Route\Route::getFallback` 等方法不存在报错。
   - **原因**：类中同时 `use` 了属性注解同名类（如 `RouteAttribute`、`MiddlewareAttribute`），AOT 编译器在 AST 解析时发生歧义绑定。
   - **解决**：在关键分发逻辑中显式指定全限定类名（如 `\Webman\Route::dispatch()`、`\Webman\App::request()` 等）。
5. **单机模式缺少 Route/Middleware 启动装载**
   - **现象**：`call method dispatch on null`。
   - **解决**：在 `main.php` 启动序列中主动调用 `\Webman\Middleware::load()` 与 `\Webman\Route::load([config_path()])`。

---

## 3. Workerman / Webman 官方 PR 提案整理

以下修改点可向官方仓库提交 PR，有助于提升框架在 PHP 8 严格模式、静态分析工具以及原生嵌入式（SAPI/AOT）环境下的兼容性。

### PR 1：提交给 `workerman/workerman` 仓库

**PR 标题**：`fix: improve PHP 8 strict callback compatibility, visibility, and allow embed SAPI`

**修改内容说明**：
1. **统一错误处理器与退出回调形参签名（修复 `ArgumentCountError`）**：
   - 将 `Worker.php` 中的 `set_error_handler(static fn (): bool => true)` 调整为 `set_error_handler(static fn (...$args): bool => true)`。
   - 将 `Worker::stopAll()` 中 `array_walk` 的闭包形参调整为 `static fn (Worker $worker, mixed ...$args) => $worker->stop(false)`。
2. **支持 `embed` SAPI 运行环境**：
   - 在 `Worker::checkSapiEnv()` 中补充支持 `PHP_SAPI === 'embed'`。
3. **修复 Windows 下网络接收与错误检查回调的访问控制**：
   - 将 `Worker` 类中的 `acceptTcpConnection()`、`acceptUdpConnection()`、`checkErrors()` 访问修饰符由 `protected` 调整为 `public`。

---

### PR 2：提交给 `workerman/webman-framework` 仓库

**PR 标题**：`fix: eliminate Route/Middleware attribute alias ambiguity and fix getFallback signature`

**修改内容说明**：
1. **消除属性注解与核心类同名引起的符号歧义**：
   - 在 `App.php` 中使用 `\Webman\Route::dispatch()` 与 `\Webman\Route::getFallback()`，避免与局部引入的 `Webman\Route\Route as RouteObject` / `support\annotation\route\Route` 发生 AST 解析二义性。
   - 在 `Route.php` 中使用 `static::setCollector()` 替代 `Route::setCollector()`。
2. **规范默认 404 回调签名**：
   - 将 `App::getFallback()` 默认返回的闭包形参规范为 `function ($req = null)`，防止分发器传参时在严格参数校验模式下抛出参数个数不匹配异常。
3. **优化 `Middleware::prepareAttributeMiddlewares` 传参**：
   - 避免将临时数组直接作为引用传递，提升类型安全性。

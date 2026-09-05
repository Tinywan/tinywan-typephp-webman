# TypePHP Webman

本项目基于 [TypePHP](https://www.swoole.com/)（Swoole 研发的 PHP AOT 静态编译器），将 **Webman / Workerman** 项目直接静态编译为原生二进制机器码（ELF / PE），实现高性能独立发布与零依赖运行。

---

## 🚀 核心特性

- **AOT 原生编译**：PHP 代码直接转译为 C++17 并编译为机器码，脱离传统 PHP 解释器。
- **跨平台全目标**：支持 Windows x64、Linux x64（动态链接版）与 Linux x64（Musl 纯静态单文件，无外部 libc 依赖）。
- **CI/CD 自动化**：GitHub Actions 自动构建跨平台产物，推送 Tag 即可发布 Release 包。
- **完整框架生态**：兼容路由、中间件、静态文件分发与模板渲染。

---

## 📦 快速使用

### 1. 本地打包构建

#### Windows (MSVC)
```cmd
# 仅编译生成二进制
build.bat

# 编译并打包到 dist/ 目录 (包含 exe, dll, 配置与视图)
package.bat
```

#### Linux (Clang / GCC)
```bash
# 动态链接版本打包 (生成 dist/webman-server.bin 及关联 so)
./package.sh

# 纯静态版本打包 (基于 Musl libc，生成单文件 dist/webman-server)
./package.sh --full-static
```

### 2. 启动服务

#### Windows
```cmd
cd dist
webman-server.exe
```

#### Linux
```bash
cd dist

# 启动服务
./start.sh start

# 守护进程模式
./start.sh start -d

# 查看状态与停止
./start.sh status
./start.sh stop
```

---

## 🛠️ Release 发布矩阵

| 目标平台 | 构建类型 | 产物文件名 | 说明 |
| :--- | :--- | :--- | :--- |
| **Linux x64** | 动态链接 (`dynamic`) | `typephp-webman-php8.5-linux-x64.tar.gz` | 自包含 PHP embed 及扩展共享库 |
| **Linux x64 (Static)** | 纯静态 (`static`) | `typephp-webman-php8.5-linux-x64-static.tar.gz` | 基于 Musl libc 全静态编译，零外部动态依赖 |
| **Windows x64** | 动态链接 (`dynamic`) | `typephp-webman-php8.5-windows-x64.zip` | 包含可执行程序、dll 及运行依赖 |

---

## 💡 关键适配记录

1. **SAPI 兼容**：允许 Workerman 在 `PHP_SAPI === 'embed'` 模式下启动。
2. **符号与注解解耦**：消除路由与属性注解同名类在 AST 静态解析下的命名冲突。
3. **闭包严格签名**：统一信号处理器与退出回调形参为可变参数，适配 PHP 8 严格模式。
4. **模板引擎优化**：重构 `Raw.php`，采用静态友好的占位渲染方案，替代动态 `extract()` 语法。


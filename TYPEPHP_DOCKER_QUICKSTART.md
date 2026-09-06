# 零环境依赖！像 Go 一样分发 PHP：Docker 一键编译原生二进制

> **摘要**：  
> TypePHP 作为 Swoole 团队开源的 PHP 深度 AOT（Ahead-of-Time）原生静态编译器，能将 PHP 代码直接编译为原生汇编机器码，赋予 PHP 媲美 Go / Rust 的启动速度与单文件分发能力。然而，本地配置 C++17、PHP 8.5 ZTS/Embed 开发库及 GCC/Clang 复杂的工具链，往往让许多开发者望而却步。  
> 本文分享如何使用开箱即用的官方 Docker 构建镜像，实现**本地零 C++、零 PHP 环境依赖，仅需一行挂载命令，秒级完成原生二进制构建**，并详细记录构建过程输出及解决 `/usr/bin/ld: cannot open output file app: Is a directory` 等典型问题的处理方法。

---

## 快速上手（只需 3 步）

### 第 1 步：准备一个 PHP 文件

在你的工作目录下新建一个 `main.php`：

```php
<?php

declare(strict_types=1);

function main(): int
{
    printf("Hello World from TypePHP Docker AOT!\n");
    return 0;
}
```

> **注意**：由于是直接编译成机器码，执行入口代码必须写在 `function main(): int` 里面，输出使用 `printf()`。

---

### 第 2 步：一行命令启动编译

在当前目录下打开终端，运行对应的 Docker 挂载命令：

#### 🔹 Windows PowerShell
```powershell
docker run --rm -v "${PWD}:/app" tinywan/typephp-linux-x64:v0.7.0
```

#### 🔹 Linux / macOS / Git Bash
```bash
docker run --rm -v "$(pwd):/app" tinywan/typephp-linux-x64:v0.7.0
```

#### 🔹 Windows CMD
```cmd
docker run --rm -v "%cd%:/app" tinywan/typephp-linux-x64:v0.7.0
```

---

### 构建过程真实输出

容器启动后会自动完成探测、代码转译与 C++ 深度编译，终端会实时输出构建全过程：

```text
[TypePHP] Working directory: /app
[TypePHP] Detected PHP entrypoint: main.php
[TypePHP] Auto-generating project.yml for app...
[TypePHP] Generated project.yml:
name: app
bin: app.bin

sources:
  - main.php
----------------------------------------
[TypePHP] Starting AOT compilation via tpc...
Initialized platform/backend: Linux + GCC (g++)
prepare: main.php
prepare completed: 1 source files in total
convert: main.php
generate arginfo file: main.php
[pch] built: opt/typephp/vendor/swoole/typephp/build/cache/pch/31e80de1e5c0ecccc9195c7b/typephp_pch.hpp.gch
Starting parallel compilation with 4 jobs for 6 files
Compiling [████████████████████████████████████████] 100% (6/6)
Successfully compiled 6 files
g++ '@./app.rsp' -o 'app.bin' -L'/opt/typephp/vendor/swoole/phpx/lib' -L'/usr/lib' -lphpx -lphp -lgmp -lgmpxx -lmpfr -lstdc++
Build successful: app.bin
```

仅需几秒钟，当前目录下就会多出一个原生 Linux ELF 二进制文件 **`app.bin`**！

---

### 第 3 步：运行编译出的二进制程序

编译出来的 `app.bin` 是 Linux 平台的原生二进制文件，无需额外配置，可以直接通过当前容器运行验证：

```powershell
docker run --rm -v "${PWD}:/app" tinywan/typephp-linux-x64:v0.7.0 ./app.bin
```

终端会直接输出：
```text
Hello World from TypePHP Docker AOT!
```

---

## 踩坑与常见问题排查

### 报错：`/usr/bin/ld: cannot open output file app: Is a directory`

#### 错误现象
```text
Compiling [████████████████████████████████████████] 100% (6/6)
Successfully compiled 6 files
g++ '@./app.rsp' -o 'app' -L'/opt/typephp/vendor/swoole/phpx/lib' -L'/usr/lib' -lphpx -lphp ...
/usr/bin/ld: cannot open output file app: Is a directory
collect2: error: ld returned 1 exit status
```

#### 原因分析
很多现代 PHP 项目根目录下都会有一个存放业务代码的 **`app/` 目录**。  
当 `project.yml` 中配置为 `name: app` 时，链接器（ld）默认尝试将可执行文件输出为 `./app`，由于同名目录已经存在，系统无法用普通文件覆盖目录，因此报错 `Is a directory`。

#### 解决办法
在项目根目录的 `project.yml` 中，显式添加一行 **`bin:`** 指定输出文件名，避开同名目录即可：

```yaml
name: app
bin: app.bin  # 👈 显式指定二进制文件名（如 app.bin 或 server）

sources:
  - app/index.php
```

保存后重新执行编译即可顺利生成！

---

## 常用进阶用法

### 1. 明确指定编译某个脚本
如果目录下有多个脚本，可以直接指定要编译的文件名：
```powershell
docker run --rm -v "${PWD}:/app" tinywan/typephp-linux-x64:v0.7.0 your_script.php
```

### 2. 编译为零依赖全静态单文件（~6MB）
如果希望产出的单二进制文件不依赖宿主机的 glibc，可在任何 Linux 发行版甚至空白镜像（`FROM scratch`）上直接运行，换用全静态镜像即可：
```powershell
docker run --rm -v "${PWD}:/app" tinywan/typephp-linux-x64-static:v0.7.0
```

---

## 官方镜像仓库

- **动态编译镜像（Ubuntu / glibc）**：`tinywan/typephp-linux-x64:v0.7.0`
- **全静态单文件镜像（Alpine / musl）**：`tinywan/typephp-linux-x64-static:v0.7.0`
- **GitHub 开源仓库**：[Tinywan/tinywan-typephp-webman](https://github.com/Tinywan/tinywan-typephp-webman)

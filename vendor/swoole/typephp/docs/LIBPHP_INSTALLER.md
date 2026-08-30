# 自动构建 libphp.so

TypePHP 的可执行文件和共享库模式需要 PHP Embed SAPI 提供的 `libphp.so`。许多 Linux 发行版的 PHP 包只包含 CLI 或 FPM，因此 Composer 安装的 `tpc.php` 在找不到 `libphp.so` 时会询问是否自动构建一份私有 PHP。

该安装器面向由 PHP 解释器启动的 `tpc.php`。自举二进制 `tpc` 在进入 `main()` 前就必须由系统动态链接器加载 `libphp.so` 和 `libphpx.so`，不能依靠自身补装缺失库。

该功能只在 Linux 和交互式终端中启用。扩展模式（`-m ext`）不需要 `libphp.so`，不会触发安装器；CI 等非交互环境也不会自动下载、安装软件包或执行 `sudo`。

## 使用流程

正常执行编译命令即可：

```bash
vendor/bin/tpc.php project.yml
```

缺少 `libphp.so` 时，安装器会依次询问：

1. 是否自动构建 PHP Embed 库；
2. PHP 版本，默认与当前运行 `tpc.php` 的 `PHP_VERSION` 完全一致，也可手动输入其他 PHP 8.4.x/8.5.x 稳定版本；
3. 安装目录，默认为 `~/.typephp`；
4. 是否通过检测到的 `apt-get`、`dnf` 或 `yum` 安装缺失的开发包。

安装器读取当前 `php-config --configure-options`，保留当前 PHP 的扩展配置，替换安装路径并加入 `--enable-embed=shared`。PHP 源码只从 PHP.net 下载，并使用官方发布信息中的 SHA-256 校验。

编译完成后主要文件如下：

```text
~/.typephp/bin/php
~/.typephp/bin/php-config
~/.typephp/lib/libphp.so
~/.typephp/lib/php.ini
~/.typephp/lib/loaded-extensions.txt
```

当前 ini 主文件和扫描目录中的配置会被合并。使用相同 PHP 主次版本时，当前 ini 中加载的共享扩展会复制到新扩展目录；跨主次版本时不会复制二进制扩展，不可用的扩展配置会被注释，避免生成的 PHP 无法启动。

安装成功后，当前 `tpc.php` 进程会自动将新目录作为 `PHP_HOME` 并继续原来的编译任务。后续也可以显式指定：

```bash
export PHP_HOME="$HOME/.typephp"
vendor/bin/tpc.php project.yml
```

再次选择同一目录和版本时，安装器会询问是否直接复用已有的 `libphp.so`，不会重复执行完整构建。

## 非交互环境

安装器不会在 CI 中自动确认权限操作。请提前准备 `libphp.so`，然后设置：

```bash
PHP_HOME=/path/to/php vendor/bin/tpc.php project.yml
```

## 自动构建 libphpx.so

Linux 下完成 PHP Embed 检查后，构建流水线还会检查 PHPX 根目录中的 `lib/libphpx.so`。PHPX 根目录按以下顺序解析：

1. `PHPX_HOME`；
2. Composer `InstalledVersions` 中的 `swoole/phpx` 安装路径；
3. TypePHP 源码仓库内的 `vendor/swoole/phpx`。

缺少共享库时，交互式终端会询问是否构建。PHPX 本身不依赖 `libphp.so`；它依赖 PHP 头文件和 `php-config`。`LibPhpxInstaller` 使用当前选定的 PHP 前缀，同时设置 `PHP_HOME`、将该前缀的 `bin` 放到 `PATH` 首位，并向 PHPX CMake 传递 `-Dphp_dir=<PHP prefix>`，确保 PHPX 与项目运行时使用的 PHP ABI 一致。

工具链检测在本地库检查之后执行。用户确认自动构建后，安装器通过 `apt-get`、`dnf` 或 `yum` 检查并可安装 GCC/G++、make、CMake、pkg-config 以及 PHP configure 所需依赖；因此 Composer 环境无需预先安装完整 C/C++ 工具链。

构建固定使用 Release、关闭 PHPX tests，并只构建 `phpx` target；并行度最多为 8。输出必须为 `<PHPX>/lib/libphpx.so`，否则安装器报错。非交互环境只提示缺失，不执行 CMake。

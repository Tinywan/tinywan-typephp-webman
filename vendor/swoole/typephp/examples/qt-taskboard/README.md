# TypePHP Taskboard (Qt Widgets)

This desktop project management demo uses PHP for task validation, status
transitions, search, filtering, counts and PDO_SQLITE persistence. C++ creates
Qt widgets, renders PHP-provided data and sends raw UI events back to PHP.

The first launch creates five example tasks. Data is saved to
`%APPDATA%\TypePHP\taskboard.sqlite` on Windows or
`$HOME/TypePHP/taskboard.sqlite` on Unix. Set `TYPEPHP_TASKBOARD_DATA` to override
the file path for demos or tests.

## Linux

Install Qt 6 Widgets, build PHPX, enable the `pdo_sqlite` PHP extension and set
`PHPX_HOME` and `PHP_HOME`. From the compiler repository root:

```bash
php bin/tpc.php examples/qt-taskboard/project.yml --job 2 --no-progress
./typephp_taskboard
```

## macOS arm64

Tested on Apple Silicon with PHP 8.4.20 ZTS, built-in PDO_SQLITE and Homebrew
qtbase 6.11.2. Install Xcode, Qt Widgets and build PHPX against the same PHP:

```bash
brew install qtbase
export PHP_HOME="$HOME/.phpbrew/php/php-8.4.20-zts"
export PHPX_HOME="$HOME/workspace/phpx"
"$PHP_HOME/bin/php" -n -m | grep -E '^(PDO|pdo_sqlite)$'
"$PHP_HOME/bin/php" -n bin/tpc.php examples/qt-taskboard/project.macos.yml --job 2 --no-progress
./typephp_taskboard
```

Run these commands from the compiler repository root. `project.macos.yml` uses
the Apple Silicon Homebrew framework path `/opt/homebrew/lib`; adjust it for
another Qt installation. Set `TYPEPHP_TASKBOARD_SCREENSHOT` to a PNG path to
render the window once, save a screenshot and exit.

macOS launches desktop applications from `.app` bundles. Package the built
binary, Qt frameworks and plugins, PHP/PHPX libraries and their linked
dependencies, then launch it through Finder or LaunchServices:

```bash
sh examples/qt-taskboard/package-macos-app.sh
open "examples/qt-taskboard/dist/TypePHP Taskboard.app"
```

`--no-console` only selects the Windows GUI subsystem; it is not needed for
the macOS bundle. The packaging script applies an ad-hoc signature for local
testing. Distribution to other Macs requires testing on a clean machine and
appropriate signing and notarization.

## Windows

Use a matching x64 MSVC 2022 PHP SDK, PHPX and the Qt 6.8.3 MSVC 2022 x64
Widgets SDK. Replace the Qt root paths in `project.windows.yml` with your
installation path. The `/Zc:__cplusplus` and `/permissive-` flags are required
by this Qt SDK. In a VS x64 Native Tools shell, from the compiler repository root:

```bat
set "PHP_HOME=D:\workspace\php-8.4.23"
set "PHPX_HOME=D:\workspace\phpx"
set "PATH=D:\workspace\qt\6.8.3\msvc2022_64\bin;%PHPX_HOME%\lib;%PHP_HOME%;%PATH%"
"%PHP_HOME%\php.exe" -n bin\tpc.php examples\qt-taskboard\project.windows.yml --job 2 --no-progress
```

The embedded PHP runtime needs PDO_SQLITE. Create a separate
`examples\qt-taskboard\runtime.ini`:

```ini
extension_dir=D:\workspace\php-8.4.23\ext
extension=php_pdo_sqlite.dll
```

Then run from the compiler repository root:

```bat
set "PHPRC=D:\workspace\compiler\examples\qt-taskboard\runtime.ini"
set "PHP_INI_SCAN_DIR="
typephp_taskboard.exe
```

Set `TYPEPHP_TASKBOARD_SCREENSHOT` to a `.png` path to render the window once,
save a screenshot and exit. After validating console output, pass
`--no-console` for a GUI-subsystem build. Run `windeployqt` to deploy Qt
libraries and plugins; PHP/PHPX and other non-Qt DLLs must be deployed
separately.

## Domain test

Run with a PHP interpreter that has `pdo_sqlite` enabled:

```bash
php examples/qt-taskboard/tests/task_store_test.php
```

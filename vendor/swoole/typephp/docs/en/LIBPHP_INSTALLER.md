# Automatically building libphp.so

TypePHP's executable and shared-library modes require the `libphp.so` provided by the PHP Embed SAPI. Many Linux distributions' PHP packages only include CLI or FPM, so the Composer-installed `tpc.php` asks whether to automatically build a private PHP when `libphp.so` is not found.

The installer targets `tpc.php` launched by the PHP interpreter. The bootstrap binary `tpc` must have `libphp.so` and `libphpx.so` loaded by the system dynamic linker before entering `main()`, so it cannot install missing libraries by itself.

This feature is only enabled on Linux and in interactive terminals. Extension mode (`-m ext`) does not need `libphp.so` and does not trigger the installer; non-interactive environments such as CI also do not automatically download or install packages, or execute `sudo`.

## Usage flow

Simply run the normal compile command:

```bash
vendor/bin/tpc.php project.yml
```

When `libphp.so` is missing, the installer asks in sequence:

1. whether to automatically build the PHP Embed library;
2. the PHP version, defaulting to exactly the `PHP_VERSION` of the currently running `tpc.php`, with the option to manually enter another PHP 8.4.x/8.5.x stable version;
3. the install directory, defaulting to `~/.typephp`;
4. whether to install missing development packages via the detected `apt-get`, `dnf`, or `yum`.

The installer reads the current `php-config --configure-options`, keeps the current PHP's extension configuration, replaces the install path, and adds `--enable-embed=shared`. PHP source is downloaded only from PHP.net, and verified using the SHA-256 from the official release information.

After compilation, the main files are as follows:

```text
~/.typephp/bin/php
~/.typephp/bin/php-config
~/.typephp/lib/libphp.so
~/.typephp/lib/php.ini
~/.typephp/lib/loaded-extensions.txt
```

The current main ini file and the configuration in the scan directory are merged. When the same PHP major/minor version is used, the shared extensions loaded in the current ini are copied to the new extension directory; across major/minor versions, binary extensions are not copied, and unusable extension configuration is commented out to avoid a generated PHP that cannot start.

After a successful installation, the current `tpc.php` process automatically uses the new directory as `PHP_HOME` and continues the original compile task. It can also be specified explicitly later:

```bash
export PHP_HOME="$HOME/.typephp"
vendor/bin/tpc.php project.yml
```

When the same directory and version are chosen again, the installer asks whether to directly reuse the existing `libphp.so` and does not repeat the full build.

## Non-interactive environments

The installer does not automatically confirm privileged operations in CI. Prepare `libphp.so` in advance, then set:

```bash
PHP_HOME=/path/to/php vendor/bin/tpc.php project.yml
```

## Automatically building libphpx.so

After the PHP Embed check completes on Linux, the build pipeline also checks `lib/libphpx.so` in the PHPX root directory. The PHPX root directory is resolved in the following order:

1. `PHPX_HOME`;
2. the `swoole/phpx` install path in Composer `InstalledVersions`;
3. `vendor/swoole/phpx` within the TypePHP source repository.

When the shared library is missing, an interactive terminal asks whether to build. PHPX itself does not depend on `libphp.so`; it depends on PHP headers and `php-config`. `LibPhpxInstaller` uses the currently selected PHP prefix, sets `PHP_HOME`, puts that prefix's `bin` at the front of `PATH`, and passes `-Dphp_dir=<PHP prefix>` to the PHPX CMake, ensuring PHPX matches the PHP ABI used by the project runtime.

Toolchain detection runs after the local library checks. After the user confirms the automatic build, the installer checks and can install GCC/G++, make, CMake, pkg-config, and the dependencies required by PHP configure via `apt-get`, `dnf`, or `yum`; therefore a Composer environment does not need a full pre-installed C/C++ toolchain.

The build always uses Release, disables PHPX tests, and builds only the `phpx` target; parallelism is capped at 8. The output must be `<PHPX>/lib/libphpx.so`, otherwise the installer reports an error. Non-interactive environments only report the missing library and do not run CMake.

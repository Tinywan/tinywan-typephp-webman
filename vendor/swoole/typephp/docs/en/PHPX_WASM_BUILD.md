# Rebuilding the PHPX WASM Static Library

This document is aimed at TypePHP/PHPX developers and explains how to recompile and install the
PHPX static library for `wasm32-wasip2`. Ordinary TypePHP users do not need to perform these steps; release packages should provide the complete
WASI SDK directly.

## Directory Convention

This document assumes the source layout is as follows:

```text
/home/swoole/workspace/aot/
├── compiler/
└── phpx/
```

It is recommended to set the PHPX root directory first:

```shell
export PHPX_HOME=/home/swoole/workspace/aot/phpx
```

The installation prefix is fixed to:

```text
$PHPX_HOME/wasm/wasm32-wasip2
```

This directory is both the input of the existing PHP/WASI SDK and the installation location of the PHPX build results:

```text
wasm/wasm32-wasip2/
├── include/php/             PHP/WASI headers
├── include/phpx/            PHPX/TypePHP runtime headers
├── lib/libphp.a
├── lib/libphpx.a
├── lib/libgmp.a
├── lib/libgmpxx.a
├── lib/libmpfr.a
├── lib/libmpdec.a
├── lib/libmpdec++.a
└── .typephp-wasi-sdk-abi
```

Do not copy host-platform `libphpx.so`, `phpx.dll`, or `.a` files here.
WASM static libraries contain the target ABI and cannot be used across WASI, Linux, macOS, or Windows.

## Toolchain Preparation

PHPX WASM currently supports only WASI 0.2 Preview 2. Add the WASI SDK to `PATH`:

```shell
export PATH=/opt/wasi-sdk-33.0/bin:$PATH
```

`PATH` only lets the shell and build tools find the WASI SDK programs; it does not make CMake automatically select the
WASI target. When configuring the build directory for the first time, you must still pass
`-DCMAKE_TOOLCHAIN_FILE=.../wasi-sdk-p2.cmake`. If you omit it, CMake will choose the host platform's
`/usr/bin/cc` and `/usr/bin/c++`, and PHPX's target check will immediately reject that configuration.

Confirm the necessary tools:

```shell
command -v wasm32-wasip2-clang
command -v wasm32-wasip2-clang++
command -v llvm-ar
command -v llvm-ranlib
command -v llvm-nm
command -v cmake
command -v ninja
```

Confirm the compile target:

```shell
wasm32-wasip2-clang++ --print-target-triple
```

It must output:

```text
wasm32-unknown-wasip2
```

The installation prefix must already contain PHP/WASI headers and `libphp.a` matching the current PHPX:

```shell
test -f "$PHPX_HOME/wasm/wasm32-wasip2/include/php/main/php.h"
test -f "$PHPX_HOME/wasm/wasm32-wasip2/lib/libphp.a"
```

## Daily Development: Rebuild PHPX Directly with CMake

When a PHPX `.cc` or header file changes, incrementally rebuild directly using `phpx/wasm/CMakeLists.txt`.
This is the recommended flow for daily development; it does not re-download or recompile PHP, GMP, or MPFR, nor does it regenerate
`libphp.a`.

First, locate the CMake toolchain from the current WASI compiler to avoid depending on a hardcoded SDK version path:

```shell
WASI_RESOURCE_DIR="$(wasm32-wasip2-clang++ --print-resource-dir)"
WASI_SDK_ROOT="$(cd "$WASI_RESOURCE_DIR/../../.." && pwd)"
WASI_CMAKE_TOOLCHAIN="$WASI_SDK_ROOT/share/cmake/wasi-sdk-p2.cmake"
test -f "$WASI_CMAKE_TOOLCHAIN"
```

### Using Ninja (Recommended)

First configure a persistent build directory:

```shell
cmake \
    -S "$PHPX_HOME/wasm" \
    -B "$PHPX_HOME/build/wasm32-wasip2" \
    -G Ninja \
    -DCMAKE_TOOLCHAIN_FILE="$WASI_CMAKE_TOOLCHAIN" \
    -DCMAKE_BUILD_TYPE=Release \
    -DPHPX_WASI_SDK_DIR="$PHPX_HOME/wasm/wasm32-wasip2" \
    -DCMAKE_INSTALL_PREFIX="$PHPX_HOME/wasm/wasm32-wasip2"
```

The toolchain takes effect when CMake executes `project()`, so it can only be set during the first configuration of a build directory.
If the directory was configured without a toolchain before and has already cached the host compiler, do not append arguments onto the existing cache;
use a new build directory instead, for example:

```shell
cmake \
    -S "$PHPX_HOME/wasm" \
    -B "$PHPX_HOME/build/wasm32-wasip2-wasi" \
    -G Ninja \
    -DCMAKE_TOOLCHAIN_FILE="$WASI_CMAKE_TOOLCHAIN" \
    -DCMAKE_BUILD_TYPE=Release \
    -DPHPX_WASI_SDK_DIR="$PHPX_HOME/wasm/wasm32-wasip2" \
    -DCMAKE_INSTALL_PREFIX="$PHPX_HOME/wasm/wasm32-wasip2"
```

Subsequent build/install commands should also use this new directory.

Compile and install:

```shell
cmake --build "$PHPX_HOME/build/wasm32-wasip2" --parallel 16
cmake --install "$PHPX_HOME/build/wasm32-wasip2"
```

When the PHPX source changes again later, you only need to run:

```shell
cmake --build "$PHPX_HOME/build/wasm32-wasip2" --parallel 16
cmake --install "$PHPX_HOME/build/wasm32-wasip2"
```

CMake/Ninja only recompiles the changed source files and then updates `libphpx.a` in the installation directory.

### Using Make

`make` can be used, but the `Unix Makefiles` generator must be selected during the first configuration, using a different build
directory; you cannot switch generators in a directory already configured by Ninja:

```shell
cmake \
    -S "$PHPX_HOME/wasm" \
    -B "$PHPX_HOME/build/wasm32-wasip2-make" \
    -G "Unix Makefiles" \
    -DCMAKE_TOOLCHAIN_FILE="$WASI_CMAKE_TOOLCHAIN" \
    -DCMAKE_BUILD_TYPE=Release \
    -DPHPX_WASI_SDK_DIR="$PHPX_HOME/wasm/wasm32-wasip2" \
    -DCMAKE_INSTALL_PREFIX="$PHPX_HOME/wasm/wasm32-wasip2"

make -C "$PHPX_HOME/build/wasm32-wasip2-make" -j16
make -C "$PHPX_HOME/build/wasm32-wasip2-make" install
```

After later modifying PHPX code, just repeat the two `make` commands. You can also use the generator-independent form:

```shell
cmake --build "$PHPX_HOME/build/wasm32-wasip2-make" --parallel 16
cmake --install "$PHPX_HOME/build/wasm32-wasip2-make"
```

The artifacts of Ninja and Make are the same; Ninja is generally faster in dependency scanning and incremental builds, so internal development defaults
to Ninja.

This flow updates:

- `lib/libphpx.a`
- `lib/libmpdec.a` and `lib/libmpdec++.a` (recompiled only when the related source changes)
- PHPX public headers under `include/phpx/`
- `.typephp-wasi-runtime-abi`

It does not update `libphp.a`, GMP, or MPFR, nor does it rewrite the full SDK's
`.typephp-wasi-sdk-abi`. Therefore, this flow should be run on an already fully installed SDK.

### Force Recompiling PHPX

When you suspect that old objects or the CMake cache are no longer trustworthy, prefer using a new, explicit build directory:

```shell
cmake \
    -S "$PHPX_HOME/wasm" \
    -B "$PHPX_HOME/build/wasm32-wasip2-clean" \
    -G Ninja \
    -DCMAKE_TOOLCHAIN_FILE="$WASI_CMAKE_TOOLCHAIN" \
    -DCMAKE_BUILD_TYPE=Release \
    -DPHPX_WASI_SDK_DIR="$PHPX_HOME/wasm/wasm32-wasip2" \
    -DCMAKE_INSTALL_PREFIX="$PHPX_HOME/wasm/wasm32-wasip2"

cmake --build "$PHPX_HOME/build/wasm32-wasip2-clean" --parallel 16
cmake --install "$PHPX_HOME/build/wasm32-wasip2-clean"
```

This does not delete `libphp.a` and the dependency libraries in the installation directory, nor does it mix in old CMake configuration.

## First Build or Rebuilding PHPX Numeric Dependencies

Use PHPX's unified build entry in the following cases:

- Setting up the PHPX WASI installation directory for the first time;
- GMP or MPFR version, patch, or compile parameter changes;
- Changes to PHPX vendored mpdecimal or its WASI configuration;
- The need to check and install all PHPX WASI headers and static libraries at once.

```shell
cd "$PHPX_HOME"

./wasm/build.sh \
    --prefix "$PHPX_HOME/wasm/wasm32-wasip2" \
    --build-dir "$PHPX_HOME/build/wasm32-wasip2-sdk" \
    --jobs 16
```

Explicitly use `$PHPX_HOME/build/` to avoid the default `/tmp` build directory being lost after a reboot. The downloaded GMP and
MPFR source and build cache are retained and can be reused in subsequent builds.

This entry builds or installs:

- `libphpx.a`
- `libgmp.a`, `libgmpxx.a`
- `libmpfr.a`
- `libmpdec.a`, `libmpdec++.a`
- The corresponding headers and the PHPX runtime ABI marker

It requires PHP/WASI headers to already exist in the installation prefix; it does not build `libphp.a`.

## PHP ABI Changes: Rebuilding the Full SDK

If the PHP source, extension set, PHP configuration, Zend ABI, or PHP installed headers change, you must rebuild the full SDK from the
TypePHP compiler repository, and you cannot replace only `libphpx.a`:

```shell
cd /home/swoole/workspace/aot/compiler

./wasm/build-sdk.sh \
    --prefix "$PHPX_HOME/wasm/wasm32-wasip2" \
    --php-source "$PWD/projects/php-8.5.9" \
    --phpx-source "$PHPX_HOME" \
    --build-dir "$PWD/build/wasm-sdk" \
    --jobs 16
```

The full build installs the PHP and PHPX parts in sequence, and writes the following after all artifacts are verified:

```text
.typephp-wasi-sdk-abi
```

Do not forge this marker by hand. The existence of the marker only means the build flow declares ABI compatibility; it cannot fix actually mixed
old headers or static libraries.

## Artifact Verification

After installation completes, check the key files:

```shell
WASI_PREFIX="$PHPX_HOME/wasm/wasm32-wasip2"

test -s "$WASI_PREFIX/lib/libphpx.a"
test -s "$WASI_PREFIX/lib/libphp.a"
test -f "$WASI_PREFIX/include/phpx/phpx.h"
test -f "$WASI_PREFIX/include/phpx/phpx_helper.h"
test -f "$WASI_PREFIX/include/phpx/typephp_helper.h"

llvm-ar t "$WASI_PREFIX/lib/libphpx.a" | head
cat "$WASI_PREFIX/.typephp-wasi-runtime-abi"
cat "$WASI_PREFIX/.typephp-wasi-sdk-abi"
```

The current markers should be:

```text
typephp-wasip2-phpx-abi-v1
typephp-wasip2-sdk-abi-v4
```

The marker versions will be upgraded as the ABI design evolves; if the expected values in the code have changed, follow the current build scripts
rather than writing old values back just to pass detection.

## TypePHP Regression Verification

First verify the Wasmtime component:

```shell
cd /home/swoole/workspace/aot/compiler

PHPX_HOME="$PHPX_HOME" \
./run-tests.php --wasm --compiler ./bin/tpc.php tests/wasm/
```

Then verify that Wasmtime and Chrome output are consistent, and cover parallel build/output directory isolation:

```shell
PHPX_HOME="$PHPX_HOME" \
./run-tests.php -j 4 --target wasm-all --compiler ./bin/tpc.php tests/wasm/
```

Browser tests also require `jco`, Node.js, and Chrome to be in `PATH`. `wasm-all` runs each case in
Wasmtime and Chrome separately, and compares the output of both sides.

Finally build the browser example:

```shell
cd examples/wasm-hello
PHPX_HOME="$PHPX_HOME" ../../bin/tpc.php project.yml
npm run build
```

## Common Errors

### `PersistentCacheSlot` or PHPX helpers are undefined

The generated code uses a new PHPX header/API, but `include/phpx/` or
`lib/libphpx.a` in the installation prefix is still an old version. Run the "Daily development: rebuild PHPX only" flow, and make sure the configuration and installation
use the same `PHPX_WASI_SDK_DIR`/`CMAKE_INSTALL_PREFIX`.

### `TypePHP WASI SDK is missing or ABI-incompatible`

Check whether `PHPX_HOME` points to the actual PHPX root directory, and whether the full SDK marker, PHP/PHPX headers,
and static libraries come from the same compatible build. Run the full SDK rebuild when the PHP ABI has changed.

### CMake detects the host compiler

You must pass the WASI SDK's `wasi-sdk-p2.cmake`. Do not use the host
`CMakeLists.txt` in the PHPX root directory to build WASM directly. Adding the WASI SDK to `PATH` is not equivalent to loading the CMake
toolchain. If `CMakeCache.txt` has already recorded `/usr/bin/cc` or `/usr/bin/c++`, use a
new build directory to reconfigure.

### TypePHP still links the old implementation after modifying PHPX

Confirm that `PHPX_HOME` takes priority over the Composer directory, and check the actual artifact time:

```shell
stat "$PHPX_HOME/wasm/wasm32-wasip2/lib/libphpx.a"
```

TypePHP should read both headers and static libraries from the same `$PHPX_HOME/wasm/wasm32-wasip2`.

# 重建 PHPX WASM 静态库

本文面向 TypePHP/PHPX 开发者，说明如何为 `wasm32-wasip2` 重新编译并安装
PHPX 静态库。普通 TypePHP 用户不需要执行这些步骤；发行包应直接提供完整的
WASI SDK。

## 目录约定

本文假设源码布局如下：

```text
/home/swoole/workspace/aot/
├── compiler/
└── phpx/
```

建议先设置 PHPX 根目录：

```shell
export PHPX_HOME=/home/swoole/workspace/aot/phpx
```

安装前缀固定为：

```text
$PHPX_HOME/wasm/wasm32-wasip2
```

该目录既是已有 PHP/WASI SDK 的输入，也是 PHPX 构建结果的安装位置：

```text
wasm/wasm32-wasip2/
├── include/php/             PHP/WASI 头文件
├── include/phpx/            PHPX/TypePHP 运行时头文件
├── lib/libphp.a
├── lib/libphpx.a
├── lib/libgmp.a
├── lib/libgmpxx.a
├── lib/libmpfr.a
├── lib/libmpdec.a
├── lib/libmpdec++.a
└── .typephp-wasi-sdk-abi
```

不要把 host 平台的 `libphpx.so`、`phpx.dll` 或 `.a` 文件复制到这里。
WASM 静态库包含目标 ABI，不能跨 WASI、Linux、macOS 或 Windows 使用。

## 工具链准备

PHPX WASM 当前只支持 WASI 0.2 Preview 2。将 WASI SDK 加入 `PATH`：

```shell
export PATH=/opt/wasi-sdk-33.0/bin:$PATH
```

`PATH` 只负责让 shell 和构建工具找到 WASI SDK 程序，并不会让 CMake 自动选择
WASI target。第一次配置构建目录时仍然必须传入
`-DCMAKE_TOOLCHAIN_FILE=.../wasi-sdk-p2.cmake`。如果省略它，CMake 会选择 host
平台的 `/usr/bin/cc` 和 `/usr/bin/c++`，PHPX 的目标检查会立即拒绝该配置。

确认必要工具：

```shell
command -v wasm32-wasip2-clang
command -v wasm32-wasip2-clang++
command -v llvm-ar
command -v llvm-ranlib
command -v llvm-nm
command -v cmake
command -v ninja
```

确认编译目标：

```shell
wasm32-wasip2-clang++ --print-target-triple
```

必须输出：

```text
wasm32-unknown-wasip2
```

安装前缀必须已经包含与当前 PHPX 匹配的 PHP/WASI 头文件和 `libphp.a`：

```shell
test -f "$PHPX_HOME/wasm/wasm32-wasip2/include/php/main/php.h"
test -f "$PHPX_HOME/wasm/wasm32-wasip2/lib/libphp.a"
```

## 日常开发：直接使用 CMake 重建 PHPX

PHPX 的 `.cc` 或头文件发生变化时，直接使用 `phpx/wasm/CMakeLists.txt` 增量重建。
这是日常开发的推荐流程，不会重新下载或编译 PHP、GMP 和 MPFR，也不会重新生成
`libphp.a`。

首先从当前 WASI 编译器定位 CMake toolchain，避免依赖硬编码的 SDK 版本路径：

```shell
WASI_RESOURCE_DIR="$(wasm32-wasip2-clang++ --print-resource-dir)"
WASI_SDK_ROOT="$(cd "$WASI_RESOURCE_DIR/../../.." && pwd)"
WASI_CMAKE_TOOLCHAIN="$WASI_SDK_ROOT/share/cmake/wasi-sdk-p2.cmake"
test -f "$WASI_CMAKE_TOOLCHAIN"
```

### 使用 Ninja（推荐）

首次配置持久化构建目录：

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

toolchain 在 CMake 执行 `project()` 时生效，所以只能在构建目录的第一次配置时设置。
如果该目录此前未传 toolchain、已经缓存了 host 编译器，不要直接在原缓存上补参数；
改用一个新的构建目录，例如：

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

后续的 build/install 命令也应使用这个新目录。

编译并安装：

```shell
cmake --build "$PHPX_HOME/build/wasm32-wasip2" --parallel 16
cmake --install "$PHPX_HOME/build/wasm32-wasip2"
```

以后 PHPX 源码再次变化时，只需要执行：

```shell
cmake --build "$PHPX_HOME/build/wasm32-wasip2" --parallel 16
cmake --install "$PHPX_HOME/build/wasm32-wasip2"
```

CMake/Ninja 只会重新编译发生变化的源文件，然后更新安装目录中的 `libphpx.a`。

### 使用 Make

可以使用 `make`，但首次配置时必须选择 `Unix Makefiles` 生成器，并使用另一个构建
目录，不能在已经由 Ninja 配置的目录中切换生成器：

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

后续修改 PHPX 代码后，只需重复两条 `make` 命令。也可以使用生成器无关的形式：

```shell
cmake --build "$PHPX_HOME/build/wasm32-wasip2-make" --parallel 16
cmake --install "$PHPX_HOME/build/wasm32-wasip2-make"
```

Ninja 与 Make 的产物相同；Ninja 通常依赖扫描和增量构建更快，因此内部开发默认
使用 Ninja。

此流程会更新：

- `lib/libphpx.a`
- `lib/libmpdec.a` 和 `lib/libmpdec++.a`（仅相关源码变化时重编）
- `include/phpx/` 下的 PHPX 公共头文件
- `.typephp-wasi-runtime-abi`

它不会更新 `libphp.a`、GMP、MPFR，也不会重写完整 SDK 的
`.typephp-wasi-sdk-abi`。因此该流程应在一个已经完整安装的 SDK 上执行。

### 强制重新编译 PHPX

怀疑旧对象或 CMake 缓存不再可信时，优先使用一个新的、明确的构建目录：

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

这样不会删除安装目录中的 `libphp.a` 和依赖库，也不会混用旧的 CMake 配置。

## 首次构建或重建 PHPX 数值依赖

以下情况使用 PHPX 的统一构建入口：

- 首次建立 PHPX WASI 安装目录；
- GMP 或 MPFR 版本、补丁、编译参数发生变化；
- PHPX vendored mpdecimal 或其 WASI 配置发生变化；
- 需要同时检查并安装 PHPX 所有 WASI 头文件和静态库。

```shell
cd "$PHPX_HOME"

./wasm/build.sh \
    --prefix "$PHPX_HOME/wasm/wasm32-wasip2" \
    --build-dir "$PHPX_HOME/build/wasm32-wasip2-sdk" \
    --jobs 16
```

显式使用 `$PHPX_HOME/build/`，避免默认 `/tmp` 构建目录在重启后丢失。下载的 GMP、
MPFR 源码与构建缓存会保留，可以在后续构建中复用。

该入口会构建或安装：

- `libphpx.a`
- `libgmp.a`、`libgmpxx.a`
- `libmpfr.a`
- `libmpdec.a`、`libmpdec++.a`
- 对应头文件和 PHPX runtime ABI marker

它要求安装前缀中已经存在 PHP/WASI 头文件；它不会构建 `libphp.a`。

## PHP ABI 变化：重建完整 SDK

如果 PHP 源码、扩展集合、PHP 配置、Zend ABI 或 PHP 安装头文件发生变化，必须从
TypePHP 编译器仓库重建完整 SDK，不能只替换 `libphpx.a`：

```shell
cd /home/swoole/workspace/aot/compiler

./wasm/build-sdk.sh \
    --prefix "$PHPX_HOME/wasm/wasm32-wasip2" \
    --php-source "$PWD/projects/php-8.5.9" \
    --phpx-source "$PHPX_HOME" \
    --build-dir "$PWD/build/wasm-sdk" \
    --jobs 16
```

完整构建依次安装 PHP 与 PHPX 部分，并在全部产物验证成功后写入：

```text
.typephp-wasi-sdk-abi
```

不要手工伪造该 marker。marker 存在只表示构建流程声明 ABI 匹配，不能修复实际混用
的旧头文件或静态库。

## 产物检查

完成安装后检查关键文件：

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

当前 marker 应分别为：

```text
typephp-wasip2-phpx-abi-v1
typephp-wasip2-sdk-abi-v4
```

marker 版本将随 ABI 设计升级；如果代码中的预期值已经变化，应以当前构建脚本为准，
不能为了通过检测而回写旧值。

## TypePHP 回归验证

先验证 Wasmtime component：

```shell
cd /home/swoole/workspace/aot/compiler

PHPX_HOME="$PHPX_HOME" \
./run-tests.php --wasm --compiler ./bin/tpc.php tests/wasm/
```

再验证 Wasmtime 与 Chrome 输出一致，并覆盖并行 build/output 目录隔离：

```shell
PHPX_HOME="$PHPX_HOME" \
./run-tests.php -j 4 --target wasm-all --compiler ./bin/tpc.php tests/wasm/
```

browser 测试还要求 `jco`、Node.js 和 Chrome 位于 `PATH`。`wasm-all` 会分别在
Wasmtime 和 Chrome 中执行每个用例，并比较两端输出。

最后构建浏览器示例：

```shell
cd examples/wasm-hello
PHPX_HOME="$PHPX_HOME" ../../bin/tpc.php project.yml
npm run build
```

## 常见错误

### `PersistentCacheSlot` 或 PHPX helper 未定义

生成代码使用了新 PHPX 头文件/API，但安装前缀中的 `include/phpx/` 或
`lib/libphpx.a` 仍是旧版本。执行“日常开发：仅重建 PHPX”流程，并确保配置和安装
使用同一个 `PHPX_WASI_SDK_DIR`/`CMAKE_INSTALL_PREFIX`。

### `TypePHP WASI SDK is missing or ABI-incompatible`

检查 `PHPX_HOME` 是否指向实际 PHPX 根目录，以及完整 SDK marker、PHP/PHPX 头文件
和静态库是否来自同一次兼容构建。PHP ABI 已变化时执行完整 SDK 重建。

### CMake 检测到 host 编译器

必须传入 WASI SDK 的 `wasi-sdk-p2.cmake`。不要直接用 PHPX 根目录的 host
`CMakeLists.txt` 构建 WASM。将 WASI SDK 加入 `PATH` 本身并不等价于加载 CMake
toolchain。如果 `CMakeCache.txt` 已记录 `/usr/bin/cc` 或 `/usr/bin/c++`，使用一个
新的构建目录重新配置。

### 修改 PHPX 后 TypePHP 仍链接旧实现

确认 `PHPX_HOME` 的优先级高于 Composer 目录，并检查实际产物时间：

```shell
stat "$PHPX_HOME/wasm/wasm32-wasip2/lib/libphpx.a"
```

TypePHP 应从同一个 `$PHPX_HOME/wasm/wasm32-wasip2` 同时读取头文件与静态库。

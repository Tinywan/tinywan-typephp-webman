#!/bin/sh
set -ex

apk add --no-cache musl-dev linux-headers php85-dev php85-embed gmp-dev gmp-static mpfr-dev build-base g++ cmake binutils curl xz bison re2c libxml2-dev

SDK_DIR="/host/vendor/swoole/phpx/full-static/sdk"
mkdir -p "$SDK_DIR/lib/musl" "$SDK_DIR/include/php" "$SDK_DIR/include/phpx"

# 1. Copy musl crt files
cp -f /usr/lib/crt1.o /usr/lib/crti.o /usr/lib/crtn.o "$SDK_DIR/lib/musl/"

# 2. Build or provide libphp.a (Embed SAPI static library from PHP 8.5)
PHP_SRC_VER="8.5.0"
PHP_BUILD_DIR="/tmp/php-src"
mkdir -p "$PHP_BUILD_DIR"
cd "$PHP_BUILD_DIR"
echo "[INFO] Downloading PHP $PHP_SRC_VER source for static embed build..."
curl -sSL "https://www.php.net/distributions/php-${PHP_SRC_VER}.tar.xz" | tar -xJ --strip-components=1

echo "[INFO] Configuring PHP static embed..."
./configure \
  --prefix=/usr \
  --enable-embed=static \
  --disable-all \
  --enable-cli \
  --enable-session \
  --enable-filter \
  --enable-json \
  --enable-ctype \
  --enable-tokenizer \
  --enable-posix \
  --enable-pcntl \
  --enable-sockets \
  --with-gmp \
  --without-pear

echo "[INFO] Building static libphp.a..."
make -j$(nproc) libphp.la

LIBPHP_STATIC_SOURCE=""
if [ -f "libs/libphp.a" ]; then
  LIBPHP_STATIC_SOURCE="libs/libphp.a"
elif [ -f ".libs/libphp.a" ]; then
  LIBPHP_STATIC_SOURCE=".libs/libphp.a"
else
  LIBPHP_STATIC_SOURCE=$(find . -name "libphp.a" | head -n 1)
fi

echo "[INFO] Found built static libphp: $LIBPHP_STATIC_SOURCE"
ls -lh "$LIBPHP_STATIC_SOURCE"

echo "[INFO] Installing PHP headers from compiled source to SDK..."
make install-headers INSTALL_ROOT=/tmp/php-install
cp -r /tmp/php-install/usr/include/php/. "$SDK_DIR/include/php/"

echo "[INFO] Merging libphp.a with musl libc, libgmp, libgmpxx, libmpfr, libstdc++ into self-contained static archive..."
echo "[INFO] Using extract+repack approach to properly handle thin archives..."
MERGE_DIR="/tmp/merge_objects"
rm -rf "$MERGE_DIR"
mkdir -p "$MERGE_DIR"
rm -f "$SDK_DIR/lib/libphp.a"

# Extract each archive into its own subdirectory to avoid object-file name collisions
for lib in "$LIBPHP_STATIC_SOURCE" /usr/lib/libgmp.a /usr/lib/libgmpxx.a /usr/lib/libmpfr.a /usr/lib/libc.a /usr/lib/libstdc++.a; do
  if [ -f "$lib" ]; then
    libname=$(basename "$lib" .a)
    subdir="$MERGE_DIR/$libname"
    mkdir -p "$subdir"
    echo "  extracting: $lib"
    (cd "$subdir" && ar x "$lib") || true
  else
    echo "  [WARN] not found, skipping: $lib"
  fi
done

# Build merged fat archive from all extracted object files
echo "[INFO] Building merged fat archive..."
find "$MERGE_DIR" -name "*.o" | sort | xargs ar rcs "$SDK_DIR/lib/libphp.a"
ranlib "$SDK_DIR/lib/libphp.a"
ls -lh "$SDK_DIR/lib/libphp.a"

echo "[INFO] Checking required symbols in libphp.a..."
if ! nm "$SDK_DIR/lib/libphp.a" 2>/dev/null | grep -q '__gmp_version'; then
  echo "[WARN] __gmp_version not found in symbol table via plain nm (possibly LTO), checking archive contents..."
  ar t "$SDK_DIR/lib/libphp.a" | grep -i 'version' || true
fi

# 4. Build static libphpx.a
cmake -S /host/vendor/swoole/phpx/full-static -B /tmp/phpx-static-build \
  -DCMAKE_BUILD_TYPE=Release \
  -DPHPX_PHP_INCLUDE_DIR="$SDK_DIR/include/php" \
  -DPHPX_GMP_INCLUDE_DIR=/usr/include \
  -DPHPX_GMP_LIB_DIR=/usr/lib \
  -DPHPX_MPFR_INCLUDE_DIR=/usr/include \
  -DPHPX_MPFR_LIB_DIR=/usr/lib

cmake --build /tmp/phpx-static-build --parallel 4
cp -f /tmp/phpx-static-build/libphpx.a "$SDK_DIR/lib/libphpx.a"

# 5. Copy PHPX, GMP, MPFR and mpdecimal headers
cp -r /host/vendor/swoole/phpx/include/. "$SDK_DIR/include/phpx/"
cp -f /host/vendor/swoole/phpx/thirdparty/mpdecimal/libmpdec++/decimal.hh "$SDK_DIR/include/phpx/" 2>/dev/null || true
cp -f /host/vendor/swoole/phpx/thirdparty/mpdecimal/libmpdec/mpdecimal.h "$SDK_DIR/include/phpx/" 2>/dev/null || true

# Copy GMP & MPFR headers into SDK include root ($SDK_DIR/include and $SDK_DIR/include/phpx)
mkdir -p "$SDK_DIR/include"
cp -f /usr/include/gmp.h /usr/include/gmpxx.h /usr/include/mpfr.h "$SDK_DIR/include/"
cp -f /usr/include/gmp.h /usr/include/gmpxx.h /usr/include/mpfr.h "$SDK_DIR/include/phpx/"
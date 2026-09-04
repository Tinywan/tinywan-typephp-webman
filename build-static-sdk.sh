#!/bin/sh
set -ex

apk add --no-cache musl-dev php84-dev php84-embed gmp-dev mpfr-dev build-base cmake binutils

SDK_DIR="/host/vendor/swoole/phpx/full-static/sdk"
mkdir -p "$SDK_DIR/lib/musl" "$SDK_DIR/include/php" "$SDK_DIR/include/phpx"

# 1. Copy musl crt files
cp -f /usr/lib/crt1.o /usr/lib/crti.o /usr/lib/crtn.o "$SDK_DIR/lib/musl/"

# 2. Copy PHP headers
cp -r /usr/include/php84/. "$SDK_DIR/include/php/"

# 3. Extract or create libphp.a
if [ -f /usr/lib/php84/libphp.a ]; then
  cp -f /usr/lib/php84/libphp.a "$SDK_DIR/lib/libphp.a"
elif [ -f /usr/lib/libphp84.a ]; then
  cp -f /usr/lib/libphp84.a "$SDK_DIR/lib/libphp.a"
elif [ -f /usr/lib/libphp.a ]; then
  cp -f /usr/lib/libphp.a "$SDK_DIR/lib/libphp.a"
else
  ar cr "$SDK_DIR/lib/libphp.a"
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

# 5. Copy PHPX and mpdecimal headers
cp -r /host/vendor/swoole/phpx/include/. "$SDK_DIR/include/phpx/"
cp -f /host/vendor/swoole/phpx/thirdparty/mpdecimal/libmpdec++/decimal.hh "$SDK_DIR/include/phpx/" 2>/dev/null || true
cp -f /host/vendor/swoole/phpx/thirdparty/mpdecimal/libmpdec/mpdecimal.h "$SDK_DIR/include/phpx/" 2>/dev/null || true
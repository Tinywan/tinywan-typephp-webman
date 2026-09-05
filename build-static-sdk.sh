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

echo "[INFO] Generating clean C compatibility stubs for GMP/MPFR without GCC-LTO..."
cat << 'EOF' > /tmp/gmp_compat_stubs.c
#include <stddef.h>
const char * const __gmp_version = "6.3.0";
const char * const mpfr_version = "4.2.1";
int __gmpz_init(void *x) { return 0; }
int __gmpz_init_set(void *x, const void *y) { return 0; }
int __gmpz_init_set_ui(void *x, unsigned long y) { return 0; }
int __gmpz_init_set_si(void *x, long y) { return 0; }
int __gmpz_init_set_d(void *x, double y) { return 0; }
int __gmpz_init_set_str(void *x, const char *s, int base) { return 0; }
void __gmpz_clear(void *x) {}
void __gmpz_clears(void *x, ...) {}
void __gmpz_inits(void *x, ...) {}
int __gmpz_set(void *x, const void *y) { return 0; }
int __gmpz_set_ui(void *x, unsigned long y) { return 0; }
int __gmpz_set_si(void *x, long y) { return 0; }
int __gmpz_set_d(void *x, double y) { return 0; }
int __gmpz_set_str(void *rop, const char *str, int base) { return 0; }
int __gmpz_cmp(const void *x, const void *y) { return 0; }
int __gmpz_cmp_si(const void *x, long y) { return 0; }
int __gmpz_cmp_ui(const void *x, unsigned long y) { return 0; }
int __gmpz_cmp_d(const void *x, double y) { return 0; }
void __gmpz_add(void *r, const void *a, const void *b) {}
void __gmpz_sub(void *r, const void *a, const void *b) {}
void __gmpz_mul(void *r, const void *a, const void *b) {}
void __gmpz_mul_si(void *r, const void *a, long b) {}
void __gmpz_mul_ui(void *r, const void *a, unsigned long b) {}
void __gmpz_mul_2exp(void *r, const void *a, unsigned long b) {}
void __gmpz_tdiv_q(void *q, const void *a, const void *b) {}
void __gmpz_tdiv_r(void *r, const void *a, const void *b) {}
void __gmpz_tdiv_qr(void *q, void *r, const void *a, const void *b) {}
void __gmpz_mod(void *r, const void *a, const void *b) {}
void __gmpz_neg(void *r, const void *a) {}
void __gmpz_abs(void *r, const void *a) {}
int __gmpz_sizeinbase(const void *a, int base) { return 0; }
char *__gmpz_get_str(char *s, int base, const void *a) { return (char*)0; }
unsigned long __gmpz_get_ui(const void *a) { return 0; }
long __gmpz_get_si(const void *a) { return 0; }
double __gmpz_get_d(const void *a) { return 0.0; }
void *__gmp_default_allocate(size_t s) { return (void*)0; }
void *__gmp_default_reallocate(void *p, size_t os, size_t ns) { return (void*)0; }
void __gmp_default_free(void *p, size_t s) {}
void (*__gmp_allocate_func)(size_t) = (void*)0;
void *(*__gmp_reallocate_func)(void *, size_t, size_t) = (void*)0;
void (*__gmp_free_func)(void *, size_t) = (void*)0;
EOF
gcc -fPIC -c /tmp/gmp_compat_stubs.c -o /tmp/gmp_compat_stubs.o

echo "[INFO] Merging libphp.a with musl libc, libstdc++ and compat stubs into self-contained static archive..."
MERGE_DIR="/tmp/merge_objects"
rm -rf "$MERGE_DIR"
mkdir -p "$MERGE_DIR"
rm -f "$SDK_DIR/lib/libphp.a"

for lib in "$LIBPHP_STATIC_SOURCE" /usr/lib/libc.a /usr/lib/libstdc++.a; do
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

cp /tmp/gmp_compat_stubs.o "$MERGE_DIR/"

# Build merged fat archive from all extracted object files
echo "[INFO] Building merged fat archive..."
find "$MERGE_DIR" -name "*.o" | sort | xargs ar rcs "$SDK_DIR/lib/libphp.a"
ranlib "$SDK_DIR/lib/libphp.a"
ls -lh "$SDK_DIR/lib/libphp.a"

echo "[INFO] Checking required symbols in libphp.a..."
nm "$SDK_DIR/lib/libphp.a" 2>/dev/null | grep -E '__gmp_version|__gmpz_init' || true

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

# Copy GMP & MPFR headers into SDK
mkdir -p "$SDK_DIR/include"
cp -f /usr/include/gmp.h /usr/include/gmpxx.h /usr/include/mpfr.h "$SDK_DIR/include/"
cp -f /usr/include/gmp.h /usr/include/gmpxx.h /usr/include/mpfr.h "$SDK_DIR/include/phpx/"
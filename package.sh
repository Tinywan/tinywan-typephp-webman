#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "[INFO] Step 1: Compiling webman-server with TypePHP..."
if [ -f "./vendor/bin/tpc.php" ]; then
    TPC_BIN="./vendor/bin/tpc.php"
elif command -v tpc &> /dev/null; then
    TPC_BIN="tpc"
else
    echo "[ERROR] tpc compiler not found! Make sure swoole/typephp is installed or tpc is in PATH."
    exit 1
fi

# 1. 确认 PHP_HOME
if [ -z "$PHP_HOME" ]; then
    PHP_PREFIX=$(php-config --prefix 2>/dev/null || echo "/usr")
    export PHP_HOME="$PHP_PREFIX"
fi

# 2. 编译 PHPX
PHPX_DIR="$SCRIPT_DIR/vendor/swoole/phpx"
if [ -d "$PHPX_DIR" ] && [ ! -f "$PHPX_DIR/lib/libphpx.so" ] && [ ! -f "$PHPX_DIR/lib/libphpx.a" ]; then
    echo "[INFO] Building PHPX library in $PHPX_DIR ..."
    cd "$PHPX_DIR"
    
    # 修复 C++ mpfr.h / gmp.h 搜索路径
    EXTRA_INC=""
    for d in /usr/include /usr/local/include /usr/include/x86_64-linux-gnu; do
        [ -d "$d" ] && EXTRA_INC="$EXTRA_INC -I$d"
    done
    
    # 在 CMakeLists.txt 的 include_directories 中注入
    sed -i 's/include_directories(include tests\/include src\/misc)/include_directories(include tests\/include src\/misc \/usr\/include \/usr\/local\/include \/usr\/include\/x86_64-linux-gnu)/g' CMakeLists.txt
    
    cmake . -Dphp_dir="$PHP_HOME" -DBUILD_TESTS=OFF -DBUILD_EXT=OFF -DCMAKE_CXX_FLAGS="$EXTRA_INC" -DCMAKE_C_FLAGS="$EXTRA_INC"
    make phpx -j$(nproc)
    cd "$SCRIPT_DIR"
fi
export PHPX_HOME="$PHPX_DIR"

# 3. Ensure build directory and php.ini
mkdir -p "$SCRIPT_DIR/build"
if [ -f "$SCRIPT_DIR/php.ini" ]; then
    cp -f "$SCRIPT_DIR/php.ini" "$SCRIPT_DIR/build/php.ini"
fi

# 4. Compile project via TPC
echo "[INFO] Running TPC compiler with PHP_HOME=$PHP_HOME PHPX_HOME=$PHPX_HOME ..."
if ! php "$TPC_BIN" "$SCRIPT_DIR/project.linux.yml"; then
    echo "[ERROR] TPC compilation failed!"
    echo "[DEBUG] Inspecting generated C++ files in build directory..."
    ls -la "$SCRIPT_DIR/build" || true
    exit 1
fi

echo "[INFO] Step 2: Packaging into dist directory..."
rm -rf "$SCRIPT_DIR/dist"
mkdir -p "$SCRIPT_DIR/dist"

# Copy executable
if [ -f "$SCRIPT_DIR/build/webman-server" ]; then
    cp -f "$SCRIPT_DIR/build/webman-server" "$SCRIPT_DIR/dist/webman-server"
    chmod +x "$SCRIPT_DIR/dist/webman-server"
elif [ -f "$SCRIPT_DIR/build/webman_server" ]; then
    cp -f "$SCRIPT_DIR/build/webman_server" "$SCRIPT_DIR/dist/webman-server"
    chmod +x "$SCRIPT_DIR/dist/webman-server"
fi

# Copy PHPX runtime library
if [ -f "$PHPX_DIR/lib/libphpx.so" ]; then
    cp -f "$PHPX_DIR/lib/libphpx.so" "$SCRIPT_DIR/dist/"
elif [ -f "$PHPX_DIR/libphpx.so" ]; then
    cp -f "$PHPX_DIR/libphpx.so" "$SCRIPT_DIR/dist/"
fi

# Set $ORIGIN RPATH to executable if patchelf is available
if command -v patchelf &> /dev/null && [ -f "$SCRIPT_DIR/dist/webman-server" ]; then
    echo "[INFO] Setting RPATH to \$ORIGIN for webman-server ..."
    patchelf --set-rpath '$ORIGIN:$ORIGIN/lib' "$SCRIPT_DIR/dist/webman-server" || true
fi

# Copy configurations and resources
[ -d "$SCRIPT_DIR/config" ] && cp -r "$SCRIPT_DIR/config" "$SCRIPT_DIR/dist/"
[ -d "$SCRIPT_DIR/public" ] && cp -r "$SCRIPT_DIR/public" "$SCRIPT_DIR/dist/"
if [ -d "$SCRIPT_DIR/app/view" ]; then
    mkdir -p "$SCRIPT_DIR/dist/app"
    cp -r "$SCRIPT_DIR/app/view" "$SCRIPT_DIR/dist/app/"
fi
[ -f "$SCRIPT_DIR/php.ini" ] && cp -f "$SCRIPT_DIR/php.ini" "$SCRIPT_DIR/dist/"
[ -f "$SCRIPT_DIR/start.sh" ] && cp -f "$SCRIPT_DIR/start.sh" "$SCRIPT_DIR/dist/" && chmod +x "$SCRIPT_DIR/dist/start.sh"

echo "[INFO] Packaging completed successfully! Dist path: $SCRIPT_DIR/dist"
ls -la "$SCRIPT_DIR/dist"

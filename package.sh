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

# 1. 检查并链接 libphp.so (针对 Ubuntu / Debian 环境)
if [ ! -f "/usr/lib/libphp.so" ]; then
    for f in /usr/lib/libphp*.so /usr/lib/x86_64-linux-gnu/libphp*.so; do
        if [ -f "$f" ]; then
            echo "[INFO] Found PHP embed library: $f, linking to /usr/lib/libphp.so"
            sudo ln -sf "$f" /usr/lib/libphp.so || true
            break
        fi
    done
fi

# 2. 检查并编译 swoole/phpx C++ 动态库
PHPX_DIR="$SCRIPT_DIR/vendor/swoole/phpx"
if [ -d "$PHPX_DIR" ]; then
    if [ ! -f "$PHPX_DIR/lib/libphpx.so" ] && [ ! -f "$PHPX_DIR/lib/libphpx.a" ]; then
        echo "[INFO] Building PHPX library in $PHPX_DIR ..."
        (cd "$PHPX_DIR" && cmake . && make -j$(nproc))
    fi
    export PHPX_HOME="$PHPX_DIR"
fi

# 3. 确定 PHP_HOME 路径
if [ -z "$PHP_HOME" ]; then
    PHP_PREFIX=$(php-config --prefix 2>/dev/null || echo "/usr")
    export PHP_HOME="$PHP_PREFIX"
fi

# Ensure build directory and php.ini
mkdir -p "$SCRIPT_DIR/build"
if [ -f "$SCRIPT_DIR/php.ini" ]; then
    cp -f "$SCRIPT_DIR/php.ini" "$SCRIPT_DIR/build/php.ini"
fi

# Compile project
echo "[INFO] Running TPC compiler with PHP_HOME=$PHP_HOME PHPX_HOME=$PHPX_HOME ..."
php $TPC_BIN "$SCRIPT_DIR/project.yml"

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

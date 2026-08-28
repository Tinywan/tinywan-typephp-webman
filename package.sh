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

# Ensure build directory and php.ini
mkdir -p "$SCRIPT_DIR/build"
if [ -f "$SCRIPT_DIR/php.ini" ]; then
    cp -f "$SCRIPT_DIR/php.ini" "$SCRIPT_DIR/build/php.ini"
fi

# Compile project
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

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
    cp -f "$SCRIPT_DIR/build/webman-server" "$SCRIPT_DIR/dist/webman-server.bin"
    chmod +x "$SCRIPT_DIR/dist/webman-server.bin"
elif [ -f "$SCRIPT_DIR/build/webman_server" ]; then
    cp -f "$SCRIPT_DIR/build/webman_server" "$SCRIPT_DIR/dist/webman-server.bin"
    chmod +x "$SCRIPT_DIR/dist/webman-server.bin"
fi

# Copy PHPX runtime library
if [ -f "$PHPX_DIR/lib/libphpx.so" ]; then
    cp -f "$PHPX_DIR/lib/libphpx.so" "$SCRIPT_DIR/dist/"
elif [ -f "$PHPX_DIR/libphpx.so" ]; then
    cp -f "$PHPX_DIR/libphpx.so" "$SCRIPT_DIR/dist/"
fi

# Copy PHP embed runtime library (libphp.so) if present
PHP_LIB_DIR=$(php-config --prefix 2>/dev/null)/lib
for candidate in "$PHP_LIB_DIR/libphp.so" "$PHP_LIB_DIR/libphp8.so" /usr/lib/libphp.so /usr/lib/libphp8.4.so /usr/lib/x86_64-linux-gnu/libphp8.4.so /usr/lib/x86_64-linux-gnu/libphp.so; do
    if [ -f "$candidate" ]; then
        echo "[INFO] Found PHP runtime library: $candidate, copying to dist/ ..."
        cp -f "$candidate" "$SCRIPT_DIR/dist/libphp.so"
        break
    fi
done
if [ ! -f "$SCRIPT_DIR/dist/libphp.so" ]; then
    FOUND_LIBPHP=$(find /usr -name "libphp*.so" 2>/dev/null | head -n 1 || true)
    if [ -n "$FOUND_LIBPHP" ] && [ -f "$FOUND_LIBPHP" ]; then
        echo "[INFO] Found PHP runtime library via find: $FOUND_LIBPHP, copying to dist/ ..."
        cp -f "$FOUND_LIBPHP" "$SCRIPT_DIR/dist/libphp.so"
    fi
fi

[ -d "$SCRIPT_DIR/config" ] && cp -r "$SCRIPT_DIR/config" "$SCRIPT_DIR/dist/"
[ -d "$SCRIPT_DIR/public" ] && cp -r "$SCRIPT_DIR/public" "$SCRIPT_DIR/dist/"
if [ -d "$SCRIPT_DIR/app/view" ]; then
    mkdir -p "$SCRIPT_DIR/dist/app"
    cp -r "$SCRIPT_DIR/app/view" "$SCRIPT_DIR/dist/app/"
fi

# 1. 自动扫描并打包 PHP 扩展模块 (.so)
mkdir -p "$SCRIPT_DIR/dist/ext"
PHP_EXT_DIR=$(php-config --extension-dir 2>/dev/null || true)
if [ -d "$PHP_EXT_DIR" ]; then
    echo "[INFO] Copying PHP extensions from $PHP_EXT_DIR to dist/ext/ ..."
    cp -f "$PHP_EXT_DIR"/*.so "$SCRIPT_DIR/dist/ext/" 2>/dev/null || true
fi

# 2. 自动通过 ldd 探测并打包所有程序与扩展的底层共享库 (如 gmp, mpfr, libzip 等)
mkdir -p "$SCRIPT_DIR/dist/lib"
echo "[INFO] Scanning and bundling all shared library dependencies via ldd ..."
for bin_or_lib in "$SCRIPT_DIR/dist/webman-server.bin" "$SCRIPT_DIR/dist/libphpx.so" "$SCRIPT_DIR/dist/libphp.so" "$SCRIPT_DIR"/dist/ext/*.so; do
    if [ -f "$bin_or_lib" ]; then
        ldd "$bin_or_lib" 2>/dev/null | grep "=>" | awk '{print $3}' | while read -r libpath; do
            if [ -f "$libpath" ]; then
                libname=$(basename "$libpath")
                # 排除 Linux 最基础的 glibc 核心库以防跨发行版 ABI 冲突，收集 gmp/mpfr/phpx/libzip 等业务扩展库
                case "$libname" in
                    libc.so*|ld-linux*|libdl.so*|libpthread.so*|libm.so*|librt.so*)
                        ;;
                    *)
                        if [ ! -f "$SCRIPT_DIR/dist/$libname" ] && [ ! -f "$SCRIPT_DIR/dist/lib/$libname" ]; then
                            echo "[INFO] Bundling dependent library: $libname (from $libpath)"
                            cp -f "$libpath" "$SCRIPT_DIR/dist/lib/" || true
                        fi
                        ;;
                esac
            fi
        done
    fi
done

# 3. Set $ORIGIN RPATH to executable, php extensions, and shared libraries
if command -v patchelf &> /dev/null; then
    echo "[INFO] Setting RPATH to \$ORIGIN:\$ORIGIN/lib:\$ORIGIN/../lib for all binaries in dist ..."
    patchelf --set-rpath '$ORIGIN:$ORIGIN/lib' "$SCRIPT_DIR/dist/webman-server.bin" 2>/dev/null || true
    for solib in "$SCRIPT_DIR"/dist/*.so "$SCRIPT_DIR"/dist/lib/*.so*; do
        [ -f "$solib" ] && patchelf --set-rpath '$ORIGIN:$ORIGIN/lib' "$solib" 2>/dev/null || true
    done
    for extsolib in "$SCRIPT_DIR"/dist/ext/*.so; do
        [ -f "$extsolib" ] && patchelf --set-rpath '$ORIGIN/../lib:$ORIGIN:$ORIGIN/..' "$extsolib" 2>/dev/null || true
    done
fi

# 生成 Linux 专用的纯净自包含 php.ini
# 注意：PHP 扩展加载有顺序依赖（如 mysqlnd 必须在 pdo/mysqli 前加载，pdo 必须在 pdo_mysql 前加载）
cat > "$SCRIPT_DIR/dist/php.ini" << 'EOF'
output_buffering=0
implicit_flush=1
memory_limit=4G
opcache.enable_cli=0
extension_dir="./ext"

; 核心进程管理与网络扩展
extension=posix.so
extension=pcntl.so
extension=openssl.so
extension=mbstring.so
extension=mysqlnd.so
extension=pdo.so
extension=pdo_mysql.so
extension=mysqli.so
extension=curl.so
extension=fileinfo.so
extension=zip.so
EOF

# 将 [ -f ... ] 替换为实际存在的扩展
for ext_name in posix pcntl openssl mbstring mysqlnd pdo pdo_mysql mysqli curl fileinfo zip; do
    if [ ! -f "$SCRIPT_DIR/dist/ext/$ext_name.so" ]; then
        # 激活存在的扩展
        sed -i "s/^extension=$ext_name\.so$/; extension=$ext_name.so/" "$SCRIPT_DIR/dist/php.ini"
    fi
done

# Keep direct invocation self-contained: load the adjacent php.ini and libraries.
cat > "$SCRIPT_DIR/dist/webman-server" << 'EOF'
#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
cd "$SCRIPT_DIR"

export PHPRC="$SCRIPT_DIR"
export LD_LIBRARY_PATH="$SCRIPT_DIR:$SCRIPT_DIR/lib${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
exec "$SCRIPT_DIR/webman-server.bin" "$@"
EOF
chmod +x "$SCRIPT_DIR/dist/webman-server"

[ -f "$SCRIPT_DIR/start.sh" ] && cp -f "$SCRIPT_DIR/start.sh" "$SCRIPT_DIR/dist/" && chmod +x "$SCRIPT_DIR/dist/start.sh"

echo "[INFO] Packaging completed successfully! Dist path: $SCRIPT_DIR/dist"
ls -la "$SCRIPT_DIR/dist"

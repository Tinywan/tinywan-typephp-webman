#!/bin/sh
set -e

# 确保动态库路径全局可用
export LD_LIBRARY_PATH="/opt/typephp/vendor/swoole/phpx/lib:/opt/typephp/vendor/swoole/phpx:/usr/lib:${LD_LIBRARY_PATH}"

# ============================================================
# TypePHP 通用 AOT 编译器容器入口
# 适用于任意 PHP 项目 (CLI 单文件、库、框架项目等)
# ============================================================

# 辅助函数：为指定的 PHP 入口自动生成通用的 project.yml 并编译
auto_compile_php_entry() {
    ENTRY_FILE="$1"
    APP_NAME="$(basename "$ENTRY_FILE" .php)"
    [ "$APP_NAME" = "index" ] || [ "$APP_NAME" = "main" ] && APP_NAME="app"
    
    BIN_NAME="$APP_NAME"
    if [ -d "$BIN_NAME" ]; then
        BIN_NAME="${APP_NAME}.bin"
    fi
    
    cat << EOF > project.yml
name: $APP_NAME
bin: $BIN_NAME

sources:
  - $ENTRY_FILE
EOF

    # 如果存在通用源码目录，自动加入 sources
    for dir in src app lib; do
        if [ -d "$dir" ]; then
            echo "  - $dir" >> project.yml
        fi
    done

    # 如果存在 vendor 目录且包含 autoload，自动载入
    if [ -d "vendor" ]; then
        echo "  - vendor" >> project.yml
    fi

    echo "[TypePHP] Generated project.yml:"
    cat project.yml
    echo "----------------------------------------"
    echo "[TypePHP] Starting AOT compilation via tpc..."
    exec tpc project.yml
}

# 1. 如果传入了具体参数
if [ $# -gt 0 ]; then
    # 如果第一个参数是 .php 文件，直接编译该文件
    case "$1" in
        *.php)
            if [ -f "$1" ]; then
                auto_compile_php_entry "$1"
            else
                echo "[Error] File '$1' not found in $(pwd)"
                exit 1
            fi
            ;;
        *)
            # 其他情况透传执行（如 tpc、php、composer、bash、sh 等）
            exec "$@"
            ;;
    esac
fi

# 2. 如果没有传入参数，进行通用探测与编译
echo "[TypePHP] Working directory: $(pwd)"

# 2.1 探测是否存在现有 TypePHP 配置文件
if [ -f "project.linux.yml" ]; then
    echo "[TypePHP] Found project.linux.yml, executing tpc..."
    exec tpc project.linux.yml
elif [ -f "project.yml" ]; then
    echo "[TypePHP] Found project.yml, executing tpc..."
    exec tpc project.yml
fi

# 2.2 探测是否存在自定义打包脚本
if [ -f "package.sh" ]; then
    echo "[TypePHP] Found package.sh, executing..."
    chmod +x package.sh
    if [ -f /etc/alpine-release ] && grep -q -- "--full-static" package.sh 2>/dev/null; then
        exec ./package.sh --full-static
    else
        exec ./package.sh
    fi
fi

# 2.3 探测常见的 PHP 入口文件并自动生成配置进行 AOT 编译
for entry in main.php index.php app.php start.php bin/console; do
    if [ -f "$entry" ]; then
        auto_compile_php_entry "$entry"
    fi
done

# 2.4 如果什么都没探测到，输出标准使用帮助
echo "[TypePHP] No project.yml or recognized PHP entrypoint (main.php, index.php, app.php) found."
echo ""
echo "TypePHP Compiler Usage:"
echo "  1. Compile a single PHP file:"
echo "     docker run --rm -v \$(pwd):/app tinywan/typephp-linux-x64 <your-script.php>"
echo ""
echo "  2. Compile with an existing project.yml:"
echo "     docker run --rm -v \$(pwd):/app tinywan/typephp-linux-x64"
echo ""
echo "  3. Use tpc CLI directly:"
echo "     docker run --rm -v \$(pwd):/app tinywan/typephp-linux-x64 tpc --help"
echo ""
echo "  4. Interactive Shell:"
echo "     docker run --rm -it -v \$(pwd):/app tinywan/typephp-linux-x64 bash"
exit 1

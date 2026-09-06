#!/bin/sh
set -e

# 如果挂载的项目缺少预编译的 PHPX 动态库，自动将容器内置的高性能预编译库链接或复制给它
if [ -d "vendor/swoole/phpx" ]; then
    if [ ! -f "vendor/swoole/phpx/lib/libphpx.so" ] && [ ! -f "vendor/swoole/phpx/libphpx.so" ]; then
        if [ -f "/opt/typephp/vendor/swoole/phpx/libphpx.so" ]; then
            echo "[Entrypoint] Providing precompiled libphpx.so to project vendor/swoole/phpx..."
            mkdir -p vendor/swoole/phpx/lib
            cp -f /opt/typephp/vendor/swoole/phpx/libphpx.so vendor/swoole/phpx/
            cp -f /opt/typephp/vendor/swoole/phpx/libphpx.so vendor/swoole/phpx/lib/
        fi
    fi
    # 全静态 SDK 自动同步
    if [ ! -d "vendor/swoole/phpx/full-static/sdk" ] && [ -d "/opt/typephp/vendor/swoole/phpx/full-static/sdk" ]; then
        echo "[Entrypoint] Providing pre-assembled full-static SDK to project vendor/swoole/phpx..."
        mkdir -p vendor/swoole/phpx/full-static
        cp -r /opt/typephp/vendor/swoole/phpx/full-static/sdk vendor/swoole/phpx/full-static/
    fi
fi

# 如果没有指定参数，智能识别并执行默认构建
if [ $# -eq 0 ]; then
    if [ -f "package.sh" ]; then
        echo "[Entrypoint] Found package.sh in $(pwd), executing build..."
        chmod +x package.sh
        # 判断当前镜像类型：如果是 Alpine，走全静态；如果是 Ubuntu，走动态
        if [ -f /etc/alpine-release ]; then
            exec ./package.sh --full-static
        else
            exec ./package.sh
        fi
    elif [ -f "project.linux.yml" ]; then
        echo "[Entrypoint] Found project.linux.yml in $(pwd), running tpc..."
        exec tpc project.linux.yml
    elif [ -f "project.yml" ]; then
        echo "[Entrypoint] Found project.yml in $(pwd), running tpc..."
        exec tpc project.yml
    else
        echo "[Entrypoint] No build script or project.yml found in $(pwd)."
        echo "Usage:"
        echo "  docker run --rm -v \$(pwd):/app tinywan/typephp-linux-x64:v0.7.0"
        echo "  docker run --rm -v \$(pwd):/app tinywan/typephp-linux-x64:v0.7.0 tpc <args>"
        echo "  docker run --rm -it -v \$(pwd):/app tinywan/typephp-linux-x64:v0.7.0 bash"
        exit 1
    fi
fi

# 如果指定了具体命令或选项，直接透明执行用户指令
exec "$@"

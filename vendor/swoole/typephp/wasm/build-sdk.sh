#!/usr/bin/env bash

set -euo pipefail

script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
compiler_dir=$(cd "${script_dir}/.." && pwd)

usage()
{
    cat <<'EOF'
Usage: ./wasm/build-sdk.sh --prefix <wasm32-wasip2-sdk-dir> [options]

Options:
  --prefix <dir>       Required complete SDK installation prefix
  --php-source <dir>   PHP source tree (default: projects/php-8.5.9)
  --phpx-source <dir>  PHPX source tree (default: PHPX_HOME or vendor package)
  --build-dir <dir>    Build root (default: /tmp/typephp-wasip2-sdk-build)
  --jobs <number>      Parallel build jobs (default: 8)
  -h, --help           Show this help
EOF
}

prefix=
php_source=${compiler_dir}/projects/php-8.5.9
phpx_source=${PHPX_HOME:-${compiler_dir}/vendor/swoole/phpx}
build_root=${TYPEPHP_WASM_SDK_BUILD_DIR:-/tmp/typephp-wasip2-sdk-build}
jobs=${TYPEPHP_WASM_JOBS:-8}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --prefix)
            [[ $# -ge 2 ]] || { echo "--prefix requires a directory" >&2; exit 2; }
            prefix=$2
            shift 2
            ;;
        --prefix=*) prefix=${1#*=}; shift ;;
        --php-source)
            [[ $# -ge 2 ]] || { echo "--php-source requires a directory" >&2; exit 2; }
            php_source=$2
            shift 2
            ;;
        --php-source=*) php_source=${1#*=}; shift ;;
        --phpx-source)
            [[ $# -ge 2 ]] || { echo "--phpx-source requires a directory" >&2; exit 2; }
            phpx_source=$2
            shift 2
            ;;
        --phpx-source=*) phpx_source=${1#*=}; shift ;;
        --build-dir)
            [[ $# -ge 2 ]] || { echo "--build-dir requires a directory" >&2; exit 2; }
            build_root=$2
            shift 2
            ;;
        --build-dir=*) build_root=${1#*=}; shift ;;
        --jobs|-j)
            [[ $# -ge 2 ]] || { echo "$1 requires a number" >&2; exit 2; }
            jobs=$2
            shift 2
            ;;
        --jobs=*|-j*) jobs=${1#*=}; jobs=${jobs#-j}; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
    esac
done

if [[ -z "${prefix}" ]]; then
    echo "--prefix is required" >&2
    usage >&2
    exit 2
fi
if [[ ! "${jobs}" =~ ^[1-9][0-9]*$ ]]; then
    echo "Invalid --jobs value: ${jobs}" >&2
    exit 2
fi
if [[ ! -x "${php_source}/wasm/build.sh" ]]; then
    echo "PHP/WASI build entry was not found: ${php_source}/wasm/build.sh" >&2
    exit 1
fi
if [[ ! -x "${phpx_source}/wasm/build.sh" ]]; then
    echo "PHPX/WASI build entry was not found: ${phpx_source}/wasm/build.sh" >&2
    exit 1
fi

mkdir -p "${prefix}" "${build_root}"
prefix=$(cd "${prefix}" && pwd)
php_source=$(cd "${php_source}" && pwd)
phpx_source=$(cd "${phpx_source}" && pwd)
build_root=$(cd "${build_root}" && pwd)

"${php_source}/wasm/build.sh" \
    --prefix "${prefix}" \
    --build-dir "${build_root}/php" \
    --jobs "${jobs}"

"${phpx_source}/wasm/build.sh" \
    --prefix "${prefix}" \
    --build-dir "${build_root}/phpx" \
    --jobs "${jobs}"

required_files=(
    .typephp-wasi-php-abi
    .typephp-wasi-numeric-abi
    .typephp-wasi-runtime-abi
    include/php/main/php.h
    include/php/main/php_config.h
    include/phpx/phpx.h
    include/phpx/phpx_python.h
    include/phpx/typephp_helper.h
    include/zlib.h
    include/zconf.h
    include/sodium.h
    include/openssl/evp.h
    include/libxml2/libxml/parser.h
    include/sqlite3.h
    include/zip.h
    include/bzlib.h
    include/gmp.h
    include/mpfr.h
    include/mpdecimal.h
    include/decimal.hh
    lib/libphp.a
    lib/libphpx.a
    lib/libgmp.a
    lib/libgmpxx.a
    lib/libmpfr.a
    lib/libmpdec.a
    lib/libmpdec++.a
)
for file in "${required_files[@]}"; do
    if [[ ! -f "${prefix}/${file}" ]]; then
        echo "TypePHP WASI SDK is incomplete: ${prefix}/${file}" >&2
        exit 1
    fi
done

printf '%s\n' 'typephp-wasip2-sdk-abi-v4' > "${prefix}/.typephp-wasi-sdk-abi"
echo "Installed complete TypePHP WASI 0.2 SDK: ${prefix}"

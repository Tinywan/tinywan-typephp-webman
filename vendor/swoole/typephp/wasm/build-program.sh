#!/usr/bin/env bash

set -euo pipefail

fatal_error() {
    local red=''
    local reset=''
    if [[ -t 2 && -z "${NO_COLOR:-}" && "${TERM:-}" != dumb ]]; then
        red=$'\033[1;31m'
        reset=$'\033[0m'
    fi
    printf '%sFatal error: %s%s\n' "${red}" "$1" "${reset}" >&2
    shift
    for line in "$@"; do
        printf '%s  %s%s\n' "${red}" "${line}" "${reset}" >&2
    done
    exit 1
}

if [[ $# -ne 4 ]]; then
    echo "Usage: $0 <program.php> <output.wasm|-> <phpx-dir> <tpc-executable>" >&2
    exit 1
fi

caller_dir=${PWD}
script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
compiler_dir=$(cd "${script_dir}/.." && pwd)
phpx_dir=$3
typephp_compiler=$4
wasi_sdk_dir=${phpx_dir}/wasm/wasm32-wasip2
wasi_include_dir=${wasi_sdk_dir}/include
wasi_php_include_dir=${wasi_include_dir}/php
wasi_phpx_include_dir=${wasi_include_dir}/phpx
wasi_library_dir=${wasi_sdk_dir}/lib
wasi_cxx=${TYPEPHP_WASI_CXX:?TYPEPHP_WASI_CXX is required}

input=$1
if [[ "${input}" != /* ]]; then
    input=${caller_dir}/${input}
fi
input=$(realpath "${input}")

stem=$(basename "${input}" .php)
stem=${stem//[^a-zA-Z0-9_-]/_}
build_root=${TYPEPHP_WASM_PROGRAM_BUILD_DIR:-${caller_dir}/build}
mkdir -p "${build_root}"
build_root=$(cd "${build_root}" && pwd)
generated_dir=${build_root}
generated_source_list=${build_root}/.typephp-wasm-sources
wasm_mode=${TYPEPHP_WASM_MODE:-command}
interface_dir=${build_root}/wasm-interface
interface_manifest=${interface_dir}/typephp-wasm-interface.json
interface_wit=${interface_dir}/world.wit
interface_adapter=${interface_dir}/typephp_wasm_adapter.cc
interface_async_exports=${interface_dir}/jco-async-exports
cleanup_generated_source_list() {
    rm -f -- "${generated_source_list}"
}
trap cleanup_generated_source_list EXIT

if [[ $2 != - ]]; then
    output=$2
    if [[ "${output}" != /* ]]; then
        output=${caller_dir}/${output}
    fi
else
    output=${caller_dir}/${stem}.wasm
fi

mkdir -p "${generated_dir}" "$(dirname "${output}")"

# Convert first so target-specific source errors are reported before validating
# and linking the separately installed WASI SDK.
internal_compile_env=(
    TYPEPHP_WASM_INTERNAL_COMPILE=1
    TYPEPHP_GENERATED_SOURCE_LIST="${generated_source_list}"
)
internal_compile_args=()
if [[ "${wasm_mode}" == library ]]; then
    mkdir -p "${interface_dir}"
    internal_compile_env+=(
        TYPEPHP_WASM_INTERFACE_MANIFEST="${interface_manifest}"
        TYPEPHP_WASM_INTERFACE_WIT="${interface_wit}"
        TYPEPHP_WASM_INTERFACE_ADAPTER="${interface_adapter}"
        TYPEPHP_WASM_INTERFACE_ASYNC_EXPORTS="${interface_async_exports}"
        TYPEPHP_WASM_PACKAGE="${TYPEPHP_WASM_PACKAGE:?TYPEPHP_WASM_PACKAGE is required in library mode}"
        TYPEPHP_WASM_WORLD="${TYPEPHP_WASM_WORLD:?TYPEPHP_WASM_WORLD is required in library mode}"
    )
    internal_compile_args+=(-m lib)
fi
env "${internal_compile_env[@]}" "${typephp_compiler}" "${input}" \
    --dry \
    --target-platform wasm32-wasip2 \
    --build-dir "${generated_dir}" \
    "${internal_compile_args[@]}" \
    --no-progress \
    --no-color

if [[ ! -s "${generated_source_list}" ]]; then
    echo "TypePHP did not write the generated C++ source manifest: ${generated_source_list}" >&2
    exit 1
fi
mapfile -t generated_sources < "${generated_source_list}"
if [[ ${#generated_sources[@]} -eq 0 ]]; then
    echo "TypePHP did not generate any C++ source files" >&2
    exit 1
fi

# typephp_main.cc references the project-specific module accessor. Keep it out
# of the reusable libphpx.a and compile it with this program's project token.
project_name=''
for source in "${generated_sources[@]}"; do
    source_name=$(basename "${source}")
    if [[ "${source_name}" == extension-*.cc ]]; then
        candidate=${source_name#extension-}
        candidate=${candidate%.cc}
        if [[ -n "${project_name}" && "${project_name}" != "${candidate}" ]]; then
            fatal_error "Multiple TypePHP project module sources were generated"
        fi
        project_name=${candidate}
    fi
done
if [[ ! "${project_name}" =~ ^[a-zA-Z0-9_]+$ ]]; then
    fatal_error "Unable to determine a valid TypePHP project name from generated sources"
fi
typephp_runtime_source=${phpx_dir}/src/misc/typephp_main.cc
if [[ ! -f "${typephp_runtime_source}" ]]; then
    fatal_error "TypePHP embedded runtime source is missing: ${typephp_runtime_source}"
fi
generated_sources+=("${typephp_runtime_source}")

wasi_sdk_stamp=${wasi_sdk_dir}/.typephp-wasi-sdk-abi
if [[ ! -f "${wasi_sdk_stamp}" ]] \
    || ! grep -qx 'typephp-wasip2-sdk-abi-v4' "${wasi_sdk_stamp}"; then
    fatal_error \
        "TypePHP WASI SDK is missing or ABI-incompatible: ${wasi_sdk_dir}" \
        "Install the matching PHPX package or set PHPX_HOME to its installation directory."
fi

required_libraries=(libphp.a libphpx.a libgmp.a libgmpxx.a libmpfr.a libmpdec.a libmpdec++.a)
for library in "${required_libraries[@]}"; do
    if [[ ! -f "${wasi_library_dir}/${library}" ]]; then
        fatal_error "TypePHP WASI SDK library is missing: ${wasi_library_dir}/${library}"
    fi
done
required_headers=(
    php/main/php.h
    php/main/php_config.h
    php/Zend/zend_config.h
    php/ext/date/lib/timelib_config.h
    phpx/phpx.h
    phpx/phpx_python.h
    phpx/typephp_helper.h
    zlib.h
    zconf.h
    gmp.h
    mpfr.h
    decimal.hh
)
for header in "${required_headers[@]}"; do
    if [[ ! -f "${wasi_include_dir}/${header}" ]]; then
        fatal_error "TypePHP WASI SDK header is missing: ${wasi_include_dir}/${header}"
    fi
done

compile_flags=(
    -std=c++17
    -O2
    -fwasm-exceptions
    -mllvm -wasm-enable-sjlj
    -mllvm -wasm-use-legacy-eh=false
    -Wno-deprecated-literal-operator
)
include_flags=(
    -I"${wasi_php_include_dir}"
    -I"${wasi_php_include_dir}/main"
    -I"${wasi_php_include_dir}/Zend"
    -I"${wasi_php_include_dir}/TSRM"
    -I"${wasi_php_include_dir}/ext/date/lib"
    -I"${wasi_phpx_include_dir}"
    -I"${wasi_include_dir}"
    -I"${generated_dir}/include"
)

# WIT is application-specific, and its generator is a host build tool found by
# tpc in PATH. It is not linked into PHPX or the resulting component.
binding_objects=()
if [[ "${wasm_mode}" == library ]]; then
    bindgen=${TYPEPHP_WIT_BINDGEN:?TYPEPHP_WIT_BINDGEN is required in library mode}
    if [[ ! -x "${bindgen}" ]]; then
        fatal_error \
            "WASI build tool wit-bindgen is unavailable: ${bindgen}" \
            "Install wit-bindgen-cli 0.60.0 and add it to PATH."
    fi
    bindgen_version=$("${bindgen}" --version 2>/dev/null || true)
    if [[ "${bindgen_version}" != 'wit-bindgen-cli 0.60.0' ]]; then
        fatal_error \
            "WASI build tool wit-bindgen has an incompatible version: ${bindgen_version:-unknown}" \
            "Install wit-bindgen-cli 0.60.0 and add it to PATH."
    fi
    binding_world=${TYPEPHP_WASM_WORLD//-/_}
    "${bindgen}" c \
        --world "${TYPEPHP_WASM_WORLD}" \
        --rename-world "${binding_world}" \
        --out-dir "${interface_dir}" \
        "${interface_wit}"
    generated_sources+=("${interface_dir}/${binding_world}.c")
    binding_objects+=("${interface_dir}/${binding_world}_component_type.o")
fi

generated_objects=()
for source in "${generated_sources[@]}"; do
    if [[ ! -f "${source}" ]]; then
        echo "Generated C++ source file not found: ${source}" >&2
        exit 1
    fi
    source_compile_flags=()
    if [[ "${source}" == "${typephp_runtime_source}" ]]; then
        object=${build_root}/typephp_main.o
        source_compile_flags+=("-DTYPEPHP_PROJECT_NAME=${project_name}")
        if [[ "${wasm_mode}" == library ]]; then
            source_compile_flags+=("-DTYPEPHP_NO_MAIN=1")
        fi
    else
        object=${source%.cc}.o
    fi
    if [[ "${source}" == *.c ]]; then
        "${TYPEPHP_WASI_CC:?TYPEPHP_WASI_CC is required}" -O2 -c "${source}" -o "${object}"
    else
        "${wasi_cxx}" "${compile_flags[@]}" "${source_compile_flags[@]}" "${include_flags[@]}" -I"${interface_dir}" -c "${source}" -o "${object}"
    fi
    generated_objects+=("${object}")
done

link_mode_flags=()
if [[ "${wasm_mode}" == library ]]; then
    link_mode_flags+=(-mexec-model=reactor)
fi

# Every generated object and runtime archive is already built with -O2. Keep
# the final driver invocation optimized as well, but do not let Clang discover
# an arbitrary system wasm-opt: older Binaryen releases cannot parse the Wasm
# exception-reference instructions emitted by the current WASI SDK. Stripping
# linker metadata has a much larger browser startup benefit than another slow
# whole-module optimization pass and does not change runtime semantics.
"${wasi_cxx}" \
    -O2 \
    --no-wasm-opt \
    -std=c++17 \
    -fwasm-exceptions \
    "${generated_objects[@]}" \
    "${binding_objects[@]}" \
    -Wl,--whole-archive \
    "${wasi_library_dir}/libphpx.a" \
    -Wl,--no-whole-archive \
    "${wasi_library_dir}/libphp.a" \
    "${wasi_library_dir}/libmpdec++.a" \
    "${wasi_library_dir}/libmpdec.a" \
    "${wasi_library_dir}/libmpfr.a" \
    "${wasi_library_dir}/libgmpxx.a" \
    "${wasi_library_dir}/libgmp.a" \
    -lwasi-emulated-signal -lsetjmp -lunwind -ldl -lm \
    "${link_mode_flags[@]}" \
    -Wl,--strip-all \
    -Wl,--fatal-warnings \
    -o "${output}"

echo "Built TypePHP/WASI program: ${output}"

if [[ "${TYPEPHP_WASM_BROWSER:-0}" == 1 ]]; then
    # Chrome does not yet load components natively, so Jco lowers the same
    # WASI 0.2 component to core Wasm + ESM.
    jco_bin=${TYPEPHP_JCO:-jco}
    browser_dir=${TYPEPHP_WASM_BROWSER_DIR:-${output%.wasm}.browser}
    mkdir -p "${browser_dir}"
    jco_flags=()
    if "${jco_bin}" transpile --help 2>&1 | grep -q -- '--bindgen-enable-wasm-exnref'; then
        jco_flags+=(--bindgen-enable-wasm-exnref)
    fi
    if ! "${jco_bin}" transpile --help 2>&1 | grep -q -- '--async-wasi-imports'; then
        echo "Jco does not support JSPI-backed asynchronous WASI imports; upgrade Jco" >&2
        exit 1
    fi
    jco_flags+=(--async-mode jspi --async-wasi-imports --async-wasi-exports)
    if [[ "${wasm_mode}" == library ]]; then
        if ! "${jco_bin}" transpile --help 2>&1 | grep -q -- '--async-exports'; then
            fatal_error "Jco does not support JSPI-backed TypePHP exports; upgrade Jco"
        fi
        if [[ ! -s "${interface_async_exports}" ]]; then
            fatal_error "TypePHP did not generate the Jco async export list: ${interface_async_exports}"
        fi
        mapfile -t jco_async_exports < "${interface_async_exports}"
        jco_flags+=(--async-exports "${jco_async_exports[@]}")
    fi
    "${jco_bin}" transpile "${output}" \
        -o "${browser_dir}" \
        --name program \
        --no-nodejs-compat \
        --no-namespaced-exports \
        --instantiation async \
        --base64-cutoff=0 \
        "${jco_flags[@]}"
    echo "Built TypePHP/WASI browser module: ${browser_dir}"
fi

if [[ "${TYPEPHP_WASM_RUN:-0}" == 1 ]]; then
    wasmtime_bin=${TYPEPHP_WASMTIME:-wasmtime}
    XDG_CACHE_HOME=${XDG_CACHE_HOME:-/tmp/typephp-wasmtime-cache} \
        "${wasmtime_bin}" "${output}"
fi

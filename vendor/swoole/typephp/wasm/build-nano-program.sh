#!/usr/bin/env bash

set -euo pipefail

fatal_error() {
    printf 'Fatal error: %s\n' "$1" >&2
    exit 1
}

if [[ $# -ne 4 ]]; then
    echo "Usage: $0 <program.php> <output.wasm|-> <phpx-dir> <tpc-executable>" >&2
    exit 1
fi

caller_dir=${PWD}
input=$1
typephp_compiler=$4
wasm_mode=${TYPEPHP_WASM_MODE:-command}

if [[ "${wasm_mode}" != command ]]; then
    fatal_error "--nano --wasm currently supports command/binary projects only"
fi

if [[ "${input}" != /* ]]; then
    input=${caller_dir}/${input}
fi
input=$(realpath "${input}")

stem=$(basename "${input}" .php)
stem=${stem//[^a-zA-Z0-9_-]/_}
build_root=${TYPEPHP_WASM_PROGRAM_BUILD_DIR:-${caller_dir}/build}
mkdir -p "${build_root}"
build_root=$(cd "${build_root}" && pwd)

if [[ $2 != - ]]; then
    output=$2
    if [[ "${output}" != /* ]]; then
        output=${caller_dir}/${output}
    fi
else
    output=${caller_dir}/${stem}.wasm
fi
mkdir -p "$(dirname "${output}")"

# Keep code generation, parallel scheduling, progress, caching, and linking in
# the normal TypePHP compiler. Nano changes only the runtime sources and link
# inputs; this script only orchestrates WASI component/browser packaging.
TYPEPHP_WASM_INTERNAL_COMPILE=1 "${typephp_compiler}" "${input}" \
    --nano \
    --target-platform wasm32-wasip2 \
    --build-dir "${build_root}" \
    -O2 \
    -o "${output}"

echo "Built TypePHP/Nano WASI program: ${output}"

if [[ "${TYPEPHP_WASM_BROWSER:-0}" == 1 ]]; then
    jco_bin=${TYPEPHP_JCO:-jco}
    browser_dir=${TYPEPHP_WASM_BROWSER_DIR:-${output%.wasm}.browser}
    mkdir -p "${browser_dir}"
    jco_flags=()
    if "${jco_bin}" transpile --help 2>&1 | grep -q -- '--bindgen-enable-wasm-exnref'; then
        jco_flags+=(--bindgen-enable-wasm-exnref)
    fi
    if ! "${jco_bin}" transpile --help 2>&1 | grep -q -- '--async-wasi-imports'; then
        fatal_error "Jco does not support JSPI-backed asynchronous WASI imports; upgrade Jco"
    fi
    jco_flags+=(--async-mode jspi --async-wasi-imports --async-wasi-exports)
    "${jco_bin}" transpile "${output}" \
        -o "${browser_dir}" \
        --name program \
        --no-nodejs-compat \
        --no-namespaced-exports \
        --instantiation async \
        --base64-cutoff=0 \
        "${jco_flags[@]}"
    echo "Built TypePHP/Nano WASI browser module: ${browser_dir}"
fi

if [[ "${TYPEPHP_WASM_RUN:-0}" == 1 ]]; then
    wasmtime_bin=${TYPEPHP_WASMTIME:-wasmtime}
    XDG_CACHE_HOME=${XDG_CACHE_HOME:-/tmp/typephp-wasmtime-cache} \
        "${wasmtime_bin}" "${output}"
fi

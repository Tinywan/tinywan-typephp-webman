#!/usr/bin/env bash

set -euo pipefail

project_dir=$(cd -- "$(dirname -- "$0")/.." && pwd)
build_dir="${project_dir}/build/thirdparty-smoke"

if [[ ! -f "${project_dir}/build/libcompiler-rt-builtins.a" ]]; then
    echo "compiler-rt archive is missing; run make compiler-rt-builtins first" >&2
    exit 1
fi
for symbol in __udivti3 __udivmodti4 __divti3 __modti3 __multi3; do
    if ! nm -g --defined-only "${project_dir}/build/libcompiler-rt-builtins.a" \
            | grep -qE "[[:space:]]${symbol}$"; then
        echo "compiler-rt archive does not define ${symbol}" >&2
        exit 1
    fi
done
echo "compiler-rt builtins archive: OK"

toybox_source="${project_dir}/thirdparty/toybox"
toybox_config="${build_dir}/toybox.config"
mkdir -p "${build_dir}"
KCONFIG_ALLCONFIG="${project_dir}/tools/toybox-miniconfig" \
KCONFIG_CONFIG="${toybox_config}" \
CFLAGS="-O2 -fno-stack-protector -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=0" \
    make -C "${toybox_source}" allnoconfig toybox

test "$("${toybox_source}/toybox" | tr ' ' '\n' | sed '/^$/d' | sort | tr '\n' ' ')" \
    = "cat date echo pwd uname "
test "$("${toybox_source}/toybox" echo 'Toybox smoke: OK')" = "Toybox smoke: OK"
"${toybox_source}/toybox" date -u +%Y-%m-%d >/dev/null
"${toybox_source}/toybox" uname -s >/dev/null

toybox_symbols="${build_dir}/toybox-undefined-symbols.txt"
nm -u "${toybox_source}/generated/unstripped/toybox" \
    | awk '{ print $NF }' | sed 's/@.*//' | sort -u > "${toybox_symbols}"
if grep -Eq '^(fork|vfork|execv|execve|execvp|posix_spawn)$' "${toybox_symbols}"; then
    echo "the selected Toybox applets unexpectedly require process creation" >&2
    exit 1
fi
echo "Toybox selected applets build: OK"
if grep -qx ioctl "${toybox_symbols}"; then
    echo "Toybox ABI audit: common runtime imports the supported ioctl entry point"
else
    echo "Toybox ABI audit: common runtime does not import ioctl"
fi
echo "Toybox ABI report: ${toybox_symbols}"

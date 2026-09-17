#!/usr/bin/env sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
version=23.1.1
tag="llvmorg-${version}"
target="${project_dir}/thirdparty/compiler-rt"
marker="${target}/.typephp-source-${version}"
manifest="${project_dir}/tools/compiler-rt-builtins-files.sha256"
base_url="https://raw.githubusercontent.com/llvm/llvm-project/${tag}/compiler-rt"

if [ -f "${marker}" ] && [ -f "${target}/lib/builtins/udivti3.c" ]; then
    exit 0
fi

temporary=$(mktemp -d "${TMPDIR:-/tmp}/typephp-os-compiler-rt.XXXXXX")
trap 'rm -rf "${temporary}"' EXIT HUP INT TERM
staging="${temporary}/installed"

echo "Downloading compiler-rt builtins ${version}"
while read -r expected relative; do
    [ -n "${relative}" ] || continue
    destination="${staging}/${relative}"
    mkdir -p "$(dirname -- "${destination}")"
    curl -L --fail --retry 3 --silent --show-error \
        -o "${destination}" "${base_url}/${relative}"
    printf '%s  %s\n' "${expected}" "${destination}" | sha256sum -c -
done < "${manifest}"

mkdir -p "${project_dir}/thirdparty"
rm -rf "${target}"
mv "${staging}" "${target}"
printf '%s\n' "${tag}" > "${marker}"
echo "Installed compiler-rt builtins ${version} in ${target}"

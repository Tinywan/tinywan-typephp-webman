#!/usr/bin/env sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
version=0.8.7
archive_sha256=e328a1d59b94748b111e022bca6a9d2fc0481fb57d23c87d90f394b559d4f062
target="${project_dir}/thirdparty/openlibm"
marker="${target}/.typephp-source-${version}"
manifest="${project_dir}/tools/openlibm-files.txt"

if [ -f "${marker}" ] && [ -f "${target}/src/e_sqrt.c" ]; then
    exit 0
fi

temporary=$(mktemp -d "${TMPDIR:-/tmp}/typephp-os-openlibm.XXXXXX")
trap 'rm -rf "${temporary}"' EXIT HUP INT TERM
archive="${temporary}/openlibm.tar.gz"
source_dir="${temporary}/openlibm-${version}"
staging="${temporary}/installed"

echo "Downloading OpenLibm v${version}"
curl -L --fail --retry 3 --silent --show-error \
    -o "${archive}" \
    "https://github.com/JuliaMath/openlibm/archive/refs/tags/v${version}.tar.gz"
echo "${archive_sha256}  ${archive}" | sha256sum -c -
tar -xzf "${archive}" -C "${temporary}"

while IFS= read -r relative; do
    [ -n "${relative}" ] || continue
    mkdir -p "${staging}/$(dirname -- "${relative}")"
    cp "${source_dir}/${relative}" "${staging}/${relative}"
done < "${manifest}"

mkdir -p "${project_dir}/thirdparty"
rm -rf "${target}"
mv "${staging}" "${target}"
printf '%s\n' "${archive_sha256}" > "${marker}"
echo "Installed OpenLibm v${version} in ${target}"

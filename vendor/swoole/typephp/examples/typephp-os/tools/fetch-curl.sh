#!/usr/bin/env sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
version=8.22.0
archive_sha256=f7ef3ae8a22e521f289803fe93543eb64c329b58aa73a9e224dfd915a2a5f4f7
target="${project_dir}/thirdparty/curl"
marker="${target}/.typephp-source-${version}"

if [ -f "${marker}" ] && [ -f "${target}/lib/http.c" ]; then
    exit 0
fi

temporary=$(mktemp -d "${TMPDIR:-/tmp}/typephp-os-curl.XXXXXX")
trap 'rm -rf "${temporary}"' EXIT HUP INT TERM
archive="${temporary}/curl.tar.xz"
source_dir="${temporary}/curl-${version}"

echo "Downloading curl ${version}"
curl -L --fail --retry 3 --silent --show-error \
    -o "${archive}" "https://curl.se/download/curl-${version}.tar.xz"
echo "${archive_sha256}  ${archive}" | sha256sum -c -
tar -xJf "${archive}" -C "${temporary}"

mkdir -p "${project_dir}/thirdparty"
rm -rf "${target}"
mv "${source_dir}" "${target}"
printf '%s\n' "${archive_sha256}" > "${marker}"
echo "Installed curl ${version} in ${target}"

#!/usr/bin/env sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
version=3.5.8
archive_sha256=a8f84a39918ec6415ce765d9b429d313ba97b8143169c172e734b9514464f5b2
target="${project_dir}/thirdparty/openssl"
marker="${target}/.typephp-source-${version}"

if [ -f "${marker}" ] && [ -f "${target}/Configure" ]; then
    exit 0
fi

temporary=$(mktemp -d "${TMPDIR:-/tmp}/typephp-os-openssl.XXXXXX")
trap 'rm -rf "${temporary}"' EXIT HUP INT TERM
archive="${temporary}/openssl.tar.gz"
source_dir="${temporary}/openssl-${version}"

echo "Downloading OpenSSL ${version} LTS"
curl -L --fail --retry 3 --silent --show-error \
    -o "${archive}" \
    "https://github.com/openssl/openssl/releases/download/openssl-${version}/openssl-${version}.tar.gz"
echo "${archive_sha256}  ${archive}" | sha256sum -c -
tar -xzf "${archive}" -C "${temporary}"

mkdir -p "${project_dir}/thirdparty"
rm -rf "${target}"
mv "${source_dir}" "${target}"
printf '%s\n' "${archive_sha256}" > "${marker}"
echo "Installed OpenSSL ${version} in ${target}"

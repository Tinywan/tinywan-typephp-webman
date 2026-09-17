#!/usr/bin/env sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
version=1.70.0
archive_sha256=e05cb1388eaca3830aded4ccf20044b6e1ac1a61411dcca11b0437c4285c8bc2
target="${project_dir}/thirdparty/nghttp2"
marker="${target}/.typephp-source-${version}"

if [ -f "${marker}" ] && [ -f "${target}/lib/nghttp2_session.c" ]; then
    exit 0
fi

temporary=$(mktemp -d "${TMPDIR:-/tmp}/typephp-os-nghttp2.XXXXXX")
trap 'rm -rf "${temporary}"' EXIT HUP INT TERM
archive="${temporary}/nghttp2.tar.xz"
source_dir="${temporary}/nghttp2-${version}"

echo "Downloading nghttp2 ${version}"
curl -L --fail --retry 3 --silent --show-error \
    -o "${archive}" \
    "https://github.com/nghttp2/nghttp2/releases/download/v${version}/nghttp2-${version}.tar.xz"
echo "${archive_sha256}  ${archive}" | sha256sum -c -
tar -xJf "${archive}" -C "${temporary}"

mkdir -p "${project_dir}/thirdparty"
rm -rf "${target}"
mv "${source_dir}" "${target}"
printf '%s\n' "${archive_sha256}" > "${marker}"
echo "Installed nghttp2 ${version} in ${target}"

#!/usr/bin/env sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
version=2.2.1
tag=STABLE-2_2_1_RELEASE
archive_sha256=ce0b7461c0ad9602c376f0bf07c5eb7253b48c7bf66f011c6bf3e2a96731c539
target="${project_dir}/thirdparty/lwip"
marker="${target}/.typephp-source-${version}"

if [ -f "${marker}" ] && [ -f "${target}/src/core/tcp.c" ]; then
    exit 0
fi

temporary=$(mktemp -d "${TMPDIR:-/tmp}/typephp-os-lwip.XXXXXX")
trap 'rm -rf "${temporary}"' EXIT HUP INT TERM
archive="${temporary}/lwip.tar.gz"
source_dir="${temporary}/lwip-${tag}"

echo "Downloading lwIP ${version}"
curl -L --fail --retry 3 --silent --show-error \
    -o "${archive}" \
    "https://github.com/lwip-tcpip/lwip/archive/refs/tags/${tag}.tar.gz"
echo "${archive_sha256}  ${archive}" | sha256sum -c -
tar -xzf "${archive}" -C "${temporary}"

mkdir -p "${project_dir}/thirdparty"
rm -rf "${target}"
mv "${source_dir}" "${target}"
printf '%s\n' "${archive_sha256}" > "${marker}"
echo "Installed lwIP ${version} in ${target}"

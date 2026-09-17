#!/usr/bin/env sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
version=0.8.14
commit=b7ec52ac35e075caffca5d330995d44e8dbfc8c3
archive_sha256=827e4cdfd69f5da973e00e2a59b30b3c9857fb7fae74c362fd0b4f96be7929b0
target="${project_dir}/thirdparty/toybox"
marker="${target}/.typephp-source-${version}"

if [ -f "${marker}" ] && [ -f "${target}/Makefile" ]; then
    exit 0
fi

temporary=$(mktemp -d "${TMPDIR:-/tmp}/typephp-os-toybox.XXXXXX")
trap 'rm -rf "${temporary}"' EXIT HUP INT TERM
archive="${temporary}/toybox.tar.gz"
staging="${temporary}/installed"

echo "Downloading Toybox ${version} (${commit})"
curl -L --fail --retry 3 --silent --show-error -o "${archive}" \
    "https://landley.net/toybox/downloads/toybox-${version}.tar.gz"
printf '%s  %s\n' "${archive_sha256}" "${archive}" | sha256sum -c -
mkdir -p "${staging}"
tar -xzf "${archive}" -C "${staging}" --strip-components=1
test -f "${staging}/LICENSE"

mkdir -p "${project_dir}/thirdparty"
rm -rf "${target}"
mv "${staging}" "${target}"
printf '%s\n' "${commit}" > "${marker}"
echo "Installed Toybox ${version} in ${target}"

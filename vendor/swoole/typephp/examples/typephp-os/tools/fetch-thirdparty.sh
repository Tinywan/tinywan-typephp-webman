#!/usr/bin/env sh

set -eu

tools_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

"${tools_dir}/fetch-openlibm.sh"
"${tools_dir}/fetch-compiler-rt-builtins.sh"
"${tools_dir}/fetch-toybox.sh"
"${tools_dir}/fetch-lwip.sh"
"${tools_dir}/fetch-openssl.sh"
"${tools_dir}/fetch-nghttp2.sh"
"${tools_dir}/fetch-curl.sh"

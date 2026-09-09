#!/usr/bin/env bash

set -euo pipefail

project_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
android_sdk=${ANDROID_SDK_ROOT:-${ANDROID_HOME:-/home/swoole/soft/android-sdk}}
adb=${android_sdk}/platform-tools/adb
apk=${project_dir}/dist/typephp-android-hello-debug.apk

[[ -x "${adb}" ]] || { echo "adb was not found: ${adb}" >&2; exit 1; }
[[ -f "${apk}" ]] || { echo "Build the APK first with ./build-app.sh" >&2; exit 1; }

"${adb}" install -r "${apk}"
"${adb}" shell am start -n com.swoole.typephp/.MainActivity

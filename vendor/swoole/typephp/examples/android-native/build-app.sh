#!/usr/bin/env bash

set -euo pipefail

project_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
compiler_root=$(cd "${project_dir}/../.." && pwd)
android_sdk=${ANDROID_SDK_ROOT:-${ANDROID_HOME:-/home/swoole/soft/android-sdk}}
android_ndk=${ANDROID_NDK_HOME:-${ANDROID_NDK_ROOT:-/home/swoole/soft/android-ndk-r27d}}
phpx_root=${PHPX_HOME:-$(cd "${compiler_root}/../phpx" && pwd)}
phpx_android_sdk=${PHPX_ANDROID_SDK_DIR:-${phpx_root}/android/arm64-v8a}

if [[ ! -d "${android_sdk}/build-tools" || ! -f "${android_sdk}/platforms/android-36/android.jar" ]]; then
    echo "Android SDK Platform 36 and Build Tools are required: ${android_sdk}" >&2
    exit 1
fi
if [[ ! -f "${android_ndk}/build/cmake/android.toolchain.cmake" ]]; then
    echo "Android NDK is required: ${android_ndk}" >&2
    exit 1
fi
if [[ ! -f "${phpx_android_sdk}/.typephp-android-sdk-abi" ]]; then
    echo "TypePHP Android SDK is required: ${phpx_android_sdk}" >&2
    exit 1
fi

build_tools_version=$(find "${android_sdk}/build-tools" -mindepth 1 -maxdepth 1 -type d \
    -printf '%f\n' | sort -V | tail -n 1)
build_tools=${android_sdk}/build-tools/${build_tools_version}
android_jar=${android_sdk}/platforms/android-36/android.jar
build_dir=${project_dir}/build/apk
classes_dir=${build_dir}/classes
package_dir=${build_dir}/package
resources_dir=${build_dir}/resources
compiled_resources_dir=${build_dir}/compiled-resources
dist_dir=${project_dir}/dist

mkdir -p \
    "${classes_dir}" \
    "${package_dir}/lib/arm64-v8a" \
    "${resources_dir}/drawable-nodpi" \
    "${compiled_resources_dir}" \
    "${dist_dir}"
cp -p "${compiler_root}/examples/objective-c-macos/ios-assets/AppIcon-180.png" \
    "${resources_dir}/drawable-nodpi/typephp_icon.png"

ANDROID_NDK_HOME="${android_ndk}" \
PHPX_HOME="${phpx_root}" \
PHPX_ANDROID_SDK_DIR="${phpx_android_sdk}" \
php "${compiler_root}/bin/tpc.php" "${project_dir}/android.yml" --no-progress

javac -source 8 -target 8 -encoding UTF-8 \
    -bootclasspath "${android_jar}" \
    -d "${classes_dir}" \
    "${project_dir}/java/com/swoole/typephp/MainActivity.java"

mapfile -t class_files < <(find "${classes_dir}" -type f -name '*.class' -print)
"${build_tools}/d8" --lib "${android_jar}" --min-api 24 \
    --output "${package_dir}" "${class_files[@]}"

unsigned_apk=${build_dir}/typephp-android-hello-unsigned.apk
aligned_apk=${build_dir}/typephp-android-hello-aligned.apk
signed_apk=${dist_dir}/typephp-android-hello-debug.apk
"${build_tools}/aapt2" compile \
    --dir "${resources_dir}" \
    -o "${compiled_resources_dir}"
mapfile -t compiled_resources < <(find "${compiled_resources_dir}" -type f -name '*.flat' -print)
"${build_tools}/aapt2" link \
    -I "${android_jar}" \
    --manifest "${project_dir}/AndroidManifest.xml" \
    --min-sdk-version 24 \
    --target-sdk-version 36 \
    --version-code 1 \
    --version-name 0.1.0 \
    "${compiled_resources[@]}" \
    -o "${unsigned_apk}"

cp -p "${dist_dir}/typephp_android_hello.so" \
    "${package_dir}/lib/arm64-v8a/libtypephp_android_hello.so"
(
    cd "${package_dir}"
    zip -q -0 "${unsigned_apk}" lib/arm64-v8a/libtypephp_android_hello.so
    zip -q "${unsigned_apk}" classes.dex
)
"${build_tools}/zipalign" -f -P 16 4 "${unsigned_apk}" "${aligned_apk}"

debug_keystore=${build_dir}/debug.keystore
if [[ ! -f "${debug_keystore}" ]]; then
    keytool -genkeypair -noprompt \
        -keystore "${debug_keystore}" \
        -storepass android \
        -alias androiddebugkey \
        -keypass android \
        -dname 'CN=Android Debug,O=TypePHP,C=CN' \
        -keyalg RSA \
        -validity 10000 >/dev/null
fi
"${build_tools}/apksigner" sign \
    --ks "${debug_keystore}" \
    --ks-pass pass:android \
    --key-pass pass:android \
    --out "${signed_apk}" \
    "${aligned_apk}"

"${build_tools}/zipalign" -c -P 16 4 "${signed_apk}"
"${build_tools}/apksigner" verify --verbose "${signed_apk}"
echo "Built signed APK: ${signed_apk}"

#!/bin/sh

set -eu

if [ "$(uname -s)" != Darwin ]; then
    echo 'This script must run on macOS.' >&2
    exit 1
fi

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
repo_dir=$(CDPATH= cd -- "$project_dir/../.." && pwd)
binary=${TYPEPHP_TASKBOARD_BINARY:-$repo_dir/typephp_taskboard}
bundle="$project_dir/dist/TypePHP Taskboard.app"
frameworks="$bundle/Contents/Frameworks"
app_binary="$bundle/Contents/MacOS/typephp_taskboard"

: "${PHP_HOME:?Set PHP_HOME to the PHP installation used to build the executable}"
: "${PHPX_HOME:?Set PHPX_HOME to the PHPX installation used to build the executable}"

if [ ! -x "$binary" ]; then
    echo "Missing executable: $binary" >&2
    exit 1
fi
if [ ! -f "$PHP_HOME/lib/libphp.dylib" ] || [ ! -f "$PHPX_HOME/lib/libphpx.dylib" ]; then
    echo 'Matching libphp.dylib and libphpx.dylib are required.' >&2
    exit 1
fi

deploy_tool=${MACDEPLOYQT:-}
if [ -z "$deploy_tool" ]; then
    deploy_tool=$(command -v macdeployqt || true)
fi
if [ -z "$deploy_tool" ] && [ -x /opt/homebrew/bin/macdeployqt ]; then
    deploy_tool=/opt/homebrew/bin/macdeployqt
fi
if [ -z "$deploy_tool" ]; then
    echo 'macdeployqt is required; install Qt for macOS first.' >&2
    exit 1
fi

mkdir -p "$project_dir/dist"
if [ -d "$bundle" ]; then
    rm -r "$bundle"
fi
mkdir -p "$bundle/Contents/MacOS" "$frameworks"
cp "$project_dir/Info.macos.plist" "$bundle/Contents/Info.plist"
cp "$binary" "$app_binary"
cp "$PHP_HOME/lib/libphp.dylib" "$frameworks/libphp.dylib"
cp "$PHPX_HOME/lib/libphpx.dylib" "$frameworks/libphpx.dylib"

# macdeployqt copies Qt frameworks, the Cocoa plugin and linked libraries.
"$deploy_tool" "$bundle" -always-overwrite -no-codesign

# Use the bundled PHP runtime when Finder starts the app without shell variables.
install_name_tool -change '@rpath/libphp.dylib' \
    '@executable_path/../Frameworks/libphp.dylib' "$app_binary"
install_name_tool -change '@rpath/libphpx.dylib' \
    '@executable_path/../Frameworks/libphpx.dylib' "$app_binary"

# Local ad-hoc signing is sufficient for testing from Finder.
codesign --force --deep --sign - "$bundle"
plutil -lint "$bundle/Contents/Info.plist"
codesign --verify --deep --strict "$bundle"
echo "Created $bundle"

#!/bin/sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
executable="$project_dir/typephp_macos_hello"
bundle="$project_dir/dist/TypePHP macOS Hello.app"

if [ ! -x "$executable" ]; then
    echo "Missing $executable; build project.yml first." >&2
    exit 1
fi

mkdir -p "$bundle/Contents/MacOS"
cp "$project_dir/Info.plist" "$bundle/Contents/Info.plist"
cp "$executable" "$bundle/Contents/MacOS/typephp-macos-hello"
codesign --force --deep --sign - "$bundle"

echo "Created $bundle"

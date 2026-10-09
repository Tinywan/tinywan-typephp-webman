#!/bin/sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
executable="$project_dir/typephp_ios_simulator_hello"
bundle="$project_dir/dist/TypePHP iOS Simulator Hello.app"

if [ ! -x "$executable" ]; then
    echo "Missing $executable; build ios-simulator.yml first." >&2
    exit 1
fi

mkdir -p "$bundle"
cp "$project_dir/Info-iOS.plist" "$bundle/Info.plist"
plutil -replace CFBundleExecutable -string typephp_ios_simulator_hello "$bundle/Info.plist"
plutil -replace CFBundleIdentifier -string org.swoole.typephp.ios-simulator-hello "$bundle/Info.plist"
cp "$executable" "$bundle/typephp_ios_simulator_hello"
cp "$project_dir"/ios-assets/AppIcon*.png "$bundle/"
codesign --force --sign - --timestamp=none "$bundle"
codesign --verify --deep --strict "$bundle"

echo "Created $bundle"

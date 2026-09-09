#!/bin/sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
executable="$project_dir/typephp_ios_hello"
bundle="$project_dir/dist/TypePHP iOS Hello.app"
profile=${TYPEPHP_IOS_PROVISIONING_PROFILE:-}
identity=${TYPEPHP_IOS_CODE_SIGN_IDENTITY:-}
bundle_identifier=${TYPEPHP_IOS_BUNDLE_IDENTIFIER:-}

if [ ! -x "$executable" ]; then
    echo "Missing $executable; build ios.yml first." >&2
    exit 1
fi

if [ -z "$profile" ] || [ ! -f "$profile" ]; then
    echo "Set TYPEPHP_IOS_PROVISIONING_PROFILE to an iOS Development .mobileprovision file." >&2
    exit 1
fi

if [ -z "$identity" ]; then
    echo "Set TYPEPHP_IOS_CODE_SIGN_IDENTITY to an installed Apple Development identity." >&2
    exit 1
fi

profile_plist=$(mktemp -t typephp-ios-profile.XXXXXX)
entitlements=$(mktemp -t typephp-ios-entitlements.XXXXXX)
trap 'rm -f "$profile_plist" "$entitlements"' EXIT HUP INT TERM

security cms -D -i "$profile" -o "$profile_plist"
plutil -extract Entitlements xml1 -o "$entitlements" "$profile_plist"

rm -rf "$bundle"
mkdir -p "$bundle"
cp "$project_dir/Info-iOS.plist" "$bundle/Info.plist"
if [ -n "$bundle_identifier" ]; then
    plutil -replace CFBundleIdentifier -string "$bundle_identifier" "$bundle/Info.plist"
fi
cp "$executable" "$bundle/typephp_ios_hello"
cp "$profile" "$bundle/embedded.mobileprovision"
cp "$project_dir"/ios-assets/AppIcon*.png "$bundle/"

codesign \
    --force \
    --sign "$identity" \
    --entitlements "$entitlements" \
    --timestamp=none \
    "$bundle"
codesign --verify --deep --strict --verbose=2 "$bundle"

echo "Created and signed $bundle"
echo "Install it with: xcrun devicectl device install app --device <device-id> '$bundle'"

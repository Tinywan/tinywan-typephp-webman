# TypePHP Apple native GUI examples

This directory contains matching macOS AppKit and iPhoneOS UIKit applications.
Both targets reuse `php-src/application.php`: TypePHP defines the window layout,
application state and click behavior, while Objective-C++ only exposes small
native UI operations. Clicking the native button returns a control ID to
TypePHP, which updates the status label.

## macOS

Requirements:

- macOS with Xcode Command Line Tools
- PHP 8.4 or 8.5 built with the embed SAPI (`libphp.dylib`)
- a matching PHPX build
- `PHPX_HOME` pointing to the PHPX source tree

Build from the TypePHP repository root:

```sh
export PHPX_HOME=/path/to/phpx
php bin/tpc.php examples/objective-c-macos/project.yml --no-progress
```

Run the native executable:

```sh
./examples/objective-c-macos/typephp_macos_hello
```

To create a standard `.app` bundle that can be launched from Finder:

```sh
sh examples/objective-c-macos/package-app.sh
open "examples/objective-c-macos/dist/TypePHP macOS Hello.app"
```

## iPhoneOS (physical iPhone)

The iPhone build is cross-compiled on macOS. It requires full Xcode (Command
Line Tools alone do not contain the iPhoneOS SDK), an Apple Development signing
identity, a provisioning profile for `org.swoole.typephp.ios-hello`, and an
`iphoneos-arm64` TypePHP SDK with this layout:

```text
<sdk>/
├── include/php/...
├── lib/
│   ├── libphp.a
│   ├── libphpx.a
│   ├── libgmp.a
│   ├── libgmpxx.a
│   └── libmpfr.a
└── .typephp-ios-sdk-abi
```

The PHP and every third-party archive in this prefix must be built for
`arm64-apple-ios`; a macOS/Homebrew archive cannot be linked into an iPhoneOS
binary. Apple system libraries and UIKit/Foundation remain SDK frameworks and
are linked by Xcode rather than copied into `libphp.a`.

After selecting full Xcode, verify the SDK:

```sh
sudo xcode-select --switch /Applications/Xcode.app/Contents/Developer
xcrun --sdk iphoneos --show-sdk-path
```

Build the SDK with swoole-cli. PHP source updates must go through
`sync-source-code.php`; this also validates and synchronizes the generated Zend
parser/scanner sources from the official php.net release archive:

```sh
cd /path/to/swoole-cli
php sync-source-code.php --action run
php prepare.php @iphoneos-arm64 --with-parallel-jobs=8
./make.sh all-library
./make.sh config
./make.sh libphp
./make.sh phpx
./make.sh sdk
```

The installed SDK is under
`thirdparty/phpx/ios/iphoneos-arm64`. The `php-version` major in `ios.yml` must
match `sapi/PHP-VERSION.conf` used by swoole-cli. Build the executable from the
TypePHP repository root; `PHPX_HOME` selects both PHPX sources and the target
SDK:

```sh
export PHPX_HOME=/path/to/swoole-cli/thirdparty/phpx
php bin/tpc.php examples/objective-c-macos/ios.yml --no-progress
```

The compiler produces `examples/objective-c-macos/typephp_ios_hello`. Package
and sign it with a development provisioning profile and identity installed in
the login keychain:

```sh
export TYPEPHP_IOS_PROVISIONING_PROFILE=/path/to/profile.mobileprovision
export TYPEPHP_IOS_CODE_SIGN_IDENTITY='Apple Development: Your Name (TEAMID)'
# Set this when the profile uses a different identifier than the example.
export TYPEPHP_IOS_BUNDLE_IDENTIFIER='your.provisioned.bundle.identifier'
sh examples/objective-c-macos/package-ios-app.sh
```

The package includes iPhone icon sizes generated from the repository's
`swoole-logo.svg`. To regenerate them after changing the logo, install FFmpeg
and run:

```sh
sh examples/objective-c-macos/generate-ios-icons.sh
```

Find the connected iPhone and install the bundle:

```sh
xcrun devicectl list devices
xcrun devicectl device install app \
    --device <device-id> \
    'examples/objective-c-macos/dist/TypePHP iOS Hello.app'
```

The `.mm` bridges are deliberately thin. AppKit/UIKit own native controls and
deliver events, but the shared TypePHP class owns the application behavior.

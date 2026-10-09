# TypePHP Apple native GUI examples

This directory contains matching macOS AppKit and iPhoneOS UIKit applications.
Both targets reuse `php-src/application.php`: TypePHP defines the window layout,
application state and click behavior, while Objective-C++ only exposes small
native UI operations. Clicking the native button returns a control ID to
TypePHP, which updates the status label.

All three Apple targets support Nano mode. The `tpc.php` compiler itself still
runs dynamically on the host PHP and loads its Composer dependencies; `--nano`
only changes the generated native application. A Nano application compiles the
selected php-nano and PHPX sources directly and does not link a host `libphp` or
an iOS PHP/PHPX SDK archive.

## macOS

Requirements:

- macOS with Xcode Command Line Tools
- PHP 8.4 or 8.5 CLI for running `tpc.php`
- the compiler Composer dependencies, including php-nano and PHPX sources
- `PHPX_HOME` pointing to the PHPX source tree

Build from the TypePHP repository root:

```sh
export PHPX_HOME=/path/to/phpx
php bin/tpc.php examples/apple-native/project.yml --nano --no-progress
```

Run the native executable:

```sh
./examples/apple-native/typephp_macos_hello
```

To create a standard `.app` bundle that can be launched from Finder:

```sh
sh examples/apple-native/package-app.sh
open "examples/apple-native/dist/TypePHP macOS Hello.app"
```

## iPhoneOS (physical iPhone)

For a Nano build, only full Xcode and the Composer source dependencies are
needed for compilation; the dedicated `iphoneos-arm64` PHP/PHPX SDK described
below is required only by the non-Nano build:

```sh
export PHPX_HOME=/path/to/phpx
sh examples/apple-native/build-ios.sh device --nano
```

Packaging and installing on a physical iPhone still requires a valid Apple
Development identity and provisioning profile.

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
php bin/tpc.php examples/apple-native/ios.yml --no-progress
```

The compiler produces `examples/apple-native/typephp_ios_hello`. Package
and sign it with a development provisioning profile and identity installed in
the login keychain:

```sh
export TYPEPHP_IOS_PROVISIONING_PROFILE=/path/to/profile.mobileprovision
export TYPEPHP_IOS_CODE_SIGN_IDENTITY='Apple Development: Your Name (TEAMID)'
# Set this when the profile uses a different identifier than the example.
export TYPEPHP_IOS_BUNDLE_IDENTIFIER='your.provisioned.bundle.identifier'
sh examples/apple-native/package-ios-app.sh
```

The package includes iPhone icon sizes generated from the repository's
`swoole-logo.svg`. To regenerate them after changing the logo, install FFmpeg
and run:

```sh
sh examples/apple-native/generate-ios-icons.sh
```

Find the connected iPhone and install the bundle:

```sh
xcrun devicectl list devices
xcrun devicectl device install app \
    --device <device-id> \
    'examples/apple-native/dist/TypePHP iOS Hello.app'
```

## iOS Simulator (Apple silicon)

Nano mode does not need simulator PHP/PHPX archives. On Apple silicon, build,
package, install and launch it with:

```sh
export PHPX_HOME=/path/to/phpx
sh examples/apple-native/build-ios.sh simulator --nano
xcrun simctl install booted 'examples/apple-native/dist/TypePHP iOS Simulator Hello.app'
xcrun simctl launch booted org.swoole.typephp.ios-simulator-hello
```

The separate simulator SDK described below is required only by the non-Nano
build.

The simulator needs archives compiled for `arm64-apple-ios-simulator`.
The physical iPhone SDK above cannot be reused. Build the separate runtime
and PHPX SDK on the Mac:

```sh
cd /path/to/swoole-cli
php prepare.php @iphonesimulator-arm64 --with-parallel-jobs=8
./make.sh all-library
./make.sh config
./make.sh libphp
bash sapi/scripts/package-php-runtime-layer.sh iphonesimulator-arm64

# Copy the packaged runtime's include/ and lib/ trees into this prefix.
mkdir -p /path/to/phpx/ios/iphonesimulator-arm64
cp -R runtime-layer/php-runtime-layer_*_iphonesimulator-arm64/include \
      runtime-layer/php-runtime-layer_*_iphonesimulator-arm64/lib \
      /path/to/phpx/ios/iphonesimulator-arm64/
cp runtime-layer/php-runtime-layer_*_iphonesimulator-arm64/.typephp-php-runtime-abi \
   /path/to/phpx/ios/iphonesimulator-arm64/
cp var/iphonesimulator-arm64/deps/gmp/lib/libgmp.a \
   var/iphonesimulator-arm64/deps/gmp/lib/libgmpxx.a \
   var/iphonesimulator-arm64/deps/mpfr/lib/libmpfr.a \
   /path/to/phpx/ios/iphonesimulator-arm64/lib/
cd /path/to/phpx
./ios/build.sh --platform simulator
```

Build and install the app with the simulator SDK:

```sh
cd /path/to/typephp
export PHPX_HOME=/path/to/phpx
sh examples/apple-native/build-ios.sh simulator
xcrun simctl install booted 'examples/apple-native/dist/TypePHP iOS Simulator Hello.app'
xcrun simctl launch booted org.swoole.typephp.ios-simulator-hello
```

The simulator bundle uses ad hoc signing and does not need a provisioning
profile or a physical iPhone.
The same source files are used for both targets. Select `device` instead of
`simulator` in `build-ios.sh` to build the physical iPhone executable. Set
`TYPEPHP_IOS_PROVISIONING_PROFILE` and `TYPEPHP_IOS_CODE_SIGN_IDENTITY` to
package and sign the device app in the same command.

The `.mm` bridges are deliberately thin. AppKit/UIKit own native controls and
deliver events, but the shared TypePHP class owns the application behavior.

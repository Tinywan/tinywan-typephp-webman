# TypePHP Android native app

This example compiles PHP into a self-contained Android `arm64-v8a` shared
library and packages a signed APK using the Android SDK command-line tools. The
TypePHP code owns the control layout, name input, click counter, reset behavior,
and event dispatch. Java only supplies the Activity lifecycle and a generic
native View bridge. It does not require Gradle or Android Studio.

Prerequisites:

- Android SDK Platform/Build Tools 36
- Android NDK r27 or newer
- JDK with `javac` and `keytool`
- the PHPX Android SDK produced by `phpx/sdk/build-native.sh`

Build:

```sh
export ANDROID_SDK_ROOT=/home/swoole/soft/android-sdk
export ANDROID_NDK_HOME=/home/swoole/soft/android-ndk-r27d
export PHPX_ANDROID_SDK_DIR=/path/to/android-arm64-v8a-sdk
./build-app.sh
```

The result is `dist/typephp-android-hello-debug.apk`. With an arm64 Android
device connected and USB debugging enabled, install and launch it using:

```sh
./install-app.sh
```

# lib-demo

This example compiles TypePHP code into a shared library and exposes a stable C ABI.

Build it from the repository root:

```sh
php bin/tpc.php examples/lib-demo/project.yml --no-progress
```

The resulting library uses the configured project name: currently `libdemo.so` on Linux, `libdemo.dylib` on macOS, and `demo.dll` on Windows. Set `output: demo.so` in `project.yml` to use an exact output filename. Its public API is declared in `include/typephp_lib_demo.h`.

On Linux, validate the library can be linked and called:

```sh
g++ -std=c++17 examples/lib-demo/smoke-test.cc -Iexamples/lib-demo \
  ./libdemo.so -Wl,-rpath,'$ORIGIN' -o lib-demo-smoke
./lib-demo-smoke
```

`typephp_lib_demo_add()` initializes the embedded PHP runtime on its first call. The runtime remains process-global for the lifetime of the library; do not call it concurrently with the first invocation.

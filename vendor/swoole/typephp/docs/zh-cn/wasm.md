## 编译

```shell
./tpc --wasm test.php
```

编译成功后默认只生成可由 Wasmtime 执行的 WASI 0.2 Component `test.wasm`。WASI 0.1 不受支持。
生成的 C++ 源码默认写入 `build/`，也可以通过 `--build-dir <directory>` 指定。

## 执行

```shell
wasmtime test.wasm
```

## Chrome

```shell
./tpc --wasm=browser test.php
```

浏览器模式额外生成 `test.browser/` Jco 模块并要求 `jco` 位于 `PATH`。完整浏览器 Demo 位于仓库 `examples/wasm-hello/`，并使用 `wasm: browser` 的 `project.yml` 构建。TypePHP 在专用 Worker 中执行；默认文件系统驻留内存，可显式启用 OPFS 快照持久化。网络 socket、进程、shell 和信号在 WASI 目标下明确不支持。

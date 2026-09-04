## Compilation

```shell
./tpc --wasm test.php
```

After a successful compilation, by default only the WASI 0.2 Component `test.wasm` executable by Wasmtime is generated. WASI 0.1 is not supported.
The generated C++ source is written to `build/` by default, and can also be specified via `--build-dir <directory>`.

## Execution

```shell
wasmtime test.wasm
```

## Chrome

```shell
./tpc --wasm=browser test.php
```

Browser mode additionally generates the `test.browser/` Jco module and requires `jco` to be on the `PATH`. The full browser demo is located in the repository's `examples/wasm-hello/`, and is built using the `project.yml` with `wasm: browser`. TypePHP executes in a dedicated Worker; the default filesystem resides in memory, and OPFS snapshot persistence can be explicitly enabled. Network sockets, processes, shell, and signals are explicitly not supported under the WASI target.

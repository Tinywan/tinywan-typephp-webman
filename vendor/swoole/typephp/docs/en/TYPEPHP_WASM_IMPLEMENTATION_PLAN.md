# TypePHP WASM Technical Plan and Implementation Roadmap

> Status: WASI 0.2 Component and Chrome Worker prototypes implemented
> Research date: 2026-08-07  
> Current goal: WASI 0.2 (Preview 2), NTS, single-threaded; WASI 0.1 not supported

## 1. Document Purpose

This document records the technical decisions, functional boundaries, runtime architecture, primary risks, validation methods, and phased implementation plan for TypePHP's WebAssembly support.

The implementation validation completed on 2026-08-07 has proven that a trimmed PHP 8.5, the PHPX core, TypePHP-generated code, GMP, MPFR, and mpdecimal can be statically linked into a single module via the WASI SDK and run in Wasmtime. See [Building a TypePHP WASI Program](WASI_BUILD.md) for the reproducible build procedure. The remainder of this document also retains the browser-stage design goals.

## 2. Core Conclusions

The first TypePHP WASM release adopts the following path:

```text
PHP source code
    -> TypePHP compiler
    -> TypePHP-generated C++
    -> WASI SDK compilation and static linking
       + PHP NTS
       + PHPX
       + TypePHP runtime
       + GMP / MPFR / mpdecimal
       + PHP embed/WASI runtime
    -> typephp.wasm (WASI 0.2 command component)
```

Specific decisions are as follows:

1. The first release reuses the current C++/Zend backend; it does not directly generate WAT/WASM, nor does it reimplement the PHP runtime.
2. Use the WASI SDK `wasm32-wasip2` sysroot to generate the Component directly; Chrome uses Jco to transpile it to ESM, without maintaining a second Emscripten ABI.
3. PHP, PHPX, TypePHP-generated code, and the high-precision libraries are all statically linked into a single `.wasm` module.
4. Wasmtime and Chrome together provide CLI, stdio, exit, clocks, random, and a controlled filesystem; the Chrome host always runs inside a Worker.
5. Only PHP NTS is supported; threads are not supported.
6. Fiber and Generator are disabled.
7. C++ exceptions and the `setjmp/longjmp` required by Zend bailout must be supported.
8. Keep the PHP stream framework and local streams, and disable network transports and features that depend on OS process capabilities.
9. WordPress Playground and other PHP-WASM projects serve only as a source of patches and porting experience, not as a dependency or codebase for TypePHP.

This document describes the shortest viable path. For the long-term backend-neutral approach, see [BACKEND_NEUTRAL_IR.md](BACKEND_NEUTRAL_IR.md). The WASI prototype proves that the TypePHP frontend and semantic layers do not need to be rewritten for WASM.

## 3. Why Not Adopt WordPress Playground

WordPress Playground is a mature browser-based WordPress product, but it is not a lightweight PHP-WASM porting layer. Its repository and build system simultaneously serve:

- Multiple PHP versions and extension combinations;
- WordPress distributions and their assets;
- Browser, Web Worker, and Node.js runtimes;
- Virtual filesystems, mounting, and persistence;
- Network proxying and browser HTTP adaptation;
- NPM packages, a website, developer tooling, and integration tests;
- WordPress-specific APIs and product features.

TypePHP cannot directly reuse the PHP-WASM binary published by Playground, because TypePHP needs to statically link PHPX, the compiled C++, and the high-precision libraries together. If TypePHP forked Playground, it would also be bound to Playground's monorepo, Node/NPM build, version matrix, and product release cycle.

Therefore, the following principles are adopted:

- Do not fork WordPress Playground;
- Do not make `@php-wasm/*` a runtime dependency of TypePHP;
- Do not copy its WordPress, network proxy, file sync, and UI layers;
- Only study the PHP configure parameters, php-src patches, Emscripten compatibility handling, and the minimal C API;
- All borrowed patches must be split apart, have their sources attributed, and be verified to still apply to TypePHP's pinned PHP/Emscripten versions.

Projects such as `seanmorris/php-wasm` and `soyuka/php-wasm` follow the same principle: they may serve as build references and issue indexes, but they do not become TypePHP's base repository.

## 4. Goals and Non-Goals

### 4.1 Current WASI Goals

- Load TypePHP compilation artifacts in WASI runtimes such as Wasmtime.
- Execute the statically compiled TypePHP application entry point.
- Preserve TypePHP's current primary language semantics based on Zend and PHPX.
- Correctly handle the PHP request lifecycle, C++ exceptions, and Zend bailout.
- Support the GMP, MPFR, and mpdecimal high-precision types.
- Support the WASI filesystem and the necessary local PHP streams.
- Return deterministic, testable errors for unsupported features, rather than link failures or runtime crashes.
- Make the build process reproducible, with pinned php-src, WASI SDK, and numeric library versions.

### 4.2 First-Phase Non-Goals

- Direct browser execution without host adaptation.
- pthread, Web Worker-parallel PHP, or shared memory.
- Fiber and Generator.
- Dynamic extension loading.
- Runtime compilation of PHP source code or general-purpose `eval()`.
- TCP, UDP, Unix sockets, and listening ports.
- Network clients such as MySQL, PostgreSQL, and Redis.
- Network protocol implementations such as `curl`, FTP, and SMTP.
- `fork`, `exec`, `system`, `shell_exec`, `proc_open`, and signal handling.
- FFI, JIT, opcache, and the debugger.
- Full WordPress compatibility.
- Implementing asynchronous host calls in the first phase.

## 5. Target Platform Selection

### 5.1 Currently Using the WASI SDK

For now, establish a command-line-verifiable baseline first. The WASI SDK has already been verified to provide, simultaneously:

- A complete C/C++ to WebAssembly toolchain;
- Standard Wasm C++ exception handling;
- The SJLJ required by Zend bailout;
- A capability-based filesystem;
- libc, time, and random number interfaces.

PHP, PHPX, and all TypePHP C++ translation units must use consistent Wasm EH/SJLJ parameters. The linker must treat inconsistent function signatures as fatal errors.

### 5.2 Chrome Component Host

Chrome currently cannot natively instantiate a Component. The builder uses Jco to transpile the same WASI 0.2 Component into core Wasm and ESM, and `examples/wasm-hello/typephp-worker.mjs` demonstrates the host entry point. The browser adaptation does not include PHP, PHPX, or high-precision type semantics.

## 6. Artifacts and Runtime Model

### 6.1 Release Artifacts

The recommended minimal release artifacts are:

```text
dist/
├── typephp.wasm
└── typephp-wasm.mjs
```

All C/C++ code goes into `typephp.wasm`. The browser does not automatically provide WASI imports on its own; `typephp-wasm.mjs` serves as the loading entry point for the WASI host/adapter, responsible only for:

- Fetching and instantiating the `.wasm`;
- Providing stdout/stderr;
- Initializing the in-memory filesystem;
- Implementing or wiring in host capabilities such as WASI clocks and random numbers;
- Invoking the exported TypePHP lifecycle interfaces;
- Converting status codes and error messages into JavaScript results.

PHP semantics, Zend object operations, or TypePHP business logic should not be placed in the JavaScript loader.

### 6.2 Lifecycle

The recommended model is "module startup once, request repeatable":

```text
instantiate wasm
    -> typephp_wasm_module_startup()
    -> typephp_wasm_request_startup()
    -> TypePHP AOT entry
    -> typephp_wasm_request_shutdown()
    -> request can be executed again
    -> typephp_wasm_module_shutdown()
```

Each request must have an independent PHP request memory pool. Successful execution, PHP exceptions, C++ exceptions, and Zend bailout must all enter a unified cleanup path.

The module-exported API can start from the following minimal set; the names are subject to the actual implementation:

```c
int typephp_wasm_module_startup(void);
int typephp_wasm_run(int argc, const char **argv);
const char *typephp_wasm_last_error(void);
void typephp_wasm_module_shutdown(void);
```

`typephp_wasm_run()` executes the statically linked AOT entry point; it is not responsible for parsing and compiling arbitrary PHP source code at runtime.

## 7. PHP Build Strategy

### 7.1 Base Configuration

- Pin a specific php-src commit, rather than pinning only a branch name.
- NTS build.
- Disable existing SAPIs such as CLI, CGI, FPM, and Apache.
- Add a minimal `typephp_wasm` SAPI, or first validate the lifecycle with a minimal embed prototype before converging on a dedicated SAPI.
- Disable opcache/JIT.
- Statically link all extensions.
- Disable unneeded extensions and auto-detection to prevent the host environment from changing build results.
- Use `config.site` and a separate patch directory to record cross-compilation conclusions.

For the first phase, do not directly copy other projects' complete configure parameters. Start from a minimal PHP core, and add extensions one by one according to TypePHP PHPT and runtime dependencies.

### 7.2 Extension Layering

It is recommended to divide extensions into three groups:

1. **Must enable**: core, standard, SPL, date, pcre, hash, json, etc., required for TypePHP and Zend basic operation; the final set is subject to actual linking and test results.
2. **Optional local extensions**: ctype, filter, mbstring, tokenizer, fileinfo, zlib, etc., with no OS network dependencies, but they increase size.
3. **Disable in first phase**: sockets, curl, mysqli, PDO network drivers, pcntl, posix, FFI, shm, sysv, readline, opcache/JIT, etc.

GMP, MPFR, and mpdecimal are first handled as static dependencies of the PHPX/TypePHP high-precision implementation; enabling PHP `ext/gmp` is not required.

## 8. PHP Streams and OS Capabilities

### 8.1 Do Not Disable the Entire Stream Subsystem

The PHP standard library depends heavily on streams. Completely disabling streams would break file reads and writes, `php://`, include path handling, and some standard extensions, with little benefit and high compatibility cost.

Keep in the first phase:

- Ordinary file streams, backed by Emscripten MEMFS;
- `php://memory`;
- `php://temp`;
- Host mapping for `php://stdin`, `php://stdout`, and `php://stderr`;
- Whether `data://` is enabled is decided by size and security evaluation;
- Pure in-memory stream filters can be enabled as needed.

### 8.2 Disable Network Streams

The following should be disabled or not registered during the PHP build and runtime registration phases:

- TCP, UDP, and Unix socket transports;
- The socket extension;
- Network-dependent wrappers such as `http://`, `https://`, and `ftp://`;
- `fsockopen()`, `pfsockopen()`, `stream_socket_*()`;
- Network database and network client extensions.

In the first phase, PHP sockets should not be emulated via synchronous XHR or implicit JavaScript fetch. If HTTP is needed in the future, an explicit, authorizable asynchronous host API should be designed, rather than faking POSIX sockets.

### 8.3 Other OS-Related Features

The following capabilities must be disabled, degraded, or injected by the host:

| Capability | First-phase strategy |
|---|---|
| Filesystem | MEMFS; optional read-only preloaded files |
| Current directory and paths | Virtual root directory; must not leak host paths |
| Environment variables | Loader-injected whitelist |
| Time | WASI clocks; the browser host implements this interface using browser clocks |
| Random numbers | WASI random; the browser host implements it using a secure random source, not a weak pseudo-random substitute |
| DNS, sockets | Not supported |
| Processes, shell | Not supported |
| Signals | Not supported |
| Users, groups, permissions | Fixed values or explicit errors |
| File locks | Cross-instance locks not supported in first phase; degrade as needed within a single instance |
| Persistence | Disabled by default; Chrome may explicitly enable OPFS filesystem snapshots |

The compiler should progressively add WASM target capability checks: statically identifiable unsupported functions error at compile time; when a dynamic call cannot be statically determined, the runtime returns a deterministic error. These calls must never manifest as link-time missing symbols, empty functions, or undefined behavior.

## 9. Exceptions, Bailout, and Cleanup

This is the project's primary technical risk and must be validated before the full PHP feature port.

### 9.1 Compilation Options

When using native WebAssembly exceptions, C and C++ must use consistent `setjmp/longjmp` modes. The prototype is recommended to validate the following combination:

```text
C compilation:
  -sSUPPORT_LONGJMP=wasm

C++ compilation:
  -fwasm-exceptions
  -sSUPPORT_LONGJMP=wasm

Final link:
  -fwasm-exceptions
  -sSUPPORT_LONGJMP=wasm
```

All PHP, PHPX, TypePHP, and third-party C/C++ objects must use the same ABI and exception configuration. C++ exception catching cannot be enabled only at the final link stage.

If target browser compatibility does not allow native Wasm EH, the Emscripten JavaScript exception mode can be researched as a fallback, but the two models must not be mixed in the same release.

### 9.2 Boundary Rules

- C++ exceptions must not cross exported functions unhandled into JavaScript.
- Zend bailout must be caught at the request top level and enter request shutdown.
- After bailout, dangling PHPX objects that depend on the destroyed request memory pool must not be destructed.
- PHPX `Variant`, `Object`, `Array`, and high-precision objects on the stack must complete destruction while the memory pool is still valid, or be taken over by a dedicated bailout-safe boundary.
- After one request fails, the next request must still be executable; otherwise the runtime can only be defined as a one-shot instance, which must be made explicit in the API.

### 9.3 Must-Test Scenarios

- PHP returns normally.
- PHP `throw` is caught by TypePHP code.
- An uncaught PHP exception reaches the request top level.
- `fatalError`/Zend bailout.
- C++ `throw` and `catch`.
- An exception is thrown when PHP calls C++ and C++ calls PHP again.
- PHPX objects and high-precision objects exist on the stack when bailout occurs.
- Execute success, failure, success three requests in sequence.
- Execute a request again after memory growth.

## 10. Memory and High-Precision Libraries

### 10.1 WASM Memory

Use a single linear memory in the first phase, and validate `-sALLOW_MEMORY_GROWTH`. Record:

- Initial memory;
- Maximum memory;
- PHP memory_limit;
- Zend memory reclamation after request end;
- Actual peak of the Emscripten allocator;
- Whether memory continues to grow after multiple requests.

Do not choose `emmalloc` before benchmarking. PHP, GMP, MPFR, and mpdecimal are all allocation-intensive components; test size and runtime among candidates such as `dlmalloc` and `emmalloc`.

### 10.2 GMP, MPFR, and mpdecimal

- Statically compile all of them with the Emscripten toolchain.
- Disable assembly and host-CPU-specific optimizations.
- Pin limb, integer width, and ABI detection results.
- Do not depend on runtime dynamic library searching.
- Run existing BigInt, BigFloat, and Decimal PHPT, and add tests for maximum memory, division by zero, precision, rounding, and exception paths.
- Verify that library exceptions or allocation failures do not bypass PHP request cleanup.

## 11. Recommended Repository Structure

It is recommended to add a separate directory in the implementation phase, rather than scattering Emscripten conditionals into the existing build code:

```text
wasm/
├── README.md
├── build.sh
├── versions.env
├── config.site
├── cmake/
│   └── TypePhpWasmToolchain.cmake
├── patches/
│   ├── php-src/
│   ├── gmp/
│   ├── mpfr/
│   └── mpdecimal/
├── sapi/
│   └── typephp_wasm/
├── runtime/
│   └── typephp-wasm.mjs
└── tests/
```

Maintenance principles:

- Patches should be small and independent, one patch per compatibility issue;
- Each patch records its upstream version, source, reason, and removal condition;
- Download caches are not committed to Git;
- php-src, Emscripten, and third-party libraries are locked by checksums;
- Build artifacts do not enter the source repository;
- CI keeps at least debug and release builds.

## 12. Phased Implementation Plan

### Phase 0: Toolchain Risk Validation

Goal: Prove that the critical low-level mechanisms are feasible before integrating the full TypePHP.

- Pin the Emscripten version.
- Compile a minimal mixed C/C++ program.
- Validate C++ exceptions.
- Validate `setjmp/longjmp`.
- Validate nesting and repeated invocation of both.
- Validate support across major browsers.

Exit condition: exception and longjmp behavior is stable, with no unacceptable browser gaps.

### Phase 1: Minimal PHP NTS

Goal: PHP core completes the module and request lifecycle in the browser.

- Cross-compile a minimal php-src.
- Implement a minimal WASM SAPI or embed validation layer.
- Support stdout/stderr and MEMFS.
- Execute a fixed entry point.
- Validate fatal errors, exceptions, and request shutdown.

Exit condition: executing "success, failure, success" requests in sequence without crashes and without sustained memory growth.

### Phase 2: Integrate PHPX and TypePHP

Goal: The existing TypePHP C++ backend can be compiled by `em++` and statically linked.

- Add a WASM platform/backend configuration to the compiler.
- Unify compilation flags across PHPX, TypePHP, and third-party libraries.
- Link a minimal TypePHP `main()`.
- Establish a WASM smoke PHPT subset.
- Add capability diagnostics for unsupported system APIs.

Exit condition: basic type, function, class, exception, array, and object tests pass.

### Phase 3: High Precision and Local Streams

Goal: Support TypePHP's critical runtime capabilities.

- Statically link GMP, MPFR, and mpdecimal.
- Run the full high-precision operator and boundary tests.
- Support the necessary `file://` and `php://` streams.
- Add a preloaded read-only resource mechanism.
- Clearly define all disabled wrappers, transports, and extensions.

Exit condition: high-precision tests pass, local file behavior is deterministic, and all network APIs fail predictably.

### Phase 4: Size, Performance, and Release

Goal: Form a distributable TypePHP WASM SDK.

- Release optimization and dead-code elimination.
- Review the exported symbol whitelist.
- Compare allocator and memory growth configurations.
- Establish download size, startup time, and peak memory benchmarks.
- Generate `typephp.wasm` and a thin `.mjs` loader.
- Write user-facing feature and limitation documentation.

Exit condition: artifacts are reproducible, the compatibility checklist is complete, and performance reaches the preset baseline.

### Phase 5: Optional Host Capabilities

These are selected later based on real needs and are not default capabilities of the base runtime:

- IDBFS or OPFS persistence;
- Explicit HTTP host API;
- Node.js host;
- WASI prototype;
- Multi-instance isolation;
- Web Worker parallel instances.

Each capability must be enabled through an explicit capability, and PHP code must not obtain all host permissions by default.

## 13. Testing Strategy

### 13.1 Testing Layers

1. **Toolchain tests**: exceptions, longjmp, static libraries, linking, and exported symbols.
2. **PHP lifecycle tests**: module/request startup, shutdown, bailout, and repeated requests.
3. **PHPX tests**: Variant, Object, Array, references, exceptions, and resource destruction.
4. **TypePHP PHPT**: select existing tests that do not depend on the OS, and maintain WASM skip reasons.
5. **High-precision tests**: full operators, boundaries, errors, and memory stress.
6. **Capability restriction tests**: network, processes, threads, and dynamic extensions must be stably rejected.
7. **Browser tests**: minimum supported versions for Chrome, Firefox, and Safari.

### 13.2 Key Metrics

- `.wasm` raw size and compressed size;
- First instantiation time;
- Module startup and request startup time;
- Execution time of a simple TypePHP program;
- Initial, peak, and post-multiple-request linear memory;
- Recoverability after exceptions and bailout;
- JavaScript loader size;
- Reproducible build checksums for identical inputs.

## 14. Go/No-Go Conditions

If any of the following occurs, pause the full port and re-evaluate the architecture:

- A safe boundary between Zend bailout and C++ stack destruction cannot be established;
- Request failure stably corrupts subsequent requests, and the one-shot instance model is unacceptable;
- GMP, MPFR, or mpdecimal require a large-scale invasive fork;
- `.wasm` size or browser peak memory clearly exceeds the acceptable range of the target scenario;
- Safari, Firefox, and Chrome require mutually incompatible exception ABIs;
- Assumptions in PHPX that depend on native threads, dynamic linking, or OS resources cannot be isolated.

If the shortest path is not feasible, then evaluate the standalone WASM runtime/backend described in [BACKEND_NEUTRAL_IR.md](BACKEND_NEUTRAL_IR.md); that rewrite should not be started prematurely without prototype data.

## 15. External References

- [PHP Source Repository](https://github.com/php/php-src)
- [Emscripten: C setjmp/longjmp Support](https://emscripten.org/docs/porting/setjmp-longjmp.html)
- [Emscripten: C/C++ Portability Notes](https://emscripten.org/docs/porting/guidelines/portability_guidelines.html)
- [Emscripten: Code and Memory Optimization](https://emscripten.org/docs/optimizing/Optimizing-Code.html)
- [WordPress Playground: Compiling PHP to WebAssembly](https://developer.wordpress.org/playground/developers/architecture/wasm-php-compiling/)
- [WordPress Playground Architecture](https://wordpress.github.io/wordpress-playground/developers/architecture/)
- [seanmorris/php-wasm](https://github.com/seanmorris/php-wasm)
- [soyuka/php-wasm](https://github.com/soyuka/php-wasm)

These links are used to track upstream behavior and known porting issues. TypePHP's final implementation and compatibility must be verified by its own build, tests, and benchmarks, and must not directly inherit the conclusions of other projects.

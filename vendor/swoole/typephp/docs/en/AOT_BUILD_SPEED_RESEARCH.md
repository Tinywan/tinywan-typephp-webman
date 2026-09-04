# AOT Compilation Speed Optimization Research Notes

This document records the current assessment of Swoole-Compiler AOT compilation speed, bottleneck analysis, and future research directions. It is not yet tied to any specific PR.

## Goal

Reduce the following two categories of time cost:

1. **Cold-start full build**: `./bin/tpc.php project.yml`
2. **Hot-start incremental build**: recompiling after changing only a few PHP files

The focus is on large projects and compiler self-hosting scenarios.

## Current Pipeline

The main flow is in `src/Php/Translator.php`:

1. `prepare()`: scan files, parse AST, collect symbols, sort dependencies
2. `convert()`: generate the corresponding `.cc` for each PHP file
3. `genStubFile()`: generate the arginfo / class register header file
4. `genFunctionDeclarations()` / `genDataDeclarations()`: generate the build-time internal declaration header
5. `genExtension()`: generate a single `extension-<target>.cc`
6. `compile()`: compile all `.cc/.c/...` into `.o`
7. `build()`: link into the final executable or extension

## Main Bottleneck Assessment

### 1. Common Header Churn Causing Full Recompilation

Currently all translation units include:

- `php_<target>_func_decl.h`
- `php_<target>_data_decl.h`

As soon as any function declaration, default-argument helper, or global symbol declaration changes, a large number of `.cc` files get recompiled.

This is one of the key reasons for the poor incremental build performance of large projects.

### 2. The `extension-<target>.cc` Single File Is Too Large

The extension main file carries:

- class entry registration
- the function table
- literal strings
- module initialization
- static property initialization
- constant initialization

The larger the project, the larger this single TU becomes, making it easy to become a compilation tail bottleneck; even if other files can be compiled in parallel, they all stall on this one large file.

### 3. Missing a General Incremental Cache

Currently only `phpx/src/misc` has an object cache:

- `hasMiscObjectFileCache()`

The `.cc`, common headers, arginfo headers, and extension file generated for the user's own project are still essentially fully regenerated and fully recompiled.

### 4. clang-format Overhead Is Fixed and Serial

`formatCppCode()` runs once for each generated file:

```bash
clang-format -i <file>
```

This introduces:

- extra process startup overhead
- a large amount of disk I/O
- serial formatting waits

It is especially noticeable for large projects.

### 5. arginfo / stub Are Regenerated Every Time

`generateStubFile()` currently runs every time; even if the input PHP files have not changed, it regenerates the header files, further amplifying the header churn problem.

### 6. Only the Compilation Stage Is Parallel; the Front Stages Are Mostly Serial

Currently `compileWithPcntl()` only parallelizes `.cc -> .o`:

- prepare
- convert
- stub generation
- format

These stages are still mostly serial.

## Highest-priority Optimization Directions

## P0: Do Not Rewrite Files When Content Is Unchanged

This is the most worthwhile foundational change to prioritize.

### Idea

For all generated files:

- `.cc`
- arginfo `.h`
- `php_<target>_func_decl.h`
- `php_<target>_data_decl.h`
- `extension-<target>.cc`

Compare the content before writing to disk:

- Same content: **do not write the file**
- Different content: write it

### Value

Avoid triggering downstream full recompilation merely because of an mtime change.

---

## P0: General object cache / incremental compilation

Extend the current caching approach that only targets `phpx/src/misc` to user-generated code.

### Suggested Cache Conditions

For each target `.o`:

1. `.o` exists
2. `.o` is newer than its corresponding source file
3. `.o` is newer than the headers it depends on
4. The compile option signature has not changed (optimization level, debug, sanitize, cxxflags, PHP/ZTS, etc.)

When satisfied, skip compilation directly.

### Supporting Requirements

A clear "build signature" mechanism is needed, for example:

- compiler backend
- cpp compiler path
- C++ standard
- optimize/debug/sanitize
- build mode
- PHP/ZTS information

---

## P0: Disable clang-format by Default

It is recommended to make formatting an explicit capability rather than part of the default compilation path.

### Recommendation

- Disable by default
- Add a `--format` or debug/dev mode to enable it
- Or format only changed files

### Value

This is a low-risk optimization with immediate effect.

---

## P1: Split the Common Declaration Header

### Current Problem

Complex default-argument helpers also enter the common `func_decl.h`, widening the impact of header changes.

### Optional Directions

1. **Split declaration headers by source file**
2. **Move helpers from the common header to local headers / local `.cc`**
3. **Only truly cross-TU declarations go into the common header**

### Goal

Reduce "one change, full project recompilation".

---

## P1: Split `extension-<target>.cc`

### Splittable Modules

1. `extension-main.cc`
2. `extension-class-register-*.cc`
3. `extension-function-table.cc`
4. `extension-const-init.cc`
5. `extension-static-init.cc`

### Value

- Reduce the size of a single TU
- Enhance parallel compilation benefits
- Reduce the tail wait for large projects

---

## P1: arginfo / stub caching

### Direction

Introduce input-content-based caching for `generateStubFile()`:

- Source PHP content hash
- gen_stub version signature
- PHP version signature

Do not overwrite output header files when the content is unchanged.

### Value

Reduce header churn, with a clear effect when combined with incremental builds.

---

## P2: Parallelizing prepare / convert / stub generation

Currently only the compile stage is parallelized. Later the following can be explored:

1. Layering by dependency topology after file scanning
2. Parallel convert for files in the same layer
3. Parallel stub generation for files in the same layer

### Risk Points

- There is a lot of shared state (literalStrings, classMap, funcMap, propMap, symbol tables, etc.)
- It is necessary to first sort out which state can be sharded and which must be merged

Therefore this direction has large benefits but also higher implementation complexity.

---

## P2: Symbol-dependency-driven minimal recompilation

An ideal incremental build should not be based only on file timestamps, but on:

- which symbols have changed
- which files depend on those symbols

### Goal

When modifying one PHP file, rebuild only:

1. the file itself
2. files that depend on its exported symbols
3. the necessary extension / declaration modules

This would significantly improve hot build speed for large projects.

---

## P2: Toolchain-level optimization

### Compilation caches

- `ccache`
- `sccache`

### Faster linkers

- `mold`
- `lld`

### Precompiled headers

Try PCH for stable large headers, for example:

- `phpx.h`
- `phpx_helper.h`
- `phpx_std.h`

These optimizations are relatively cheap to implement and can be advanced together with the compiler option layer.

## Special Constraints on Literal Arrays

Literal arrays are different from literal strings:

- **Literal strings** can leverage permanent strings to bypass the Zend request lifecycle
- **Literal arrays** must exist from `module_init()` to `module_clean()`, i.e. between PHP's `RINIT/RSHUTDOWN`

Therefore all future "array initialization caching" research must obey:

1. **PHP arrays must not be persisted into process-level permanent objects**
2. Only the "initialization plan" or "generated code template" can be cached
3. Real array objects must be constructed within the request lifecycle

The already-introduced `ArrayInitPlan` belongs to this kind of safe abstraction:

- It only saves `expr/init/clean`
- It does not save array object instances that cross requests

## Suggested Landing Order

### Phase One (Fastest Effect)

1. Do not write files when content is unchanged
2. General `.o` cache
3. Disable clang-format by default
4. arginfo/stub content cache

### Phase Two (Structural Benefits)

5. Split the common header
6. Split `extension-<target>.cc`
7. Narrow the visibility of default-argument helpers

### Phase Three (Long-term Optimization)

8. prepare/convert parallelization
9. Symbol-dependency-driven minimal recompilation
10. PCH / ccache / mold / sccache

## Suggested Code Locations to Research First

- `src/Php/Translator.php`
  - `formatCppCode()`
  - `compile()`
  - `compileSourceFile()`
  - `compileWithPcntl()`
  - `genFunctionDeclarations()`
  - `genDataDeclarations()`
  - `genExtension()`
  - `genStubFile()`
- `src/Php/Backend/*`
  - Compile/link command construction, convenient for integrating `ccache` / `mold` / `lld`

## A Realistic Assessment

For large projects, slow AOT compilation is usually not simply "g++ is slow", but the superposition of the following:

1. Full regeneration
2. Header churn causing full recompilation
3. A single oversized extension TU
4. Per-file formatting
5. Lack of a real incremental cache

Therefore the most effective direction is not to first tune compilation flags, but to prioritize:

- **incrementality**
- **splitting**
- **reducing the common dependency surface**

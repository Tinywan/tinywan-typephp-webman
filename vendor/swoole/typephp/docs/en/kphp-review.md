# KPHP Compiler Review: Design, Optimization, Toolchain, and Syntax Compatibility Analysis

> 2026-06-11 · Based on source review of /home/swoole/workspace/cpp/kphp

---

## 1. Architecture Overview

| Dimension | KPHP | AOT Compiler |
|------|------|-------------|
| Compilation target | PHP → C++ → binary | PHP → C++ → binary |
| IR form | Custom vertex (op_*) tree | PHP-Parser AST → C++ string |
| Type inference | Iterative convergence graph inference (converging toward generalization) | SSA + manual type annotations |
| Intermediate optimization | Multi-pass AST rewriting (~60+ pipes) | Direct AST → C++ translation + a few optimizations |
| Runtime | Self-developed (allocator/string/array/mixed) | phpx (C++ RAII wrapper around Zend API) |
| Concurrency model | Single thread + reactor/epoll | Depends on Zend/TSRM |
| Thread safety | None (lock-free memory allocator) | Zend TSRM |
| Code size (compiler) | ~18k lines of pipe code | ~6k lines of CompilerBase |

---

## 2. Reusable Innovative Designs

### 2.1 Rewrite Rules DSL (Pattern-Matching Optimization Rules)

**File**: `compiler/rewrite-rules/early_opt.rules`

A declarative DSL describes AST rewriting and generates C++ optimization code at compile time. The rule format is `(pattern) => (replacement)`, supporting conditional clauses and embedded C++ expressions:

```lisp
;; strlen constant folding
(op_func_call {"strlen"} arg:(op_string))
  => (op_int_const { std::to_string(arg->str_val.size()) })

;; explode index direct access → specialized version
(op_index (op_func_call {"explode"} delim s) k:(op_int_const))
  => (op_func_call {"_explode_nth"} delim s k)

;; ("" . $x) → (string)$x — eliminate meaningless concatenation
(op_concat (op_string {""}) x) => (op_conv_string x)

;; substring type optimization: conv(substr(...)) → conv(_tmp_substr(...))
(op_conv_int x) if let x2 { to_tmp_string_expr(x) } => (op_conv_int x2)
```

**AOT adoption priority: P0**

Currently `FuncCallOptimizer`'s `strlen`/`count` optimizations are hardcoded. Introducing a similar rule engine can:
- Add optimizations declaratively, reducing maintenance cost
- Use `if let` conditions for local pattern variable binding
- Separate rule files from the compiler, making hot reloading feasible

Implementation suggestion: implement a `RewriteRule` class at the PHP level and load/match it in `FuncCallOptimizer`.

---

### 2.2 Smart instanceof / Smart Casts (Type Narrowing)

**File**: `compiler/pipes/transform-to-smart-instanceof.cpp`

After `if ($x instanceof A)`, `$x` is automatically renamed to `instance_cast<A>($x)` inside the if body. The core innovation is doing variable splitting **before type inference**:

```php
// PHP source
if ($x instanceof A) {
    $x->methodOfA();  // $x automatically becomes instance_cast<A>($x)
}

// Reverse guard pattern
if (!($x instanceof A)) return;
// After this, $x is replaced by instance_cast<A>($x) across the whole function scope
```

It also handles the renaming of same-named variables across different `catch (SomeClass $e)` blocks to prevent assumption confusion.

**AOT adoption priority: P0**

Currently `SsaTypeOptimizer` only narrows int/float/string base types. Object type narrowing can be added:
1. Identify `instanceof` guards in the SSA builder
2. Replace with the target subclass type in the then/else branches
3. Combine with the existing `stableObjects` mechanism for devirtualization

---

### 2.3 Pipeline Parallel Compilation

**File**: `compiler/compiler.cpp`

The compilation process works at function granularity, chaining pipes via `operator>>` and processing them in parallel across multiple threads:

```cpp
SchedulerConstructor{scheduler}
    >> PipeC<LoadFileF>{}
    >> PipeC<FileToTokensF>{}
    >> PipeC<ParseF>{}
    >> PassC<GenTreePostprocessPass>{}
    /* ... 60+ pipes */;
```

Three pipe types:
- **PipeC\<T\>**: general transformation, input→output
- **PassC\<T\>**: function-level transformation, traverses all AST vertices
- **SyncC\<T\>**: synchronization point, outputs only after all inputs are processed

Different functions can be processed simultaneously at different stages; global storage uses thread-safe or lock-free structures.

**AOT adoption priority: P3**

Currently `Preprocessor` → `CompilerBase` is serial. For large projects, function-level parallelism can be introduced:
- Compile classes/functions independently at their granularity
- Use `SyncC` synchronization points to merge global symbol tables

---

### 2.4 Switch Splitting (State Machine Transformation)

**File**: `compiler/pipes/split-switch.cpp`

Extract each case branch of a switch into an independent function, driven by a state variable:

```cpp
// Each case becomes:
int case_state = 0;
auto case_res = switch_func_N(&case_state);
if (case_state == 1) return case_res;     // normal return
if (case_state == -1) break;              // break semantics
```

`break N` and `continue N` are converted to setting the state variable to `-1` + return, consistent with the `_brk_flag` / `_cnt_flag` approach previously implemented in AOT.

**AOT adoption priority: P2**

Large switches can be split into independent functions, reducing single-function complexity and giving GCC more room for inlining/optimization.

---

### 2.5 Constant Immutability Markers and init-once

**Files**: `compiler/pipes/collect-const-vars.cpp`, `runtime-common/core/memory-resource/`

Compile-time constant arrays/strings use special refcount markers:

```cpp
ExtraRefCnt::for_global_const   // immutable, triggers COW on modification
ExtraRefCnt::for_instance_cache // shared across requests, not modifiable
```

These constants are stored in the data section, initialized once at server startup, and used read-only in subsequent requests. Any modification automatically triggers COW.

**AOT adoption priority: P1**

Currently AOT already promotes constant arrays to static variables, but a finer-grained immutability marker mechanism can be introduced to reduce unnecessary COW copies (when the compiler can prove a variable is never modified).

---

### 2.6 Function Specialization (Multi-Version Generation)

**File**: `compiler/pipes/early-optimization.cpp`

Functions are specialized by their arguments before type inference:

- `microtime()` → `_microtime_float()` or `_microtime_string()` (based on the true/false argument)
- `list() + explode()` → `_explode_tupleN()` (precise N-tuple type)
- `explode()[N]` → `_explode_nth()` (O(1) direct access to the Nth element)
- `substr()` in a function argument position → `_tmp_substr()` (avoid string copying)

The key is that **specialized versions return more precise types**. For example, `microtime()` returns `mixed`, while `_microtime_float()` returns `float`.

**AOT adoption priority: P1**

`FuncCallOptimizer` currently only does constant folding; it can be extended to **multi-version specialization**:

```php
// Current
$result = strlen($s);  // returns mixed/int

// After optimization
$result = _strlen_string($s);  // compile-time-determined int return
```

---

### 2.7 Class Assumptions: A Priori Type Prediction

**File**: `compiler/class-assumptions.cpp`

Solves the **circular dependency between type inference and call graph construction**:

```
$obj->method()  needs $obj's type to bind method()
               but type inference needs a complete call graph
               → Assumption breaks the cycle
```

Assumption sources:
- `@param ClassName $x` — parameter type
- `@return ClassName` — return type
- `@var ClassName` — local variable
- Constructor call `new ClassName()` → directly obtains the type

Assumptions are made **before** type inference, used to bind the call graph. After type inference they are **validated** — an error is reported on mismatch.

**AOT adoption priority: P2**

For method call devirtualization: assumptions are available earlier than pure SSA analysis and can serve as the first stage of devirtualization (falling back when SSA is unavailable).

---

### 2.8 Automatic Virtual Method Generation

**File**: `compiler/pipes/generate-virtual-methods.cpp`

When a method is overridden by subclasses, the base class method automatically becomes a dispatcher:

```cpp
ReturnType f$Base$$method(instance_var, args...) {
    if (instance_var.ce() == Child1::ce)
        return f$Child1$$method(instance_cast<Child1>(instance_var), args...);
    if (instance_var.ce() == Child2::ce)
        return f$Child2$$method(instance_cast<Child2>(instance_var), args...);
    // ... fallback to self
    return f$Base$$method$$Base(instance_var, args...);
}
```

It also performs PHP 7.4+ type variance checks (parameter contravariance, return covariance).

**AOT adoption priority: P2**

The "runtime exact-type guard" in the current devirtualization plan is consistent with this idea. Its automatic generation of all dispatch branches + variance checking can be adopted.

---

### 2.9 Performance Inspection Annotations

**File**: `docs/kphp-language/best-practices/performance-inspections.md`

Compile-time performance analysis, activated via annotations:

```php
/** @kphp-warn-performance implicit-array-cast */
function businessLogic() { ... }
```

Supported inspection items:
- `implicit-array-cast` — detect `array<int>` → `array<mixed>` implicit conversion (expensive copy)
- `array-merge-into` — detect merges that can be optimized via `array_merge_into`
- `array-reserve` — detect arrays that can be pre-sized
- `constant-execution-in-loop` — detect constant expressions inside loops

Annotations propagate through the call chain to all reachable functions.

**AOT adoption priority: P3**

Similar to function coloring, this can serve as a compile-time static analysis plugin. `implicit-array-cast` has a huge performance impact on typed arrays and is worth detecting separately.

---

### 2.10 Pooled Memory Allocator

**File**: `runtime-common/core/memory-resource/unsynchronized_pool_resource.h`

- Pre-allocates fixed-size buffers
- Small blocks (<16KB): slab allocation, graded by size (`free_chunks_[chunk_id]`), O(1) allocate/free
- Large blocks (≥16KB): red-black tree management (`huge_pieces_`), supporting defragmentation
- **Hard reset after each request** (`hard_reset()`), no per-object freeing needed
- Supports OOM handling memory reservation

**AOT adoption priority: P4**

Currently relies on Zend MM. For long-running CLI mode, a pool allocator can significantly reduce fragmentation and allocation overhead. But it requires replacing the entire memory management layer, a large effort.

---

## 3. Toolchain Analysis

### 3.1 Test Infrastructure

KPHP has a three-layer test system:

| Layer | Directory | Purpose |
|------|------|------|
| PHPT tests | `tests/phpt/` (75+ subdirectories) | PHP behavior compatibility tests |
| C++ unit tests | `tests/cpp/compiler/` `tests/cpp/runtime/` `tests/cpp/server/` | Compiler/runtime/server component tests |
| Python integration tests | `tests/python/tests/` | HTTP/RPC/multi-process integration tests |

The test runner `tests/kphp_tester.py` supports:
- Tag mechanism (`@ok`, `@kphp_should_fail`, `@kphp_should_warn`, etc.)
- PHP version selection (`@php7.4`, `@php8`)
- Multi-process parallel execution (based on ThreadPool)
- TCP server management
- k2 mode (component compilation) compatibility
- Incremental compilation support (nocc distributed compilation)

**AOT adoption**:
- Currently AOT only has two layers — `phpunit/` (PHPUnit) and `tests/compiler/` (PHPT) — lacking compiler internal unit tests and integration tests
- The tag mechanism is more flexible than pure PHPT — it can mark expected compile failures, expected warnings, etc.
- The Python test runner provides better CI integration capability

### 3.2 Benchmark Framework

**File**: `tests/benchmarks/`

Uses the Go-written `ktest` tool for KPHP vs PHP performance comparison:

```
$ KPHP_ROOT=/path/to/repo/kphp ./ktest bench-vs-php tests/benchmarks/
```

Benchmark coverage:
- `BenchmarkBasic.php` — basic operations
- `BenchmarkConcat.php` — string concatenation
- `BenchmarkExplode.php` — explode performance
- `BenchmarkMultiSwitch.php` — large switch
- `BenchmarkTmpString.php` — temporary string optimization effect
- `BenchmarkJson.php` / `BenchmarkFFI.php` — specific features

**AOT adoption**:
- Can build a similar AOT vs PHP benchmark comparison suite
- Especially focus on scenarios the AOT compiler claims to optimize (such as typed property access, devirtualized calls)

### 3.3 IDE Integration

KPHP provides the **kphpstorm** IDE plugin (`docs/kphp-language/kphpstorm-ide-plugin/`), supporting:
- `@kphp-*` annotation syntax highlighting
- Type annotation completion
- Hints for KPHP-specific types

**AOT adoption**:
- Currently the AOT compiler uses annotations like `@phpstan-*`; can consider providing VSCode/JetBrains plugins

### 3.4 Incremental Compilation

KPHP recompiles only changed files (based on CRC64 hashes):

```cpp
// At the start of each generated file
//crc64      <content_hash>
//crc64_with_comments <hash_with_comments>
```

These hashes are compared against the previous generation results to determine which files need recompilation, including all upstream files that depend on them.

**AOT adoption**:
- Currently the `build/` directory is fully regenerated; a similar incremental mechanism can be introduced to speed up iteration on large projects

---

## 4. Syntax Compatibility Analysis

### 4.1 PHP Versions Supported by KPHP

KPHP targets the **PHP 7.4** language level, with some 8.0/8.1 features being added.

### 4.2 Unsupported Features (Architectural Reasons)

| Feature | Reason |
|------|------|
| Dynamic function/method calls (`call_user_func`) | Symbols cannot be resolved at compile time |
| `eval()` | Unknown at compile time |
| Dynamic class/function declarations | Symbol table must be complete at compile time |
| Reflection | Requires runtime metadata |
| Mock (PHPUnit) | Depends on Reflection + dynamic redefinition |
| Array internal pointers (`reset`/`current`/`next`) | Not consistent with reference semantics |
| PHP extension interop | Replaced by self-developed runtime |

### 4.3 Unsupported Features (Not Implemented)

| Feature | Status |
|------|------|
| Nested `list()` | Not implemented |
| Generators (`yield`) | Not implemented |
| Anonymous classes | Not implemented |
| Group use declarations | Not implemented |
| finally | Not implemented |
| `func_get_args` | Not implemented |
| References (except foreach by ref and reference parameters) | Partially supported |
| Interface appearing multiple times in a parent chain | Not supported |
| `insteadof` / trait renaming | Not supported |

### 4.4 KPHP-Specific Annotations

```php
// Function annotations
@kphp-inline                  // force inlining (GCC inline)
@kphp-flatten                 // aggressively inline all callees
@kphp-required                // force compilation (for string callbacks)
@kphp-sync                    // forbid being resumable
@kphp-no-return               // never returns (optimizes CFG)
@kphp-pure-function           // pure function (callable on constant arrays)
@kphp-warn-unused-result      // error on unused return value
@kphp-should-not-throw        // forbid throwing exceptions
@kphp-throws {Class}          // checked exceptions
@kphp-generic T1, T2          // generic functions
@kphp-color {color}           // capability annotation
@kphp-warn-performance {...}  // performance inspection
@kphp-disable-warnings {...}  // suppress specific warnings
@kphp-profile                 // embed profiler

// Class annotations
@kphp-serializable            // serializable
@kphp-immutable-class         // immutable class
@kphp-json {attr}={value}     // JSON configuration
```

### 4.5 Syntax Differences from the AOT Compiler

| Feature | KPHP | AOT Compiler |
|------|------|-------------|
| Base PHP version | 7.4 | 8.2+ |
| Enums | Not supported | Supported (PHP 8.1 enum) |
| Named arguments | Not supported | Supported |
| Match expressions | Not supported | Supported |
| Union types | Partial support | Supported |
| Nullsafe `?->` | Not supported | Supported |
| Constructor promotion | Not supported | Supported |
| `list()` destructuring | Partial | Full support |
| `break N` / `continue N` | Partial support | Supported |
| Typed arrays | Custom syntax `array<T>` | None (phpstan annotations) |
| Generic functions | `@kphp-generic` | None |
| Tuples / Shapes | Custom syntax | None |
| FFI | Supported (custom FFI) | None |

### 4.6 Key Differences in the Type System

KPHP's type system is much stricter than PHP's:
- **No type mixing allowed**: `f(42); f("string")` for the same `$arg` is a compile error
- **Typed arrays**: `array<int>` vs `array<string>` are different types; conversion requires explicit or implicit cast
- **mixed is expensive**: 16-byte tagged union + switch-case dispatch
- **Generic functions**: implemented via `@kphp-generic` compile-time specialization, similar to C++ templates
- **Variable splitting**: the same variable name may split into different names on different CFG paths (e.g. `$x` → `$x$v1`)

---

## 5. Summary and Priority Recommendations

| Priority | Technique | Difficulty | Benefit | Notes |
|--------|------|------|------|------|
| **P0** | Rewrite Rules DSL | Medium | High | Declarative optimization rules, extremely extensible |
| **P0** | Smart instanceof casts | Low | High | Directly improves object type narrowing + devirtualization |
| **P1** | Function specialization (multi-version) | Medium | High | More precise return types, eliminates mixed pollution |
| **P1** | Immutable constant markers | Low | Medium | Reduces COW; constant promotion foundation already exists |
| **P2** | Switch splitting | Medium | Medium | Multi-level break foundation already exists; optimization for specific scenarios |
| **P2** | Class Assumptions | High | High | Requires PHPDoc parsing infrastructure |
| **P2** | Automatic virtual method generation | Medium | High | Complements the devirtualization plan |
| **P3** | Performance inspection annotations | Low | Medium | Helps developers discover hidden performance issues |
| **P3** | Pipeline parallelism | High | Medium | Faster compilation of large projects |
| **P3** | Function coloring | Low | Low | Aids security/IO auditing |
| **P4** | Incremental compilation | Medium | Medium | Development experience optimization |
| **P5** | Resumable state machine | High | Medium | Requires actual async needs |
| **P5** | Pool allocator | Very high | High | Requires replacing the entire memory management |

# HHVM/HHBBC Compiler Review: Whole-Program Analysis, Type System, and Optimization Pipeline

> 2026-06-11 · Based on source review of /home/swoole/workspace/cpp/hhvm

---

## 1. Architecture Overview

HHVM's compilation system is divided into three layers:

| Layer | Component | Responsibility |
|------|------|------|
| Frontend | HackC (hphp/hack) | Hack/PHP source → HHAS (Hack Assembly) |
| Middle end | HHBBC (hphp/hhbbc) | HHBC bytecode → optimized HHBC bytecode |
| Backend | JIT (hphp/runtime/vm/jit) | HHBC → x86-64 machine code (runtime tracing JIT) |

HHBBC (~80k lines of C++) is a **whole-program bytecode optimizer** based on fixed-point iterative analysis. Core files:

| File | Lines | Purpose |
|------|------|------|
| `interp.cpp` | 6,685 | Abstract interpreter (forward dataflow) |
| `index.cpp` | 30,132 | Whole-program index: type info, dependency tracking, incremental re-analysis |
| `type-system.h` | 1,610 | Type lattice definition (trep + specialization) |
| `type-system.cpp` | 8,451 | Type operation implementation (meet/join/subtype/union) |
| `dce.cpp` | 3,102 | Type-aware dead code elimination (local + global) |
| `analyze.cpp` | 2,293 | Function-level dataflow analysis driver |
| `optimize.cpp` | 962 | Type-aware optimization pass |
| `cfg-opts.cpp` | ~300 | CFG optimization (unreachable block removal, exception edge simplification) |

Comparison with AOT Compiler:

| Dimension | HHBBC | AOT Compiler |
|------|-------|-------------|
| Compilation target | HHBC → optimized HHBC → JIT machine code | PHP → C++ → binary |
| Analysis level | Bytecode-level (whole-program) | AST-level (single-file) |
| IR form | php::Func (factored CFG + Bytecode) | PHP-Parser AST |
| Type inference | trep + specialization + fixed-point iteration | SSA + manual type annotations |
| Optimization scope | Whole-program (cross-file/cross-function) | Single function + class hierarchy |
| Call graph | Dynamically built (Index + assumption) | Static (classExtends + classMethodOverride) |
| Concurrency | Function-level parallel analysis | Serial execution |
| Runtime | HHVM (JIT + refcounting GC) | phpx (RAII wrapper around Zend API) |
| Code size | ~80k lines of C++ (HHBBC only) | ~15k lines of PHP |

---

## 2. Reusable Innovative Designs

### 2.1 Whole-Program Fixed-Point Analysis

**Files**: `hphp/hhbbc/README`, `analyze.cpp`, `index.cpp`

HHBBC's core algorithm: iterative convergence analysis across the **whole program**.

```
Algorithm flow:
1. Initialize work list = all functions/classes
2. Analyze each work unit in parallel (read-only Index, producing a new result)
3. Single-threaded: merge the new result into the Index
4. If the Index was updated → add back functions that depend on that info to the work list
5. Repeat until the Index reaches a fixed point (info no longer changes)
6. Final parallel optimization pass
```

Key design principles:
- Info in the Index can only **shrink** (shrinking types: becoming more precise)
- Types within function analysis can only **grow** (growing types: accumulated within the analysis context)
- Index info is **never wrong** (soundness guarantee) — it can only be insufficiently precise

**AOT adoption priority: P0**

Currently AOT is single-file serial compilation with no cross-file/cross-function analysis. What can be adopted:

1. **Index structure**: store inferred results such as each function's return type, parameter types, and property types
2. **Dependency tracking**: record every query ("function F queried the return type of method M of class C") and add F back to the work list when C::M's type is updated
3. **Iterative convergence**: initialize all functions as "unknown" → analyze round by round → until type info stops changing

Implementation suggestion:
```php
// Built in Preprocessor
class AnalysisIndex {
    // Function return types (shrinking across iterations)
    public array $returnTypes = [];   // funcName => TypeInfo

    // Dependency graph: funcName => [depended-on funcName, ...]
    public array $dependencies = [];

    // Function parameter states (collected from call sites)
    public array $paramTypes = [];    // funcName => [argIdx => TypeInfo]

    // Public static property states
    public array $publicStaticProps = []; // className::prop => TypeInfo
}
```

Then repeatedly analyze functions in topological order until `$returnTypes` and `$paramTypes` stop changing.

---

### 2.2 Trep Type Lattice (Bitset Type Lattice)

**Files**: `hphp/hhbbc/type-system.h`, `type-system-bits.h`, `type-system-detail.h`

HHBBC's type system is the cornerstone of the entire optimizer and is ingeniously designed:

**Base mechanism — trep (type representation):**
```cpp
// Each "base type" is a bit
BUninit, BInitNull, BFalse, BTrue, BInt, BDbl,
BCls, BLazyCls, BFunc, BClsMeth, BEnumClassLabel,
BObj, BRes, BRFunc, BRClsMeth,
BSStr, BCStr,  // uncounted/counted strings
BSVec, BCVec, BSVecE, BCVecE, BSVecN, BCVecN,  // arrays' counted × empty dimensions
// ... Dict, Keyset follow the same pattern
```

Types are represented as union types via bitset combinations. For example, `Int|String` is `BInt|BStr`.

**Specialization — extra info attached to types:**
```
Int=n          — known constant integer value
Dbl=n          — known constant float value
{S,C}Str=s     — known constant string value
Obj{<}=c       — known class type (exact or subclass)
Arr(T1,T2,...) — known array shape (packed array)
Arr([T1:T2])   — known array key/value types
```

**Key innovations:**
- `counted` / `uncounted` dimension: strings and arrays distinguish whether they are reference-counted, allowing the compiler to optimize uncounted (static/constant) values more aggressively
- `empty` / `non-empty` dimension: arrays distinguish empty/non-empty, eliminating redundant empty checks
- Monotonic lattice: types can only change in one direction (Index types can only shrink, analysis types can only grow)

**AOT adoption priority: P0**

Currently AOT uses discrete constants like `TYPE_INT`, `TYPE_STRING`, `TYPE_ARRAY` with no fine-grained info such as union types or uncounted markers. It could:

1. Use bitsets to represent union types (`int|string` instead of `mixed`)
2. Add an `Immutable` marker (values provably immutable at compile time)
3. Add a `NonEmpty` marker (arrays provably non-empty at compile time)
4. Build a type lattice defining meet (⊓) and join (⊔) operations

```php
// Current
public const TYPE_INT = 1;

// Suggested: bitset
class Type {
    const BINT = 1 << 0;
    const BSTRING = 1 << 1;
    const BBOOL = 1 << 2;
    const BFLOAT = 1 << 3;
    // ...
    const BIMMUTABLE = 1 << 16;  // immutable marker
    const BNONEMPTY  = 1 << 17;  // non-empty marker

    // union type: int|string = BINT | BSTRING
    // meet: narrower = more specific
    // join: wider = more general
}
```

---

### 2.3 Abstract Interpreter

**Files**: `hphp/hhbbc/interp.cpp` (6,685 lines), `interp.h`

HHBBC uses an **abstract interpreter** for function-level type inference. This differs from traditional SSA + constraint solving:

```
Algorithm (analyze_func):
1. Initialize the entry block's input state (parameter types = conservative assumptions from the Index)
2. Work list ← entry blocks
3. While work list not empty:
   a. Pop a block
   b. Run the abstract interpreter instruction-by-instruction over the block
   c. On instructions that may throw → propagate current state to the exception edge
   d. On branch instructions → propagate the post-branch state to the taken edge
   e. Block may fallthrough → propagate final state to the fallthrough edge
   f. If the target block's input state changed → add to work list
```

Key characteristics of the abstract interpreter:
- **State propagation**: compute a new `State` (locals + eval stack type info) after each instruction
- **Factored CFG**: exception edges are modeled separately (`FactoredExitBlock`), so ordinary instructions don't break basic blocks just because they "may throw"
- **Type specialization**: branch conditions automatically narrow types (e.g. `if (is_int($x))` → `$x` narrowed to `Int` in the then branch)
- **Constant propagation**: built-in constant folding and value tracking

**AOT adoption priority: P1**

Currently AOT's `SsaTypeOptimizer` does some type narrowing, but:
- No factored CFG concept (exceptions don't participate in dataflow)
- No abstract interpreter framework (each instruction defines `step(state) → new_state`)
- No iterative dataflow analysis

A lightweight abstract interpretation can be implemented on top of SSA:
```php
class AbstractInterpreter {
    // Run abstract interpretation over each SSA basic block
    function analyzeBlock(Block $block, State $inputState): State {
        foreach ($block->instructions as $instr) {
            $inputState = $this->step($instr, $inputState);
        }
        return $inputState;
    }

    // Each instruction defines how to transform state
    function step(Instruction $instr, State $state): State {
        // switch on instruction type
        // return new State with updated types
    }
}
```

---

### 2.4 Type-Aware Dead Code Elimination

**File**: `hphp/hhbbc/dce.cpp` (3,102 lines)

HHBBC's DCE differs from traditional liveness-based DCE — it **combines type analysis** to discover more dead code:

```
Two kinds of DCE:
1. Local DCE — within a single basic block
   - Traverse the block backward
   - Maintain a "reverse stack": mark which eval stack slots will be used in the future
   - Unused stack slots → the instruction producing that slot can be deleted
   - Unused local stores → eliminated

2. Global DCE — across basic blocks
   - Liveness analysis over locals
   - Allows eliminating dead stores across blocks
```

Key design: **type awareness** — DCE needs to know each instruction's type to judge correctly. For example:
- If `$x` has type `Bottom` (unreachable) on some path, code using `$x` may be dead code
- If `$x` is a counted type, related inc/dec ref operations cannot be eliminated

**AOT adoption priority: P2**

Currently AOT's DCE essentially relies on GCC/Clang's `-O2`. Compile-time local DCE can be added:
- Eliminate assignments to unused local variables
- Eliminate side-effect-free pure computations (if the result is unused)
- Use type info to determine whether an operation may have side effects

---

### 2.5 DataType Encoding: 3-of-7 Error-Correcting Code

**File**: `hphp/runtime/base/datatype.h`

HHVM's runtime type tags use an extremely clever bit encoding:

```cpp
// DataType is uint8_t
// - bit 0 (LSB): countedness — 0 = definitely uncounted
// - bits 1-7: 3-of-7 error-correcting code — exactly 3 bits set to 1

// Type detection becomes simple bit operations:
// Check if Vec or Dict: dt <= KindOfVec
// Check if it has a persistent version: dt <= KindOfString
// Check if null/uninit: dt >= KindOfUninit
```

Characteristics of the 3-of-7 encoding:
- There are exactly C(7,3) = 35 8-bit values with exactly 3 bits set to 1 (each persistent/counted pair shares the same 3-of-7 code)
- Detecting any type: `(dt & type_mask) == type_tag` completes in two instructions
- Unsigned LT/GT comparisons implement efficient type-group detection

**AOT adoption priority: P4**

This informs the **quality of generated code** for the AOT compiler — but mainly at the phpx layer. Consider giving phpx's `Variant` type tags a more efficient encoding to optimize runtime type checks like `is_int()`/`is_string()`.

---

### 2.6 RepoAuthType: Space-Efficient Type Storage in Bytecode

**Files**: `hphp/runtime/base/repo-auth-type.h`, `repo-auth-type-tags.h`

HHBBC encodes the type info derived from analysis as `RepoAuthType`, embeds it in the bytecode stream (`AssertRAT` instruction), and makes it available to the JIT.

Design points:
- Compact encoding (`CompactTaggedPtr`): type tag + optional pointer (class name/array shape) packed into one pointer width
- Covers the complete lattice from `Uninit` (most precise) to `Cell` (most general)
- `SubObj` / `SubCls` tags support subclass relationships
- Array shape specialization (precise type of packed arrays)

**AOT adoption priority: P3**

Currently AOT's type annotations are represented through the C++ type system (`int64_t`, `php::string`, `php::array`). For typed properties, RAT-like thinking can be used to generate more precise C++ type declarations.

---

### 2.7 Index Dependency Tracking

**File**: `hphp/hhbbc/index.cpp` (30,132 lines)

The Index is not just storage for type info — more centrally it provides the **dependency tracking mechanism**:

```
Dependency kinds (DependencyKind):
- ReturnTy    — function return type
- ConstVal    — constant value
- ClsConst    — class constant
- PropType    — property type
- PublicSProp — public static property type (especially important!)
```

When a function's return type is updated in the Index, all functions that queried that return type are marked for re-analysis.

**Special handling of public static properties**: public static properties can be modified by any function, so the Index tracks all mutation operations. When analysis finds that a static property is never modified, more aggressive constant propagation becomes possible.

**AOT adoption priority: P1**

Complements the whole-program analysis in 2.1 — Index dependency tracking is the foundation for implementing iterative analysis. In AOT:

```php
class Index {
    // Return types: funcName => Type
    // Dependencies: funcName => [depends_on_funcName, ...]
    // Dirty flags: funcName => bool (needs re-analysis)
}
```

After Preprocessor scans all files, build the initial Index, then iterate until convergence.

---

### 2.8 Factored CFG (Exception-Factored Control Flow Graph)

**Files**: `hphp/hhbbc/cfg.h`, `parse.cpp`

HHBBC's control flow graph **factors out** exception edges: it doesn't terminate basic blocks at every instruction that may throw, instead letting basic blocks be as large as possible.

```
Traditional CFG:
  instr1           ; block 1
  instr2(may_throw) ; block 1 terminates here (because it may throw)
  ---
  instr3           ; block 2

Factored CFG:
  instr1           ; block 1
  instr2(may_throw)
  instr3
  ; block 1 contains multiple instructions
  ; exception edges connect from the factored exit edge to the exception handler
```

Benefits:
- Larger basic blocks → more efficient dataflow analysis (fewer block boundaries)
- Type info can rule out exception possibilities → exception edges can be deleted during optimization
- Easier instruction scheduling during the JIT stage

**AOT adoption priority: P4**

For an AOT compiler (translating to C++ rather than directly generating machine code), the CFG is mostly handled by GCC. But for function coloring and SSA analysis, more precise exception modeling can be a reference.

---

### 2.9 Parallel Analysis

**Files**: `hphp/hhbbc/parallel.cpp` (69 lines), `README`

In HHBBC's fixed-point iteration, the analysis of each work unit can be **fully parallel**:

```
Thread-safety model:
- Analysis phase: can only read the Index (internally thread-safe) + read php metadata (immutable)
- Merge phase: single-threaded Index update (no lock needed)
```

This leverages the "Index info is never wrong" property — even if two threads analyze based on different versions of the Index, merging results never produces wrong info.

**AOT adoption priority: P3**

Similar to KPHP's pipeline parallelism. In AOT, independent functions/classes can be analyzed in parallel. Note that Index merging must be serial (or use lock-free structures).

---

### 2.10 Public Static Property Optimization

**File**: `hphp/hhbbc/index.cpp`

HHBBC tracks the mutation of every public static property across the whole program:

```
- Initial state: conservative assumption (may be modified by any function)
- During each analysis round, record which functions modify which static props
- If a static prop is never modified during analysis → can be constant-folded
- If a static prop is only ever assigned one type → its type can be narrowed
```

This is more precise than a simple "is it written" analysis because it is a **whole-program** analysis that can see cross-file mutations.

**AOT adoption priority: P2**

Currently in AOT, public static properties always use `Variant` (mixed type). Through whole-program analysis, static properties that are only assigned internally can be optimized to precise C++ types.

---

## 3. Toolchain Analysis

### 3.1 Test Infrastructure

HHVM has a massive test system:

| Layer | Directory | Count | Purpose |
|------|------|------|------|
| Quick test | `hphp/test/quick/` | 865 .php files | Fast regression tests |
| Slow test | `hphp/test/slow/` | 7,927 .php files | Comprehensive functional/performance tests |
| Zend test | `hphp/test/zend/` | ~4,500 | PHP compatibility tests (from php-src) |
| Ext test | `hphp/test/ext/` | ~800 | Extension feature tests |
| Server test | `hphp/test/server/` | ~100 | HTTP/RPC integration tests |
| HHBBC unit test | `hphp/hhbbc/test/` | 3 C++ files | Compiler internal tests |
| **Total** | | **~14,675** | |

Test runner characteristics:
- Quick vs Slow layering: Quick (~1 second to run) for pre-commit, Slow (~10 minutes) for CI
- Supports multiple run modes: interp / JIT / hhbbc + JIT / RepoAuthoritative
- Zend tests: directly reuse php-src's official tests to verify PHP compatibility

**AOT adoption:**
- Quick/Slow layered test strategy — classify the existing `tests/compiler/` by run time
- Directly reuse php-src's official PHPT tests — verify the AOT compiler's PHP behavior compatibility
- HHBBC has too few internal unit tests (only 3) — should not be emulated; AOT's PHPUnit coverage is better

### 3.2 Hack Type Checker

HHVM's Hack language has a complete **static type checker** (`hphp/hack/`) that runs independently of the compiler:

- Compile-time type annotations (`int`, `string`, `vec<T>`, `dict<TK,TV>`, `shape(...)`)
- Gradual typing: can migrate gradually from unannotated code
- IDE integration (LSP protocol support)
- Type coverage tracking

**AOT adoption:**
- Currently AOT uses annotations like `@phpstan-type`; phpstan can be integrated for pre-compilation type checking
- Type coverage is a useful metric: X% of functions/variables have precise type annotations

### 3.3 Tracing / Debug Infrastructure

HHVM has rich debugging and tracing:
- `TRACE_SET_MOD(hhbbc)` — module-level conditional logging (compile-time switch)
- `hphp/tools/` — various analysis tools (bytecode viewer, profiler, etc.)
- `debug.cpp` — human-readable printing of types/states

**AOT adoption:**
- The current `-vv` verbose output can adopt the TRACE_MODULE approach to filter logs by module
- Visualization of type analysis results can aid debugging the optimizer

### 3.4 RepoAuthoritative Mode

HHVM supports a **compile once, deploy many times** mode:

```
Source → HHBBC whole-program analysis → optimized bytecode Repo → multiple processes load the Repo directly
```

- The Repo stores **pre-analyzed type info** (RepoAuthType)
- No re-inference of types at runtime
- Bytecode-level interning (string/class/function ids)
- Multiple processes share the same Repo (mmap)

**AOT adoption: P4**

Currently AOT directly generates `.cc` files and compiles them into a binary, already on the "compile once, deploy many times" path. But the Repo's mmap sharing idea can be used for constant pool sharing in multi-process environments (rather than each process loading independently).

---

## 4. Type System Compatibility Analysis

### 4.1 Hack vs PHP vs AOT Compiler

| Feature | PHP 8.2 | Hack | AOT Compiler |
|------|---------|------|-------------|
| Base types | mixed, int, string, float, bool, array, null, void, never | int, string, float, bool, null, void, noreturn, mixed, dynamic, nonnull, nothing | Same as PHP |
| Union types | `int\|string` | `int\|string` (but discouraged in practice) | Supported |
| Intersection types | `X&Y` (8.1+) | Via `where` constraints | Not supported |
| Generics | None | `vec<T>`, `dict<TK,TV>`, `class Box<T>` | `std::vector<T>` (native C++) |
| Array types | `array` | `vec<T>`, `dict<TK,TV>`, `keyset<T>` | `php::array` |
| Shapes | None | `shape('x' => int, 'y' => string)` | None (object suggested) |
| Enums | enum (8.1) | enum, enum class (with label) | Supports PHP 8.1 enum |
| Nullable types | `?int` | `?int` (function params only) | Supported |
| nothing / bottom | None | Yes (empty function return, unreachable) | None |
| dynamic | None | Yes (selectively skip type checking) | None |

### 4.2 Key Differences in the Type Lattice

Hack/HHBBC's type system is far richer than PHP's:

```
HHBBC type lattice:

          Cell (any value)
            |
    InitCell (not Uninit)
      |           |
   Prim        Boxed types (Obj, Res, ...)
     |
  InitPrim (not null)
  |    |    |
Num  Bool  Str/ArrKey
|  |
Int Dbl

Bottom: Bottom (no value - unreachable)
Top: Cell (any value)
```

Characteristics:
- **Bottom**: represents the type of unreachable paths, enabling dead code elimination
- **Counted/Uncounted**: static strings vs runtime-allocated strings, different lifetimes
- **Array shapes**: `dict<'name' => string, 'age' => int>` is a distinct type
- **Wait handle**: `WaitH<T>` represents the type of async results

### 4.3 Hack's Gradual Typing Design

Hack evolved from PHP and supports gradual migration:
- `mixed` — any type (equivalent to unannotated PHP)
- `dynamic` — any type, and no type errors reported (a looser mixed)
- `<<__Soft>>` — soft type hints (not enforced at runtime)

This design allows large codebases to add type annotations incrementally, avoiding an "all or nothing" approach.

### 4.4 Syntax Differences from the AOT Compiler

| Feature | HHBBC Input (HHAS) | AOT Compiler |
|------|------|-------------|
| Base PHP version | Hack (PHP 5.6 branch) | PHP 8.2+ |
| Type annotations | Mandatory (required by Hack) | Optional (phpstan annotations) |
| Generics | `vec<T>`, `dict<K,V>`, custom generic classes | No native generics |
| Lambda / closures | `$x ==> $x + 1` (short lambda) | PHP closures (`function($x) { return $x + 1; }`) |
| async / await | Native support (WaitHandle) | None |
| Shapes | `shape('x' => int)` | None |
| Enum class | Supported (enum class with label) | PHP 8.1 enum only |
| XHP | HTML template syntax | None |
| Case types | `case type T = int \| string` | None |

---

## 5. Summary and Priority Recommendations

| Priority | Technique | Difficulty | Benefit | Notes |
|--------|------|------|------|------|
| **P0** | Whole-program fixed-point analysis | Very high | Very high | Cross-file type inference, return type narrowing, eliminating pseudo-dynamic calls |
| **P0** | Trep type lattice | Medium | Very high | Precise union types, uncounted markers, non-empty markers — improves precision of all optimizations |
| **P1** | Abstract interpreter | High | High | Replaces/enhances current SSA analysis, supports branch type narrowing and constant propagation |
| **P1** | Index dependency tracking | High | High | Foundation for whole-program analysis, incremental compilation |
| **P2** | Type-aware DCE | Medium | Medium | Eliminate more dead code, reduce generated C++ code size |
| **P2** | Public static prop optimization | Medium | Medium | Precise typing of global static properties |
| **P3** | RepoAuthType storage | Low | Medium | Embed precise type info in artifacts (limited value for the C++ generation stage) |
| **P3** | Parallel analysis | High | Medium | Faster compilation of large projects |
| **P4** | DataType encoding optimization | Low | Low | Micro-optimization of phpx Variant type checks |
| **P4** | Factored CFG | High | Low | C++ compiler already handles CFG optimization |
| **P5** | Repo mmap sharing | High | Low | Optimization for specific deployment scenarios |

### Key Takeaways

1. **HHBBC's whole-program analysis is its biggest differentiator** — this is the AOT compiler's greatest architectural gap. Single-file analysis cannot see cross-file type info, forcing many calls to use dynamic dispatch.

2. **The type system is the core engine of optimization** — HHBBC's trep + specialization + monotonic lattice is a huge investment (about 12k lines), but it is the quality foundation for all optimization passes.

3. **Dependency tracking is the key to incremental analysis** — the Index automatically records "who queried what", so only affected functions need re-analysis, avoiding re-analyzing the whole program each time.

4. **Factored CFG and DataType encoding are more JIT-oriented** — these designs serve runtime JIT optimization. The AOT compiler generates C++ source, and GCC/Clang handle these low-level optimizations, so the investment should not be duplicated.

5. **The test infrastructure is worth learning from** — Quick/Slow layering, directly reusing php-src tests, and multiple run modes all have direct reference value for building the AOT compiler's test CI.

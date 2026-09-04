# PeachPie Compiler Review: Roslyn Integration, Cross-Language Interop, and Type System

> 2026-06-11 · Based on source review of /home/swoole/workspace/cpp/peachpie

---

## 1. Architecture Overview

PeachPie is a **PHP-to-.NET compiler based on Roslyn (Microsoft's .NET compiler platform)**, with roughly 710 C# source files and 272k lines of code.

### Compilation Pipeline

```
PHP source
  → PhpSyntaxTree (Roslyn SyntaxTree for PHP — Syntax/)
  → SemanticModel + Symbols (Roslyn symbol system — Semantics/, Symbols/)
  → BoundControlFlowGraph (CFG with typed IR — Semantics/Graph/, FlowAnalysis/)
  → CIL Bytecode (EMIT — CodeGen/, Emitter/)
  → .NET Assembly (.dll / .exe)
```

| Stage | Component | Responsibility |
|------|------|------|
| Syntax parsing | `Syntax/` (PhpSyntaxTree, NodesFactory) | PHP → Roslyn SyntaxTree |
| Semantic binding | `Semantics/` (SemanticsBinder, BoundExpression) | Name resolution, method binding, type inference |
| Symbol system | `Symbols/` (SourceTypeSymbol, PEMethodSymbol...) | Roslyn symbol table for types/methods/properties |
| Dataflow analysis | `FlowAnalysis/` (FlowState, TypeRefMask, ExpressionAnalysis) | Type inference, unreachable code detection, conditional narrowing |
| CFG optimization | `FlowAnalysis/Passes/` (TransformationRewriter) | CFG rewriting, constantization, dead code elimination |
| Code generation | `CodeGen/` (CodeGenerator, GhostMethodBuilder) | IR → CIL instructions |
| Assembly output | `Emitter/` (PEModuleBuilder) | CIL → PE file (.dll/.exe) |

### Core Files

| File | Lines | Purpose |
|------|------|------|
| `CodeGen/Graph/BoundExpression.cs` | 5,742 | Core IR node definitions and CIL emission |
| `CodeGen/CodeGenerator.Emit.cs` | 4,396 | CIL instruction generation |
| `FlowAnalysis/ExpressionAnalysis.cs` | 2,911 | Expression-level type analysis |
| `Semantics/BoundExpression.cs` | 2,721 | Semantic binding expressions |
| `Runtime/Operators.cs` | 2,573 | Runtime implementation of PHP operators |
| `Runtime/PhpString.cs` | 2,047 | PHP string value type |
| `CodeGen/VariableReference.cs` | 1,863 | Variable references and address analysis |
| `Symbols/Source/SourceTypeSymbol.cs` | 1,795 | Source type symbols |
| `Runtime/Conversions.cs` | 1,603 | Type conversion runtime |

### Comparison with AOT Compiler

| Dimension | PeachPie | AOT Compiler |
|------|----------|-------------|
| Compilation target | PHP → CIL → .NET Assembly | PHP → C++ → binary |
| Compiler framework | Roslyn (C# compiler-as-a-library) | Self-built PHP AST → C++ string |
| IR form | BoundControlFlowGraph (Roslyn pattern) | PHP-Parser AST nodes |
| Type inference | FlowState + TypeRefMask bitset | SSA + manual type annotations |
| Symbol system | Roslyn Symbol hierarchy (complete) | Simplified ClassDef/FunctionDef |
| Output | .NET PE file (cross-platform) | Native binary (Linux/Mac/Windows) |
| Runtime | Peachpie.Runtime (PhpValue, PhpArray, PhpString) | phpx (C++ RAII wrapper around Zend API) |
| Cross-language interop | First-class citizen — PHP ⇄ C# bidirectional calls | FFI only (swoole_cc/cpp extension loading) |
| Parallel compilation | Parallel.ForEach function-level parallelism | Serial execution |
| Code size | ~272k lines of C# (including runtime) | ~15k lines of PHP |
| MSBuild integration | Complete SDK (`dotnet build`) | None |

---

## 2. Reusable Innovative Designs

### 2.1 Roslyn-Based Compiler Architecture

**Files**: all of `Peachpie.CodeAnalysis/`

PeachPie's most central design decision is to be **fully built on the Microsoft Roslyn compiler platform**. This means:

- **Reuse Roslyn's symbol system**: `TypeSymbol`, `MethodSymbol`, `NamedTypeSymbol`, and other standard Roslyn types
- **Reuse Roslyn's metadata emission**: `PEModuleBuilder`, `PEAssemblyBuilder` directly generate PE files
- **Reuse Roslyn's diagnostics system**: `DiagnosticBag`, standardized Error/Warning mechanism
- **Native MSBuild integration**: PHP projects are standard .NET projects (`.csproj` style), compilable with `dotnet build`

**AOT adoption priority: P2**

Currently AOT built all compiler infrastructure from scratch. PeachPie's approach is a useful reference, but not suitable for direct porting (AOT's goal is to generate C++ rather than CIL). What can be adopted:

- Decompose the compiler into standard stages — **Syntax → Semantic → IR → CodeGen** — with clear interfaces per stage
- Separate Preprocessor (symbol collection) into an independent Analyzer stage, similar to Roslyn's Compilation concept
- Define a unified `Diagnostic` type instead of scattered `fatalError()` / `SyntaxError` calls

---

### 2.2 TypeRefMask: 64-bit Type Bitset

**File**: `FlowAnalysis/TypeRef/TypeRefMask.cs`

PeachPie uses `ulong` (64-bit) as the type mask:

```csharp
public struct TypeRefMask {
    ulong _mask;
    // bits 0-61:  type index (up to 62 different types)
    // bit 62:     IncludesSubclasses (type may include subclasses)
    // bit 63:     IsRef (value is a reference/alias)
}
```

Characteristics:
- **O(1) type comparison**: `(mask & type_bit) != 0` detects a type
- **Union types**: `mask1 | mask2` = contains both types
- **Type narrowing**: `mask & ~excluded_type_bit` = excludes a type
- **IsRef marker**: tracks whether a value is assigned by reference, affecting alias analysis
- **IncludesSubclasses**: distinguishes `exactly Class` vs `Class or subclass`

Each `TypeRefContext` maintains a type registry mapping concrete .NET types (such as `System.Int64`, `Pchp.Core.PhpString`) to bit indices.

**AOT adoption priority: P0**

This is consistent with HHVM's trep idea — using a bitset to represent types. AOT's discrete constants like `TYPE_INT`, `TYPE_STRING` can be replaced with a bitset:

```php
class TypeMask {
    const BINT     = 1 << 0;
    const BFLOAT   = 1 << 1;
    const BSTRING  = 1 << 2;
    const BBOOL    = 1 << 3;
    const BARRAY   = 1 << 4;
    const BOBJECT  = 1 << 5;
    // Extension markers
    const BEMPTY      = 1 << 60;  // empty value marker
    const BSUBCLASS   = 1 << 61;  // allow subclasses
    const BREFERENCE  = 1 << 62;  // reference marker
}
```

Advantage: `int|string` = `BINT|BSTRING`, type narrowing = `& ~excluded_bits`.

---

### 2.3 FlowState + Worklist Dataflow Analysis

**Files**: `FlowAnalysis/FlowState.cs`, `FlowAnalysis/Worklist.cs`

PeachPie uses the classic **dataflow worklist algorithm** for type inference:

```csharp
class FlowState {
    TypeRefMask[] _varsType;     // type mask for each variable
    ulong _initializedMask;      // whether variables are initialized
    HashSet<NoteData> _notes;    // additional info (such as function return points)
}
```

**Merge operation**: when two CFG paths converge, the `FlowState(state1, state2)` constructor computes:
- The **union** of type masks (all possible types)
- The **union** of initialization masks (initialized in either branch counts as initialized)
- The **intersection** of notes (only info present on both paths is retained)

The Worklist processes blocks in topological order, re-enqueueing when state changes.

**AOT adoption priority: P1**

Currently AOT's SSA analysis does some type inference, but lacks:
- **Structured FlowState**: a unified variable type-state representation
- **Standard merge operation**: type merging at join points
- **Worklist iteration**: an iterative analysis framework reaching a fixed point

---

### 2.4 ConditionBranch-Aware Type Narrowing

**Files**: `FlowAnalysis/ConditionBranch.cs`, `FlowAnalysis/AnalysisFacts.cs`

PeachPie's type analysis is **aware of the conditional branch of the current context**:

```csharp
enum ConditionBranch {
    AnyResult = 0,   // ordinary evaluation
    ToTrue = +1,     // branch where the expression result is true
    ToFalse = -1,    // branch where the expression result is false
}
```

In conditional expressions, the analyzer carries the branch direction to propagate type info:

```csharp
// if ($x instanceof MyClass) { ... }
// In the ToTrue branch:
//   $x's type is narrowed, excluding types that cannot be MyClass
// In the ToFalse branch:
//   $x's type is narrowed, excluding MyClass

// if (is_int($x)) { ... }
// In the ToTrue branch:
//   $x's type is narrowed to int
```

`AnalysisFacts.HandleSpecialFunctionCall()` registers type-check functions like `is_int`, `is_string`, `is_array`, `is_callable`, `function_exists`, `class_exists`, automatically narrowing variable types in branches.

**AOT adoption priority: P1**

Currently `SsaTypeOptimizer` does some instanceof narrowing, but:
- Does not support narrowing via built-in type-check functions like `is_int()` / `is_string()`
- Does not support constant folding of existence checks like `class_exists()` / `function_exists()`
- Can directly adopt `AnalysisFacts`'s "known type-check function registry" pattern

---

### 2.5 PhpValue Tagged Union Design

**File**: `Peachpie.Runtime/PhpValue.cs`

PeachPie's runtime value type uses an ingenious C# tagged union:

```csharp
[StructLayout(LayoutKind.Sequential)]
public readonly partial struct PhpValue {
    readonly PhpTypeCode _type;   // 1-byte type tag

    // Explicit-layout union: two fields occupy the same memory
    [StructLayout(LayoutKind.Explicit)]
    struct ValueField {
        [FieldOffset(0)] public bool @bool;
        [FieldOffset(0)] public long @long;
        [FieldOffset(0)] public double @double;
    }

    [StructLayout(LayoutKind.Explicit)]
    struct ObjectField {
        [FieldOffset(0)] public object @object;
        [FieldOffset(0)] public string @string;
        [FieldOffset(0)] public PhpString.Blob blob;
        [FieldOffset(0)] public PhpArray array;
        [FieldOffset(0)] public PhpAlias alias;
    }

    readonly ValueField _value;    // value-type storage
    readonly ObjectField _obj;     // reference-type storage
}
```

Memory layout: `PhpValue` = `PhpTypeCode` (1 byte) + padding + `ValueField` (8 bytes) + `ObjectField` (8 bytes, pointer) ≈ 24 bytes.

Advantages of this design:
- **readonly struct** — no GC overhead, allocatable on the stack
- **Explicit union** — value types and reference types share space, compact
- **PhpAlias mechanism** — abstracts PHP references (copy-on-write) via `PhpAlias` rather than directly copying values
- **MutableString** — distinguishes immutable string from writable MutableString (for string concatenation optimization)

**AOT adoption priority: P3**

Currently AOT uses `Variant` (based on Zend `zval`) as the dynamic type. What can be adopted:
- PhpString's MutableString separation (string builder pattern)
- PhpAlias's reference semantics abstraction
- But replacing `Variant` entirely is a huge effort, so low priority

---

### 2.6 GhostMethodBuilder: PHP Method ⇄ C# Method Adaptation

**File**: `CodeGen/GhostMethodBuilder.cs`

PeachPie's most distinctive feature is **automatically generating ghost stub methods** so PHP methods can be called directly from C#:

```csharp
// Generate C#-callable wrappers for PHP methods:
// - Handle parameter type conversion (PhpValue → CLR type)
// - Handle return type conversion (CLR type → PhpValue)
// - Build and pass PhpContext
// - Support explicit interface override
static MethodSymbol CreateGhostOverload(
    MethodSymbol original, NamedTypeSymbol containingtype,
    PEModuleBuilder module, DiagnosticBag diagnostic,
    TypeSymbol ghostreturn, ImmutableArray<ParameterSymbol> ghostparams,
    bool phphidden = false, MethodSymbol explicitOverride = null)
```

Ghost methods enable:
- Type-safe interfaces when C# calls PHP methods
- PHP implementing C# interfaces (IMethod, INotifyPropertyChanged, etc.)
- PHP classes usable as .NET generic parameters

**AOT adoption priority: P4**

Currently AOT does not support PHP calling C++ (or vice versa). If a bidirectional interop layer is needed in the future, the ghost stub pattern is worth referencing.

---

### 2.7 DelayedTransformations: Parallel-Safe Deferred Transformations

**File**: `FlowAnalysis/Passes/DelayedTransformations.cs`

During the parallel analysis phase, certain transformations (such as marking unreachable functions, promoting conditional functions to unconditional ones) cannot directly modify shared state. PeachPie uses the **deferred transformation** pattern:

```csharp
class DelayedTransformations {
    ConcurrentBag<SourceRoutineSymbol> UnreachableRoutines;
    ConcurrentBag<SourceTypeSymbol> UnreachableTypes;
    ConcurrentBag<SourceFunctionSymbol> FunctionsMarkedAsUnconditional;
    // Collected thread-safely during parallel analysis
    // Applied serially via Apply() after analysis completes
}
```

Analysis threads only place the objects to be transformed into the `ConcurrentBag`, and after analysis completes a single thread calls `Apply()`.

**AOT adoption priority: P2**

AOT is currently serial and doesn't need this. But if parallel compilation is introduced in the future (see KPHP review 2.3), deferred transformation is the foundational pattern for thread safety.

---

### 2.8 Native MSBuild Integration (Peachpie.NET.Sdk)

**File**: `Peachpie.NET.Sdk/`

PeachPie is not just a compiler — it is a **complete .NET SDK**:

```
dotnet new classlibrary -o MyPhpLib  # create a PHP class library project
dotnet build                           # compile PHP → .NET DLL
dotnet run                             # run the compiled program
dotnet publish                         # publish as a self-contained app
```

Implemented via MSBuild targets/props:
- `build/peachpie.targets` — defines the compilation task
- `Peachpie.NET.Sdk.nuspec` — NuGet package definition
- `BuildTask.cs` — MSBuild compilation task

This means PHP projects can seamlessly use the .NET ecosystem: NuGet package references, project references, conditional compilation, multi-targeting, etc.

**AOT adoption priority: P2**

AOT currently uses `php bin/tpc.php <project>` on the command line. What can be adopted:
- Create a Composer plugin or CLI phar package for the AOT compiler
- Define a JSON Schema for `project.yml` (similar to `.csproj`)
- Support a unified entry point like `composer build` or `php-aot build`

---

### 2.9 Lazily Evaluable AnalysisFacts

**File**: `FlowAnalysis/AnalysisFacts.cs`

PeachPie performs constant evaluation of a large number of PHP runtime functions at compile time:

| Function | Evaluation strategy |
|------|---------|
| `function_exists(X)` | Check if symbol X exists in the PE assembly → fold to `true` |
| `class_exists(X)` | Check if type X exists in the PE assembly → fold to `true`/`false` |
| `method_exists(X, M)` | Check if method M exists in type X → fold to `true`/`false` |
| `defined(CONST)` | Check if the constant exists → fold to `true`/`false` |
| `is_callable(F)` | Check if F is unconditionally declared → fold to `true` |
| `dirname(__FILE__)` | Compile-time path computation → `__DIR__` |
| `basename(__FILE__)` | Compile-time filename extraction → string constant |

These evaluations leverage PeachPie's "PE assembly" concept — already-compiled .NET assemblies contain complete type/method/constant metadata that can be **queried at compile time**.

**AOT adoption priority: P1**

Currently `FuncCallOptimizer` only does the most basic constant folding (`strlen("abc")` → `3`). It can be extended to compile-time evaluation of reflective functions like `function_exists`, `class_exists`, `defined` — provided a complete symbol table is built in Preprocessor.

---

### 2.10 Conditional Declaration Detection and Unreachable Code Elimination

**Files**: `FlowAnalysis/Passes/DelayedTransformations.cs`, `FlowAnalysis/Passes/TransformationRewriter.cs`

PeachPie can detect **conditional declarations** (`if (condition) { function foo() {} }`) and optimize them:

- If analysis proves the condition is always true → the function is marked as unconditionally declared
- If analysis proves the condition is always false → the function/class is marked as Unreachable and not compiled

This enables writing C-like conditional compilation patterns in PHP:

```php
if (PHP_VERSION_ID >= 80000) {
    function newFeature() { ... }  // not compiled on lower versions
}
```

**AOT adoption priority: P2**

Currently AOT compiles every scanned function regardless of reachability. For projects with multi-version PHP compatibility code, conditional declaration elimination can reduce compilation artifact size.

---

## 3. Toolchain Analysis

### 3.1 Test Infrastructure

PeachPie has 529 test files (PHP files) distributed across functional directories:

| Directory | Content |
|------|------|
| `tests/arrays/` | Array operation tests (including a `lazy_copy` subdirectory) |
| `tests/classes/` | Class/object tests |
| `tests/functions/` | Function call tests |
| `tests/generators/` | Generator/yield tests |
| `tests/strings/` | String operation tests |
| `tests/operators/` | Operator tests |
| `tests/transformations/` | Compiler transformation/optimization tests |
| `tests/constants/` | Constant tests |
| `tests/constructs/` | Language construct tests |
| `tests/traits/` | Trait tests |
| `tests/reflection/` | Reflection tests |
| `tests/spl/` | SPL tests |
| `tests/bcmath/` `tests/hash/` `tests/pcre/` | Extension tests |
| `tests/pdo/` `tests/ftp/` `tests/openssl/` | Database/network extensions |
| `tests/gd/` `tests/xml/` `tests/zip/` | Graphics/XML/ZIP extensions |
| `tests/web/` `tests/scripting/` | Web/scripting integration tests |

Test execution: compiled assemblies are executed through the .NET test framework (xUnit/NUnit).

**AOT adoption:**
- The `tests/transformations/` directory specifically tests compiler optimizations — AOT can build similar optimization-correctness tests
- `tests/arrays/lazy_copy/` specifically tests copy-on-write behavior — AOT can also build dedicated COW tests

### 3.2 Deep Visual Studio Integration

PeachPie provides a complete IDE experience:
- **Visual Studio Extension** — project management, IntelliSense, debugging, performance analysis
- **VS Code / Rider support** — via OmniSharp / LSP
- **NuGet package management** — PHP libraries can be published and referenced as NuGet packages

**AOT adoption:**
- Can provide a VSCode extension (Task integration + project templates)
- AOT's current `project.yml` can use a JSON Schema to provide IDE auto-completion

### 3.3 Command-Line Toolchain

```bash
# PeachPie's CLI experience
dotnet peach build              # compile a PHP project
dotnet peach run                # run the compiled program
dotnet peach publish            # self-contained publishing
dotnet peach add <package>      # add a dependency
```

**AOT adoption:**
```bash
# A similar CLI could be designed
php-aot build                    # compile a project
php-aot run                      # compile and run
php-aot new <name>               # create a new project
```

---

## 4. Type System and Interop Analysis

### 4.1 PeachPie Type Mapping

| PHP type | PeachPie runtime type | .NET CLR type |
|----------|-------------------|---------------|
| null | `PhpTypeCode.Null` | `null` (any reference type) |
| bool | `PhpTypeCode.Boolean` | `bool` |
| int | `PhpTypeCode.Long` | `long` |
| float | `PhpTypeCode.Double` | `double` |
| string | `PhpTypeCode.String` / `MutableString` | `string` / `PhpString` |
| array | `PhpTypeCode.PhpArray` | `PhpArray` |
| object | `PhpTypeCode.Object` | `object` (concrete class) |
| reference | `PhpTypeCode.Alias` | `PhpAlias` |

### 4.2 PHP ⇄ C# Interop

PeachPie's bidirectional interop is its most prominent differentiator:

**C# calling PHP:**
```csharp
// Compiled PHP classes become .NET classes; C# can directly new and call them
var phpObj = new MyPhpClass(ctx);
phpObj.someMethod(arg1, arg2);
```

**PHP calling C#:**
```php
// .NET types can be used directly in PHP
$list = new \System\Collections\Generic\List<int>;
$list->Add(42);
```

Interop implementation relies on:
- `GhostMethodBuilder` generating adapter methods
- `ConversionsExtensions` handling automatic PhpValue ↔ CLR type conversion
- `DynamicOperationFactory` handling dynamic method call forwarding

### 4.3 Syntax Differences from the AOT Compiler

| Feature | PeachPie | AOT Compiler |
|------|----------|-------------|
| Base PHP version | 8.0+ (target) | 8.2+ |
| Type annotations | Optional (gradually enriched) | Optional (phpstan annotations) |
| Namespaces | Standard PHP | Standard PHP |
| Generics | None | None |
| C# interop | Complete (first-class citizen) | FFI extension only |
| .NET ecosystem | Fully compatible | Not relevant |
| MSBuild integration | Complete | None |
| Reflection | Partial support | Not supported |
| yield/generator | Supported | Supported |

---

## 5. Summary and Priority Recommendations

| Priority | Technique | Difficulty | Benefit | Notes |
|--------|------|------|------|------|
| **P0** | TypeRefMask bitset type system | Medium | Very high | Union types, type narrowing, non-null markers — foundation for all optimization passes |
| **P1** | ConditionBranch type narrowing | Low | High | Automatic narrowing via `is_int()`/`is_string()` check functions, not covered by current SSA |
| **P1** | AnalysisFacts compile-time evaluation | Medium | High | Compile-time folding of `function_exists`/`class_exists`/`defined` |
| **P1** | FlowState worklist analysis framework | Medium | High | Structured variable type state, standard merge operation |
| **P2** | Roslyn-style compiler layering | High | Medium | Clear Syntax→Semantic→IR→CodeGen layering; current Preprocessor/CompilerBase responsibilities are mixed |
| **P2** | Conditional declaration detection | Low | Medium | Eliminate unreachable functions/classes, reduce compilation artifact size |
| **P2** | MSBuild integration / CLI unification | Low | Medium | Standardized project configuration, CI-friendly |
| **P3** | PhpValue tagged union | High | Medium | Compact memory layout, but replacing Variant/zval is a large effort |
| **P3** | DelayedTransformations | Low | Low | Only meaningful under parallel compilation |
| **P4** | GhostMethodBuilder interop | Very high | Low | Requires .NET or a similar FFI runtime |
| **P4** | Deep IDE integration | High | Low | Limited ROI for a VSCode extension |

### Key Takeaways

1. **PeachPie's biggest advantage is .NET ecosystem integration** — this is a natural advantage from its architectural choice rather than any single technical innovation. AOT can adopt its "compiler SDK + standardized CLI" thinking without porting specific techniques.

2. **TypeRefMask bitset is the most directly portable design** — representing types with a 64-bit bitset while supporting union types, subclass markers, and reference markers. This is a common feature of HHBBC's trep and KPHP's type analysis, indicating that the bitset type lattice is the best practice for PHP compilers.

3. **The ConditionBranch pattern is a low-cost, high-benefit enhancement** — carrying "expected result" info in conditional branch analysis enables type-check functions to automatically narrow variable types. AOT's SSA analysis can adopt it immediately.

4. **GhostMethodBuilder reveals a general interop pattern** — generating adapter/thunk methods to bridge the calling conventions of two languages. Although AOT targets C++ rather than .NET, if cross-language call needs arise (such as PHP calling C extensions), this pattern can be reused.

5. **PeachPie's roughly 530 tests** are notably fewer than KPHP (75+ directories) and HHVM (14,675), indicating relatively lower maturity. But its way of categorizing tests by functional module and optimization type is worth referencing.

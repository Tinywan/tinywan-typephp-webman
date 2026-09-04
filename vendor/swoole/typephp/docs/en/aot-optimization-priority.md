# AOT Compiler Optimization Priority Re-evaluation: Considering GCC/Clang Secondary Compilation

## Core Principle

The optimization value of AOT does not lie in doing what GCC/Clang already do, but in **providing information that GCC/Clang cannot infer from the C++ code**.

What GCC/Clang can already do under `-O2`/`-O3`:
- Constant folding, constant propagation (SCCP)
- Dead code elimination (DCE)
- Common subexpression elimination (CSE)
- Loop unrolling, vectorization
- Instruction selection, register allocation
- Function inlining (within the same translation unit)
- Branch prediction optimization
- SIMD auto-vectorization

What GCC/Clang **cannot do** — because the semantics are obscured by abstraction layers such as `php::Var`, `php::Object`, and virtual function calls:
- Narrow `php::Var` to a concrete C++ type
- Eliminate virtual calls of `php::Object`
- Eliminate reference counting operations
- Move `php::Array` from heap allocation to stack allocation
- Downgrade `php::BigInt` to native `int64_t`
- Eliminate the function lookup/dispatch overhead of `php::call`
- Cross-translation-unit global analysis (LTO can partially do this, but is limited by visibility)

---

## Reordered Priorities

### Tier 1: Directly Improve C++ Code Shape (GCC Cannot Fix)

These optimizations change **what C++ code is generated**, not the C++ code that has already been optimized. This is the core value of AOT.

#### #1 Type Inference → Generate Concrete C++ Types Instead of php::Var

**Problem:** Currently AOT maps a large number of variables to `php::Var` (a general type), and GCC can only perform limited optimization on the member functions of `php::Var`.

**Benefit:**
```
// Currently generated code (GCC can't do much)
php::Var a = php::toInt(x);
php::Var b = php::toInt(y);
php::Var c = php::add(a, b);   // GCC can't see this is addition

// After type inference
int64_t a = php::toInt(x);
int64_t b = php::toInt(y);
int64_t c = a + b;             // GCC can perform all integer optimizations
```

**Value amplification for GCC secondary compilation:** once the type becomes `int64_t`, GCC can further:
- Register allocation (no longer through `php::Var`'s memory layout)
- Constant folding and propagation
- Loop optimization (operations on `php::Var` inside loops become pure integer arithmetic)
- Automatic SIMD

**Implementation recommendation:** this is the **highest priority**. A complete SSA form is not needed; just infer the most precise type at each assignment point and use that type to declare the variable during C++ code generation. The existing union/nullable type check infrastructure can be extended into type inference.

---

#### #2 Devirtualization

**Problem:** all non-final method calls in PHP are virtual calls. AOT generates `obj->method()` (a C++ virtual function call) even if only one subclass implements the method.

**Benefit:**
```
// Currently generated (virtual call, GCC dares not eliminate the vtable lookup)
return self->foo();     // virtual call

// After devirtualization (GCC can inline this call!)
return Aot_MyClass_foo(self);  // direct function call
```

**Breakthrough:** collect all classes and their inheritance relationships at compile time. If:
- The method is private → always call directly
- The method is final → always call directly
- The class has only 1 non-abstract implementation across all compiled files → call directly
- `self::foo()` calls a method of its own class → can call directly (if the class has no undiscovered subclasses)

**Synergy with GCC:** once it becomes a direct call, GCC can:
- Inline the entire function body
- Cross-function constant propagation
- Chain-eliminate subsequent redundant operations

---

#### #3 Escape Analysis → Stack Allocation + Reference Count Elimination

**Problem:** every `new` object/array is allocated on the heap and its lifetime is managed through reference counting.

**Benefit:**
```
// Currently (heap allocation + refcount)
php::Array arr = php::newArray();
arr.set("key", value);     // refcount operations
return arr;                // copy + refcount

// After escape analysis (stack allocation + no refcount)
zend_array arr;             // on the stack
zend_hash_update(&arr, "key", value);  // no refcount
return php::Array::fromStack(std::move(arr));  // pack only at the return point
```

**Impact on GCC secondary compilation:** this is the optimization that helps GCC the **most**, because:
- Heap allocation → stack allocation: eliminates `malloc` calls, and GCC can fully optimize the stack layout
- Eliminating refcount: GCC does not need to analyze the side effects of interlocked operations and can freely reorder instructions
- GCC can perform SROA (Scalar Replacement of Aggregates) on stack variables, breaking arrays/objects into scalars

**Implementation recommendation:** even simple local escape analysis (only analyzing whether an object is passed outside the function) can eliminate a large number of allocations. The complex version (cross-function escape analysis) continues to increase the benefit.

---

#### #4 Call-Graph-Driven Cross-File Inlining

**Problem:** the same PHP project may be compiled into multiple `.cc` files; GCC's LTO can inline across files but is limited by compilation time.

**Benefit:** AOT knows the entire call graph at compile time and can **decide inlining while generating C++ code**:

```
// Currently
auto result = aot_smallHelper(x, y);  // function call, even if the body is one line

// After inlining
auto result = x + y;  // GCC can continue optimizing
```

**Key decision information:**
- Function body size (< 10 lines → inline candidate)
- Call count (called only once → inlining can fully eliminate the function)
- Recursion marker (recursive functions are not inlined)
- Cross-function constant propagation opportunities (arguments are constants → after inlining GCC can fold the entire function)

**Synergy with GCC:** AOT makes the "decision" (whether to inline) and generates the inlined code directly at the call site. GCC continues optimizing on the larger inlined body. AOT possesses information that GCC does not (a global view from the call graph).

---

### Tier 2: Provide More Precise Type Information to GCC

These optimizations improve the quality of the C++ types passed to GCC.

#### #5 Integer Range Inference → Choose the Optimal Integer Type

**Benefit:**
```php
// PHP source: loop counter, 0 to 100
for ($i = 0; $i < 100; $i++) { ... }

// Currently generated by AOT
int64_t i = 0;            // always uses int64_t (to prevent overflow)

// After range inference
int8_t i = 0;             // 0..100 is enough, better cache locality
// or at least
int64_t i = 0;            // but marked "no overflow possible", no BigInt promotion inserted
```

**Impact on GCC:**
- Smaller types → better vectorization (more elements packed into SIMD registers)
- "Will not overflow" assertions → GCC's VRP (Value Range Propagation) can perform more aggressive optimizations based on this premise

#### #6 Avoid Unnecessary BigInt/BigFloat Allocation

**Related to #5.** PHP's integer arithmetic automatically promotes to float or BigInt on overflow. If AOT can prove no overflow, no promotion code needs to be generated.

```
// Currently (every int operation must consider overflow)
php::Var result = php::BigInt::add(php::toBigInt(a), php::toBigInt(b));

// After range inference (no overflow)
int64_t result = a + b;  // pure integer, GCC's world
```

---

### Tier 3: Partially Overlapping with GCC but Still Valuable

#### #7 Constant Folding (PHP Level)

**What GCC can do:** constant expressions at the C++ level are fully folded in GCC's SCCP pass.

**AOT's unique value:**
- PHP-specific constants: `PHP_INT_MAX`, `PHP_VERSION`, `__DIR__`, etc. are fully resolved at compile time
- Constant resolution across PHP namespaces/class names (GCC cannot see PHP's symbol semantics)
- Results of PHP built-in functions: `strlen("hello")` → 5 (GCC does not know `strlen`'s internal implementation but can fold functions with known arguments)

**Assessment:** limited value. Most PHP source code does not contain complex compile-time-evaluable constant expressions.

#### #8 Dead Code Elimination (PHP Level)

**What GCC can do:** GCC's DCE + unreachable block elimination is very mature.

**AOT's unique value:**
- Eliminate branches based on the PHP type system: `if (false)` eliminated at the PHP level → no C++ code generated
- Eliminate branches based on type narrowing: `if ($x instanceof Foo)` when `$x`'s type is already determined to be `Bar` and `Foo ⊄ Bar`, the false branch is unreachable
- Eliminate uncalled PHP functions/classes (cross-file dead code)

**Assessment:** medium value. AOT should focus on "DCE based on PHP semantics" rather than competing with GCC on "DCE based on C++ semantics".

---

### Tier 4: Improve the Compilation Process Rather than Output Quality

#### #9 File Caching (Compilation Acceleration)

**What GCC can do:** ccache. But ccache requires file content hash matching.

**AOT's unique value:**
- Cache PHP→C++ translation results (parsed AST + type information)
- Reuse across restarts
- Incremental updates (only recompile changed PHP files)

**Assessment:** useful but not core. First optimize the output code quality, then optimize compilation speed.

#### #10 Pass Pipeline Architecture

Infrastructure at the engineering architecture level, improving code maintainability and extensibility. Does not directly affect output quality.

---

## Priority Summary

```
Must do (directly affects the C++ types GCC sees):
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⭐ #1  Type inference → avoid php::Var generalization     【largest single-point benefit】
⭐ #2  Devirtualization → eliminate virtual call overhead  【second-largest benefit】
⭐ #3  Escape analysis → stack allocation + eliminate refcount   【great benefit for numeric code】

Strongly recommended (give GCC better premises):
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⭐ #4  Call-graph inlining → cross translation-unit boundaries
⭐ #5  Range inference → optimal integer type selection
⭐ #6  Eliminate unnecessary BigInt/BigFloat allocation

Nice to have (unique value but not core):
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  #7  PHP-level constant folding
  #8  PHP-semantic-level DCE
  #9  Compilation cache acceleration

Engineering foundation:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  #10 Pass Pipeline architecture
```

## Further Analysis of #1 Type Inference

This is the largest single-point benefit item and deserves further elaboration. The following is the concrete technical path:

### Key Insight: No Complete SSA Needed

AOT **generates C++ code**, not optimized bytecode. This means:

1. No φ functions needed: C++ variable assignment is naturally the "fresh variable" form of SSA
2. No dominance frontier computation needed: the C++ compiler does it itself
3. Only need to compute the possible type of **each expression** and generate the corresponding C++ type declaration

### Current Problem

```
PHP source:     $a = 1;
                $b = $a + 2;
Generated C++:  php::Var a = php::toInt(php::toVar(1));
                php::Var b = php::add(a, php::toVar(2));  // !!all Var!!
```

What GCC sees at this point:
- All values are `php::Var` (a complex struct)
- Operations go through the `php::add()` function (external symbol, not inlinable)
- Cannot determine types, cannot narrow

### Target

```
PHP source:     $a = 1;
                $b = $a + 2;
Generated C++:  int64_t a = 1;
                int64_t b = a + 2;       // GCC can fully optimize
```

### Implementation Path

**Phase A: Local type propagation (~300 lines)**

Traverse the AST, at each assignment point:
1. If the RHS is a literal → type is clear, record it
2. If the RHS is a variable of known type → propagate the type
3. If the RHS is a binary operation and both operand types are known → infer the result type
4. If a variable is assigned different types multiple times → degrade to `php::Var`

**Phase B: Conditional narrowing (~200 lines, depends on TypeSpecifier)**

```
if ($x instanceof MyClass) {   // entering this branch, $x's value is determined to be MyClass
    $x->method();              // direct call can be used here
}
```

**Phase C: Cross-basic-block merge (~200 lines)**

```
if (cond) { $x = 1; } else { $x = 2; }
// merge point: $x is int64_t (both branches are integers)
```

---

## Safety Constraints on Type Narrowing: PHP instanceof Semantics Analysis

### Problem

The user pointed out: the semantics of `if ($x instanceof Foo)` in PHP is that `$x` may be an instance of `Foo` **or any of its subclasses**. This is the same as C++'s `dynamic_cast<Foo*>`.

This means:

```php
if ($x instanceof Foo) {
    $x->method();  // virtual call! $x's actual class may override method()
}
```

You **cannot** turn `$x->method()` into a direct call to `Foo::method()` just because it was narrowed to `Foo` — unless `Foo` is a final class or method is private.

### Three Safety Levels of Narrowing

#### Level 1: Type member accessibility (always safe)

Even without knowing the exact type, as long as you know `$x` is a subtype of `Foo`, you can:
- Confirm `$x` has access to a certain method/property
- Eliminate impossible code paths (`if ($x instanceof Foo)` is false when `$x` is known to be `Bar` and `Foo` and `Bar` are unrelated)
- Generate correct C++ type annotations (`Foo*` instead of `void*`/`php::Var`)

```php
// Before narrowing: $x is php::Var, calling foo() may throw an exception
// After narrowing: $x is some subtype of Foo, definitely has a foo() method
$x->foo();  // can confirm the method exists (though still a virtual call)
```

**Benefit:** eliminate runtime checks for nonexistent methods, improve error detection (found at compile time).

#### Level 2: final class + private method (fully safe)

| Scenario | Can narrow to exact type? | Can call directly? |
|------|-------------------|------------|
| `$x instanceof FinalClass` | Yes | Yes |
| `$x->privateMethod()` | Yes (private cannot be overridden) | Yes |
| `self::method()` and class has no subclasses | Yes | Yes |
| `$x instanceof NonFinalClass` | No | No |

```php
final class Logger {
    public function log(string $msg): void { ... }
}

if ($x instanceof Logger) {
    $x->log("test");  // safe: direct call to Aot_Logger_log($x, "test")
}
```

#### Level 3: Finite subclass set + guard (guard-based devirtualization)

When `Foo` is not final, but AOT compiled the entire codebase and can determine **all subclasses of Foo**:

```
Known class hierarchy:
  Foo (abstract)
  ├── SubFooA    method() implementation
  └── SubFooB    method() implementation
```

At this point `$x instanceof Foo` means `$x` can only be `SubFooA` or `SubFooB`. It can generate:

```cpp
// guard-based devirtualization
if (x.getInstanceOf(get_class(SubFooA))) {
    Aot_SubFooA_method(x);          // direct call
} else {
    Aot_SubFooB_method(x);          // direct call (the last one needs no check)
}
// replaces the original: x->method(); (virtual call)
```

**Benefit:** for class hierarchies with a known finite subclass set, replace vtable lookup with if-else dispatch. If there is only 1 subclass (de facto final), the branch is fully eliminated.

### How PHP Itself Handles This

The `zend_ssa_var_info` structure of Zend SSA precisely tracks this distinction:

```c
typedef struct _zend_ssa_var_info {
    uint32_t  type;
    zend_class_entry *ce;
    bool  is_instanceof : 1;  // 0 = class == ce, 1 = may be child of ce
    // ...
} zend_ssa_var_info;
```

- `is_instanceof = false`: the variable is **exactly this class** (from a `new Foo()` or a `get_class($x) === 'Foo'` check)
- `is_instanceof = true`: the variable **may be this class or its subclass** (from `$x instanceof Foo`)

Only when `is_instanceof = false` and ce is non-abstract can it be safely devirtualized.

### Impact on Tier 1 Optimizations

| Optimization | Affected by instanceof semantics? | Valid scope |
|------|------------------------|---------|
| #1 Type inference (generate concrete C++ types) | Not directly affected | Narrowing to `Foo*` is still valuable (better than `php::Var`) |
| #2 Devirtualization | **Yes — instanceof does not provide exact type** | Only final classes, private methods, finite subclass guards |
| #3 Escape analysis | Not directly affected | Does not need exact type, only escape/non-escape determination |
| #4 Call-graph inlining | Not directly affected | Call-graph analysis concerns function relationships, unrelated to instanceof narrowing |
| #5 Range inference | Not directly affected | Integer ranges are unrelated to OOP inheritance |

### Key Conclusion

Although `instanceof` narrowing is not sufficient to deterministically devirtualize all calls, it provides the guarantee of **type member visibility**, which can eliminate method lookups, improve error detection, and generate more precise C++ type signatures (`Foo*` vs `php::Var`).

The information that can truly drive devirtualization comes from:
1. **Final classes** — provided by source declarations
2. **Whole-program class hierarchy analysis** — after compiling all code, one knows which classes are de facto final (no subclasses in the codebase)
3. **Call-site-specific type inference** — tracking from `new Foo()` assignment to the call site

---

## Conclusion

Under the GCC/Clang secondary compilation architecture, the AOT compiler's optimization strategy should be:

1. **"Freeze" PHP's dynamic type information into static C++ types** — this is what GCC cannot do itself
2. **"Land" virtual calls as direct calls** — so GCC can inline
3. **"Promote" heap allocation to stack allocation** — so GCC can perform SROA
4. **Do not compete with GCC on SSA-level optimization** — SCCP, DCE, CSE are left to GCC
5. **Focus on cross-translation-unit information** — call graph, class hierarchy — which even LTO has difficulty covering

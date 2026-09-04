# PHP Zend Optimizer / OPcache / JIT Design Analysis: Mechanisms That Can Be Introduced into an AOT Compiler

This document analyzes the implementation of the Zend Optimizer, OPcache, and JIT in php-src (v8.4.14), identifying the design patterns, algorithms, and data-flow frameworks that can be introduced into an AOT compiler.

Source locations:
- Optimizer: `~/soft/php/php-8.4.14/Zend/Optimizer/`
- OPcache: `~/soft/php/php-8.4.14/ext/opcache/`
- JIT: `~/soft/php/php-8.4.14/ext/opcache/jit/`

---

## Overview

PHP's optimizer is one of the most mature high-end optimizers for dynamic languages in the field. It contains complete compiler optimization infrastructure, including full SSA (Static Single Assignment) construction, e-SSA (Extended SSA) type/range inference, SCCP (Sparse Conditional Constant Propagation), escape analysis, dead code elimination, call graph analysis, profile-guided tracing JIT, and more.

An AOT compiler can borrow from the following aspects:

| Priority | Design/Module | Implementation Scale | Benefit |
|--------|----------|---------|------|
| P1 | Pass Pipeline architecture | ~100 lines of framework | Clear architecture, pluggable, split into O0/O1/O2 |
| P1 | SSA + e-SSA construction | Medium | Foundation for all advanced optimizations |
| P1 | Type inference (Type & Range Inference) | Medium | Precise type deduction capability |
| P2 | SCDF generic data-flow framework | ~300 lines | Reusable for SCCP/type inference/optimization |
| P2 | SCCP constant propagation | Medium | Conditional constant folding + unreachable code elimination |
| P2 | DCE dead code elimination | ~400 lines | Worklist-driven precise DCE |
| P3 | Escape Analysis | ~500 lines | Stack allocation, reference count elimination |
| P3 | Call Graph | ~400 lines | Cross-function analysis, inlining decisions, dead code |
| P4 | JIT IR framework | Very heavy | Reference the abstraction levels of its IR design |
| P4 | OPcache persistence/File Cache | Light | Serialized IR cache after optimization |

---

## 1. Pass Pipeline Architecture

### Design

PHP defines 16 optimization passes, each corresponding to a bitmask bit (`zend_optimizer.h:28-46`):

```c
#define ZEND_OPTIMIZER_PASS_1      (1<<0)   // Simple local optimization (constant replacement/folding)
#define ZEND_OPTIMIZER_PASS_2      (1<<1)   //
#define ZEND_OPTIMIZER_PASS_3      (1<<2)   // Jump optimization
#define ZEND_OPTIMIZER_PASS_4      (1<<3)   // INIT_FCALL_BY_NAME -> DO_FCALL
#define ZEND_OPTIMIZER_PASS_5      (1<<4)   // CFG optimization (block pass)
#define ZEND_OPTIMIZER_PASS_6      (1<<5)   // DFA optimization (type/range inference → single function)
#define ZEND_OPTIMIZER_PASS_7      (1<<6)   // CALL GRAPH optimization (cross-function analysis)
#define ZEND_OPTIMIZER_PASS_8      (1<<7)   // SCCP (constant propagation)
#define ZEND_OPTIMIZER_PASS_9      (1<<8)   // Temporary variable optimization
#define ZEND_OPTIMIZER_PASS_10     (1<<9)   // NOP removal
#define ZEND_OPTIMIZER_PASS_11     (1<<10)  // Merge identical constants
#define ZEND_OPTIMIZER_PASS_12     (1<<11)  // Adjust stack usage
#define ZEND_OPTIMIZER_PASS_13     (1<<12)  // Remove unused variables
#define ZEND_OPTIMIZER_PASS_14     (1<<13)  // DCE (dead code elimination)
#define ZEND_OPTIMIZER_PASS_15     (1<<14)  // Collect constants (unsafe)
#define ZEND_OPTIMIZER_PASS_16     (1<<15)  // Function inlining
```

### Pipeline Scheduling

The `zend_optimize()` function (`zend_optimizer.c:1067-1183`) executes each pass in order, with each pass only processing the results produced by already-executed passes:

```
pass1 (constant folding) → pass3 (jump optimization) → pass4 (function call optimization)
  → pass5 (CFG) → pass6 (DFA + type inference) → pass9 (temporary variables)
  → pass10 (NOP removal) → pass11 (constant merging) → pass13 (variable cleanup) → ...
```

When `PASS_6 + PASS_7` are enabled simultaneously, `zend_optimize_script()` takes a more complex call graph path:
```
build_call_graph → zend_optimize (per-func) → analyze_call_graph
  → build call_map → dfa_analyze_op_array (per-func)
  → dfa_optimize_op_array (per-func, with call context)
  → pass9 → pass11 → pass13 → pass12 (stack adjust) → redo_pass_two
```

### Key Design Points

**1. Bitmask switches + registered passes**

Users can combine arbitrary passes by bitmask. It also supports `zend_optimizer_register_pass()` to register external passes (such as JIT's own optimization passes):

```c
static struct {
    zend_optimizer_pass_t pass[ZEND_OPTIMIZER_MAX_REGISTERED_PASSES];
    int last;
} zend_optimizer_registered_passes;
```

Registered passes execute after all built-in passes (`zend_optimizer_call_registered_passes`).

**2. Optional dump output per pass**

Controlled via `debug_level`, supporting output of the intermediate representation before/after any pass for debugging and performance analysis.

**3. Dual-layer optimization: per-function and script-level**

- `zend_optimize(op_array, ctx)` — single-function optimization, conservative (does not use cross-function information)
- `zend_optimize_script(script, ...)` — whole-script optimization with call graph, enabling cross-function optimization

### AOT Takeaways

The AOT compiler can define a similar pass pipeline:

```php
enum AotPass: int {
    case CONSTANT_FOLD       = 1 << 0;
    case TYPE_CHECK_INSERT   = 1 << 1;
    case ESCAPE_ANALYSIS     = 1 << 2;
    case DEVIRTUALIZE        = 1 << 3;
    case DEAD_CODE_ELIM      = 1 << 4;
    case FUNCTION_INLINE     = 1 << 5;
    case LOOP_OPTIMIZE       = 1 << 6;
    case BOX_ALLOC_ELIM      = 1 << 7;
}
```

Combined by optimization level:

```php
const O0 = AotPass::TYPE_CHECK_INSERT->value;  // Required baseline code generation
const O1 = O0 | AotPass::CONSTANT_FOLD->value;  // Basic optimization
const O2 = O1 | AotPass::DEVIRTUALIZE->value | AotPass::FUNCTION_INLINE->value;
```

---

## 2. SSA (Static Single Assignment) + e-SSA

### Data Structures

SSA is built on top of the control flow graph (CFG):

**CFG (`zend_cfg.h:84-92`):**
```c
typedef struct _zend_cfg {
    int               blocks_count;       // Number of basic blocks
    int               edges_count;        // Number of edges
    zend_basic_block *blocks;             // Array of basic blocks
    int              *predecessors;       // Predecessor list
    uint32_t         *map;                // opnum → block mapping
    uint32_t          flags;
} zend_cfg;

typedef struct _zend_basic_block {
    int              *successors;         // Successor block indices
    uint32_t          flags;
    uint32_t          start;              // Starting opcode
    uint32_t          len;                // Number of opcodes
    int               successors_count;
    int               predecessors_count;
    int               idom;               // Immediate dominator
    int               loop_header;        // Nearest loop header
    int               level;              // Dominator tree depth
    int               children;           // Linked list of dominated child blocks
} zend_basic_block;
```

**SSA (`zend_ssa.h:135-143`):**
```c
typedef struct _zend_ssa {
    zend_cfg               cfg;           // Control flow graph
    int                    vars_count;    // Number of SSA variables
    int                    sccs;          // Number of strongly connected components
    zend_ssa_block        *blocks;       // φ functions per basic block
    zend_ssa_op           *ops;          // use-def information per instruction
    zend_ssa_var          *vars;         // def-use chain per SSA variable
    zend_ssa_var_info     *var_info;     // Type inference results (type bitmask + range)
} zend_ssa;
```

**SSA Op (`zend_ssa.h:82-92`):**
```c
typedef struct _zend_ssa_op {
    int op1_use;
    int op2_use;
    int result_use;
    int op1_def;        // SSA variable defined by this instruction
    int op2_def;
    int result_def;
    int op1_use_chain;  // use-def chain
    int op2_use_chain;
    int res_use_chain;
} zend_ssa_op;
```

### e-SSA: Extended SSA with Pi Nodes

This is one of the most elegant designs of the PHP optimizer. A Pi node is a special kind of φ function used to represent type/range constraints inferred from conditional branches.

**Pi constraints (`zend_ssa.h:42-59`):**
```c
typedef struct _zend_ssa_range_constraint {
    zend_ssa_range         range;       // Range constraint [min, max]
    int                    min_var;     // Symbolic lower-bound variable
    int                    max_var;     // Symbolic upper-bound variable
    zend_ssa_negative_lat  negative;    // Negation potential
} zend_ssa_range_constraint;

typedef struct _zend_ssa_type_constraint {
    uint32_t               type_mask;   // Type mask (narrowed type after AND operation)
    zend_class_entry      *ce;          // Class entry (for instanceof)
} zend_ssa_type_constraint;

typedef union _zend_ssa_pi_constraint {
    zend_ssa_range_constraint range;
    zend_ssa_type_constraint type;
} zend_ssa_pi_constraint;
```

**How it works:** For a condition like `if ($x > 0)`:
- In the truthy branch, insert `Pi($x, range[1, LONG_MAX])` — constrain `$x`'s SSA variable to the range > 0
- In the falsy branch, insert `Pi($x, range[LONG_MIN, 0])` — constrain `$x` to ≤ 0

This allows subsequent optimizations within a branch to use refined type/range information without altering the explicit assignment chain of the original variable.

### SSA Construction Flow

```
1. zend_build_cfg()     → Build the control flow graph (including dominator tree, loop detection)
2. zend_build_dfg()     → Build the data flow graph (compute use/def sets)
3. zend_build_ssa()     → Place φ functions → rename variables → build SSA form
4. zend_ssa_compute_use_def_chains() → Connect use-def chains
5. zend_ssa_find_sccs() → Find strongly connected components (for type inference)
6. zend_ssa_inference() → Type inference + range inference (populate var_info)
```

### AOT Takeaways

The AOT compiler does not need SSA form (because it generates C++ code, not direct register manipulation), but the following e-SSA concepts can be used directly:

1. **The Pi constraint concept:** Insert type-narrowing markers in conditional branches so that variables inside a branch have more precise types. This directly corresponds to the implementation basis of the TypeSpecifier / Type Narrowing (#7) mentioned earlier.

2. **Type & Range information associated with every expression:** Similar to the SSA `var_info` design, AOT can maintain a `{type_mask, range, ce}` triple for every variable/expression in the FunctionContext.

3. **Use-def chains for optimization decisions:** When determining whether a variable has exactly one `use`, SSA's use_chain provides O(1) lookup.

---

## 3. Type Inference (Type & Range Inference)

### The Type System

PHP uses a bitmask to represent type information (defined in `zend_type_info.h`), which is its most distinctive design:

```c
#define MAY_BE_UNDEF          (1<< 0)
#define MAY_BE_NULL           (1<< 1)
#define MAY_BE_FALSE          (1<< 2)
#define MAY_BE_TRUE           (1<< 3)
#define MAY_BE_LONG           (1<< 4)
#define MAY_BE_DOUBLE         (1<< 5)
#define MAY_BE_STRING         (1<< 6)
#define MAY_BE_ARRAY          (1<< 7)
#define MAY_BE_OBJECT         (1<< 8)
#define MAY_BE_RESOURCE       (1<< 9)
#define MAY_BE_REFERENCE      (1<<10)
#define MAY_BE_CALLABLE       (1<<11)
#define MAY_BE_ITERABLE       (1<<12)
#define MAY_BE_VOID           (1<<13)
#define MAY_BE_INDIRECT       (1<<14)

// Convenient combinations
#define MAY_BE_ANY            (MAY_BE_NULL|MAY_BE_FALSE|MAY_BE_TRUE|...)
#define MAY_BE_TRUTHY         (MAY_BE_TRUE|MAY_BE_LONG|...  /* not 0/''/[]/null */)
#define MAY_BE_FALSEY         (MAY_BE_UNDEF|MAY_BE_NULL|MAY_BE_FALSE|...)
```

**Core advantage:** Bit operations are extremely fast. Type operations (merge/intersection/difference) require only a single AND/OR/NOT instruction:

```c
// Merge the types of two variables
uint32_t result_type = info1 | info2;

// Check whether it might be a string
if (info & MAY_BE_STRING) { ... }

// Intersection
uint32_t common = info1 & info2;
```

### Range Inference

Each SSA variable carries a `zend_ssa_range { min, max, underflow, overflow }`:

```c
typedef struct _zend_ssa_range {
    zend_long  min;
    zend_long  max;
    bool  underflow;  // Whether there is an underflow risk
    bool  overflow;   // Whether there is an overflow risk
} zend_ssa_range;
```

The core algorithm (`zend_inference.c:1071`) is based on V. Campos's "Speed and Precision in Range Analysis, SBLP'12" paper:

1. **Warmup phase (16 passes):** Propagate ranges over SCCs (strongly connected components), using widening to accelerate convergence
2. **Narrowing phase:** Gradually narrow the ranges, eliminating the over-approximation caused by widening
3. **Zend Engine-specific arithmetic semantics:** `zend_add_will_overflow()`, `zend_sub_will_overflow()` and others precisely detect integer overflow

**Operator range inference example:**

```c
// ADD: result range
min = OP1_MIN() + OP2_MIN()
max = OP1_MAX() + OP2_MAX()
overflow = OP1_RANGE_OVERFLOW() || OP2_RANGE_OVERFLOW()
         || zend_add_will_overflow(OP1_MAX(), OP2_MAX())

// Result type: if overflow is true, add MAY_BE_DOUBLE to the type
// (PHP int overflow automatically converts to float)
```

### `update_type_info` per Opcode

`_zend_update_type_info()` is a huge switch that precisely computes the result type and range for each Zend opcode. For example, `ZEND_ASSIGN_DIM` (array assignment) updates not only the type of the assigned element, but also the type of the array as a whole, considering MAY_BE_PACKED_GUARD (packed array guard) and reference count inference.

### AOT Takeaways

1. **Bitmask type system:** It is the most suitable lightweight type representation for the AOT compiler. AOT currently uses string types (`TYPE_INT = 'int'`), which cannot efficiently represent compound types like "may be int or string". Bitmasks provide O(1) union/intersect/test operations.

2. **Range inference:** Can select the optimal integer type for C++ code generation (`int32_t` vs `int64_t` vs `BigInt`), avoiding unnecessary BigInt allocations.

3. **Overflow tracking:** Precisely determine when conversion from int64 to float/BigInt is needed, inserting conversion code only when overflow is actually possible.

4. **Per-opcode type update table:** The design of `_zend_update_type_info()` maps directly onto AOT's Rule system — each opcode corresponds to a Rule responsible for outputting the result type of that operation.

---

## 4. SCCP (Sparse Conditional Constant Propagation)

### Core Design

SCCP performs both **constant propagation** and **conditional constant folding** simultaneously, and can also eliminate unreachable code (no separate dead code elimination pass needed).

Implemented in `sccp.c`, based on `scdf.h` (the SCDF framework).

### Value Lattice

```
    TOP (undefined)
   / | \
  C1 C2 C3  (constant values)
   \ | /
    BOT (overdefined = not constant)
```

- TOP: the value of this variable is not yet known (optimistic assumption)
- BOT: this variable is known not to be a constant
- Constant value: the exact value is known

### Key Algorithm Points (from the comments at sccp.c:30-74)

**The `meet` operation (merging of φ functions):**
- BOT + any = BOT
- TOP + any = any
- C_i + C_i = C_i (two identical constants)
- C_i + C_j = BOT (two different constants)

**Instruction evaluation:**
- Any operand is BOT → result is BOT (exception: op1 of ASSIGN)
- Instructions that can never be evaluated → BOT
- Any operand is TOP → result is TOP
- All operands are known constants → attempt compile-time evaluation → return constant value on success, BOT on failure

**Branch feasibility determination:**
- Branch on BOT → all successors are feasible
- Branch on TOP → no successor is infeasible (wait for more information)
- Branch on a known constant → only the branch that satisfies the condition is feasible

### The SCDF Framework (`scdf.h`)

SCCP is built on top of the SCDF (Sparse Conditional Data Flow) framework, a generic sparse conditional data-flow analysis engine:

```c
typedef struct _scdf_ctx {
    zend_op_array *op_array;
    zend_ssa *ssa;
    zend_bitset instr_worklist;       // Instructions to process
    zend_bitset phi_var_worklist;    // Phi/SSA variables to process
    zend_bitset block_worklist;      // Blocks to process
    zend_bitset executable_blocks;   // Executable blocks
    zend_bitset feasible_edges;      // Feasible edges

    struct {
        void (*visit_instr)(...);       // Process an instruction
        void (*visit_phi)(...);         // Process a φ function
        void (*mark_feasible_successors)(...);  // Mark feasible successors
    } handlers;
} scdf_ctx;
```

**Usage pattern:** SCCP implements `visit_instr` (constant evaluation), `visit_phi` (the meet operation), and `mark_feasible_successors` (branch feasibility). Type inference also uses a similar worklist propagation algorithm.

**Generic worklist mechanism:**
```c
// When a variable's value changes, add all its uses to the worklist
static inline void scdf_add_to_worklist(scdf_ctx *scdf, int var_num) {
    const zend_ssa_var *var = &ssa->vars[var_num];
    int use;
    FOREACH_USE(var, use) {
        zend_bitset_incl(scdf->instr_worklist, use);  // Mark instructions using this variable
    }
    FOREACH_PHI_USE(var, phi) {
        zend_bitset_incl(scdf->phi_var_worklist, phi->ssa_var);
    }
}
```

### AOT Takeaways

1. **The SCDF framework is the most directly reusable:** about 300 lines of C code providing a generic worklist-driven conditional data-flow engine. AOT can port it as a PHP class, reusing it across multiple optimization passes such as SCCP, type inference, and escape analysis.

2. **The TOP/BOT lattice model:** The AOT compiler can use the same lattice structure when analyzing types:
   - TOP = unknown type (early in analysis)
   - BOT = contradictory type (an inconsistency was found)
   - Concrete value (a constant or an exact type)

3. **Conditional branch feasibility:** SCCP's branch feasibility determination can directly help AOT eliminate unreachable branches at compile time, generating simpler C++ code.

---

## 5. DCE (Dead Code Elimination)

### Algorithm (`dce.c`)

PHP's DCE uses an optimistic strategy:

```
1. Assume all instructions and φ functions are dead
2. Mark all instructions with obvious side effects as live (side-effect instructions)
3. Starting from live instructions, mark the defining instructions of their operands as live (reverse propagation along use-def chains)
4. Repeat until the worklist is empty
5. Delete all instructions still marked as dead
```

**The key `may_have_side_effects()` check (`dce.c:74-100`):**

Zend opcodes are divided into three kinds:
- Never have side effects (such as ADD, CONCAT, BOOL_NOT): can be eliminated by DCE
- May produce a notice but have no essential side effect (such as DIV_BY_ZERO triggering a warning): configurable whether to eliminate
- Always have side effects (such as ECHO, THROW, ASSIGN_OBJ): must be preserved

**Special capability:** It can eliminate "redundant modifications to non-escaping arrays/objects" and "useless array/object allocations". If an array is only built, modified, and used locally, the intermediate ASSIGN_DIM steps may be eliminated.

### AOT Takeaways

The AOT compiler's DCE can be more aggressive (because types are known at compile time):

1. **Side-effect classification matrix:** Build a side-effect table for AOT's expression/statement types, precisely marking which operations must be preserved
2. **Escape-aware DCE:** Combined with escape analysis, eliminate operations on non-escaping objects — this is one of AOT's biggest optimization opportunities
3. **Control-dependence based DCE:** PHP explicitly states that its current DCE does not consider control dependence (comments at `dce.c:35-39`); AOT can perform more precise control-dependence DCE

---

## 6. Escape Analysis

### Algorithm (`escape_analysis.c`)

Based on the classic escape analysis algorithm of Kotzmann & Mossenbock (PPPJ'05).

**Core steps:**

1. **Build equivalence escape sets (`zend_build_equi_escape_sets`):** Uses the Union-Find algorithm. If two SSA variables are related through a φ function or ASSIGN (same value), they belong to the same equivalence class.

2. **Escape state propagation:** Each equivalence class has four states:
   ```
   ESCAPE_STATE_UNKNOWN      → initial state (zero-initialized C memory)
   ESCAPE_STATE_NO_ESCAPE    → definitely does not escape (final goal)
   ESCAPE_STATE_FUNCTION_ESCAPE → escapes to the called function (passed as argument)
   ESCAPE_STATE_GLOBAL_ESCAPE → global escape (returned, assigned to a global variable, throws an exception, etc.)
   ```

3. **Monotonic state convergence:** States can only go from UNKNOWN → NO_ESCAPE/FUNCTION_ESCAPE/GLOBAL_ESCAPE, never reverse.

4. **Apply escape information:**
   - Non-escaping arrays can be allocated on the stack (no heap allocation needed)
   - Non-escaping objects can avoid reference counting operations
   - Non-escaping variables do not need separation (ZEND_SEPARATE)

**Predecessor/successor edges:** Supports symbolic type aliases (SYMTABLE_ALIAS) and HTTP response header aliases (HTTP_RESPONSE_HEADER_ALIAS).

### AOT Takeaways

Escape analysis may be the most valuable for the AOT compiler:

1. **Box allocation elimination:** AOT uses `Box<T>` to represent object references. Escape analysis can confirm which Boxes do not need heap allocation and can be created directly on the stack.

2. **Reference count elimination:** Non-escaping objects can skip `php::Object::Ref()` / `php::Object::Unref()` operations.

3. **Array stack allocation:** After escape analysis, local arrays can use a stack-based `zend_array`.

4. **The 4-state model is very simple and effective**, and AOT can map it directly:
   - ESCAPE_STATE_NO_ESCAPE → stack allocation
   - ESCAPE_STATE_FUNCTION_ESCAPE → decided by the caller
   - ESCAPE_STATE_GLOBAL_ESCAPE → heap allocation

---

## 7. Call Graph

### Design (`zend_call_graph.h`)

PHP's call graph tracks bidirectional relationships, both caller → callee and callee → caller:

```c
struct _zend_call_info {
    zend_op_array    *caller_op_array;     // Caller
    zend_op          *caller_init_opline;  // INIT_FCALL instruction
    zend_op          *caller_call_opline;  // DO_FCALL instruction
    zend_function    *callee_func;         // Called function
    zend_call_info   *next_caller;         // Linked list: the callee's next caller
    zend_call_info   *next_callee;         // Linked list: the caller's next callee
    bool              recursive;           // Recursive call
    bool              send_unpack;         // Uses SEND_UNPACK
    bool              named_args;          // Named arguments
    bool              is_prototype;        // May be a method overridden by a subclass
    bool              is_frameless;        // frameless function
    int               num_args;
    zend_send_arg_info arg_info[1];
};

struct _zend_func_info {
    zend_ssa           ssa;               // The function's own SSA
    zend_call_info    *caller_info;       // Who called this function
    zend_call_info    *callee_info;       // Whom this function called
    zend_call_info    **call_map;         // Quick index from opnum to call_info
    zend_ssa_var_info  return_info;       // Inferred return type
};
```

**Key features:**

1. **Bidirectional graph:** `caller_info` and `callee_info` are separate linked lists, supporting traversal upward (from callee to find callers) and downward (from caller to find callees)
2. **call_map:** An array indexing opnum → call_info, providing O(1) lookup of the call information for a given opcode position
3. **Return type propagation:** The callee's return_info can be propagated upward to the caller's return_info
4. **Argument type propagation:** The caller's actual argument types can be propagated downward to the callee's parameter types (for more precise function-body optimization)

### Advanced Cross-Function Optimization (`zend_optimize_script:1626-1728`)

```
1. build_call_graph        → build the bidirectional call graph
2. zend_optimize (per-func) → perform independent local optimization per function
3. analyze_call_graph      → infer function information (recursion flags, indirect variable access, func_get_args, etc.)
4. build_call_map          → build the opnum→call index for each function
5. dfa_analyze_op_array    → build SSA + type inference (per-func)
6. dfa_optimize_op_array   → perform SCCP + DCE + block pass based on SSA
```

### AOT Takeaways

The AOT compiler's first two steps (prepare + convert) naturally build a complete symbol dependency graph. On top of this, it can add:

1. **call_map index:** Quickly look up the callee's metadata from each call site (parameter types, return type, whether it is an inlining candidate)
2. **Bidirectional return type propagation:** AOT's return type inference is currently top-down; the call graph allows feeding the callee's known return type back to the caller
3. **Recursion flags:** `ZEND_FUNC_RECURSIVE_DIRECTLY` / `ZEND_FUNC_RECURSIVE_INDIRECTLY` are critical for inlining strategy decisions

---

## 8. JIT IR Framework

### Design

The IR (Intermediate Representation) used by PHP JIT is a generic SSA-derived low-level intermediate representation, located in `ext/opcache/jit/ir/`.

**Three stages of IR:**

| Stage | Files | Purpose |
|------|------|------|
| IR builder | `ir_builder.h`, `zend_jit_ir.c` | Build IR instructions from Zend bytecode |
| IR optimizer | `ir_cfg.c`, `ir_fold.h`, `ir_gcm.c` | CFG optimization, constant folding, global code motion (GCM) |
| IR emitter | `ir_emit.c`, `ir_emit_x86.h` | Emit x86/ARM64 machine code from IR |

**Example IR instructions: IR_ADD, IR_MUL, IR_LOAD, IR_STORE, IR_CALL, IR_GUARD, etc.**

**JIT optimization levels (`zend_jit.h:32-37`):**
```c
#define ZEND_JIT_LEVEL_NONE        0     // JIT not enabled
#define ZEND_JIT_LEVEL_MINIMAL     1     // Minimal JIT (subroutine threading)
#define ZEND_JIT_LEVEL_INLINE      2     // Selective inline threading
#define ZEND_JIT_LEVEL_OPT_FUNC    3     // Optimize a single function based on type inference
#define ZEND_JIT_LEVEL_OPT_FUNCS   4     // Optimize based on the call tree
#define ZEND_JIT_LEVEL_OPT_SCRIPT  5     // Interprocedural analysis
```

**JIT trigger modes (`zend_jit.h:39-44`):**
```c
#define ZEND_JIT_ON_SCRIPT_LOAD    0  // Compile immediately when all functions are loaded
#define ZEND_JIT_ON_FIRST_EXEC     1  // Compile on first execution
#define ZEND_JIT_ON_PROF_REQUEST   2  // Compile the hottest functions based on profile data
#define ZEND_JIT_ON_HOT_COUNTERS   3  // Compile after N calls/loop iterations
#define ZEND_JIT_ON_HOT_TRACE      5  // Use tracing JIT after N calls
```

### AOT Takeaways

1. **IR as an intermediate carrier for AST→C++:** AOT currently generates C++ code directly from the AST. Introducing an IR layer can:
   - Perform optimization at the IR level (fold, GCM, register allocation simulation)
   - Decouple the front end (PHP AST) from the back end (C++ codegen)

2. **The constant folding table of `ir_fold.h`:** IR contains an auto-generated folding rule table (`gen_ir_fold_hash`) defining hundreds of algebraic simplification rules. AOT can adopt a similar "rule table"-driven constant folding approach.

3. **JIT's profiling mechanism:** `hot_loop` / `hot_func` counters — AOT can embed profile data into the generated binary for PGO (Profile-Guided Optimization).

---

## 9. OPcache Persistence & File Cache

### Design

OPcache is not just a cache — it stores the **optimized** bytecode in the cache.

```
Original PHP source code
  → compiled to zend_op_array (original bytecode)
  → through all Zend Optimizer passes (SSA + type inference + SCCP + DCE + ...)
  → keep only the optimized zend_op_array (discard temporary IR such as SSA)
  → zend_persist() serializes to shared memory / file cache
```

**`zend_persist_calc` + `zend_persist`**: two-phase serialization —
1. `_calc` computes the required shared memory size
2. `_persist` performs the actual serialization (all pointers adjusted to absolute offsets)

**File cache (`zend_file_cache.c`):** Writes the persisted script to a file, allowing reuse across process restarts.

### AOT Takeaways

The current AOT compiler compiles from PHP source code every time. It can borrow the OPcache philosophy:

1. **Cache the optimized AST/type information:** After the `convert()` stage, serialize the typed AST and load it directly on the next compilation
2. **Incremental compilation:** Recompile only changed files and their dependencies
3. **Two-phase serialization (`_calc` + `_persist`):** Compute the size first, then allocate memory/write, avoiding realloc fragmentation

---

## 10. Other Notable Designs

### zend_bitset

PHP uses its own bitset implementation for efficient set operations. The optimizer frequently uses bitsets to represent worklists, live sets, and def/use sets.

### zend_worklist.h

Generic worklist iteration macros; SCCP and type inference use the same worklist mechanism. Designed as macros for inlining performance.

### zend_arena

An arena memory allocator used for fast allocation and bulk release of all optimizer data structures. One arena is bound to one `zend_optimizer_ctx`, and all passes share the same arena.

### Inter-Pass Data Management

Temporary IR such as SSA is destroyed immediately after a pass completes (via arena free), keeping only the optimized results in the final `zend_op_array`. This guarantees memory efficiency.

---

## Recommended Adoption Order (for the AOT compiler)

```
Phase 1: Pass Pipeline
  └── Define the AotPass enum + Pipeline runner, pluggable pass architecture

Phase 2: Bitmask type system
  └── Borrow Zend's type mask design, replacing the current string type constants
  └── Map directly to C++ uint32_t constants

Phase 3: Type inference rules
  └── Each AST node/opcode corresponds to an update_type_info
  └── Implement in combination with the Rule system (#4 design)

Phase 4: SCCP + DCE
  └── Constant propagation + dead code elimination based on the SCDF framework
  └── Can eliminate redundant expressions before C++ code generation

Phase 5: Escape analysis
  └── Union-Find equivalence escape sets + 4-state propagation
  └── Used for Box allocation elimination and reference count elimination

Phase 6: Call graph cross-function optimization
  └── Add call_map + type propagation on top of the existing symbol dependency graph
```

Each layer can be implemented independently and immediately bring benefits to existing code generation.

# Backend-Neutral IR for C++, WASM and JavaScript

TypePHP currently lowers PHP AST directly to C++ code. This works for the
current C++/Zend backend, but it couples PHP semantic analysis, temporary value
management, reference behavior, runtime calls and C++ code formatting in one
stage.

If TypePHP needs to support targets such as WASM or JavaScript, the compiler
should introduce a backend-neutral semantic IR before backend-specific code
generation.

## Recommended Pipeline

```text
PHP Parser AST
    -> Bound AST
    -> PHP Semantic IR
    -> Backend Lowering
        -> C++/Zend Runtime IR -> C++
        -> JavaScript Runtime IR -> JS
        -> WASM Runtime IR -> wasm/wat/import calls
```

The important rule is that the semantic IR must describe PHP behavior, not C++
implementation details.

It should not contain:

- `php::Var`
- `php::Ref`
- `php::Array`
- `zend_*`
- `zval*`
- C++ expression fragments

Those belong to the C++ backend only.

## Bound AST

The Bound AST should preserve PHP syntax shape while attaching resolved semantic
information.

It should contain:

- Resolved names for functions, classes, methods, properties and constants.
- Scope information for locals, globals, statics and closure captures.
- Declared and inferred types.
- By-reference information for parameters, returns and assignments.
- Call target classification: AOT function, native call, internal function,
  dynamic call or unsupported target.
- L-value and R-value classification for reads, writes, `isset`, `unset` and
  reference access.
- Target capability diagnostics, such as whether `eval`, `include`, resources or
  reflection are allowed for the selected backend.

Most unsupported syntax should be rejected in this phase or during IR
validation, not during backend code generation.

## PHP Semantic IR

The semantic IR should be close to PHP runtime semantics. It should not be too
low-level at first, because PHP references, arrays, dynamic calls and exception
flow are difficult to recover after early lowering.

Example:

```text
%value = load_local $value
%class = load_local $name
%ok = instanceof %value, %class
return %ok
```

For a more complex expression:

```text
%services = load_prop this, "services"
%closure = make_closure closure#1 captures [$name]
%filtered = call_func "array_filter", [%services, %closure, ARRAY_FILTER_USE_BOTH]
%values = call_func "array_values", [%filtered]
%result = coalesce_dim %values, 0, null
return_checked %result, ?object
```

Suggested instruction families:

- `LoadLocal`, `StoreLocal`
- `LoadGlobal`, `StoreGlobal`
- `LoadProp`, `StoreProp`, `PropRef`
- `LoadDim`, `StoreDim`, `DimRef`
- `CallFunc`, `CallMethod`, `CallStatic`
- `NativeCall`
- `MakeClosure`
- `InstanceOf`
- `Isset`, `Empty`, `Coalesce`
- `Cast`, `TypeCheck`, `TypeAssert`
- `ToReferenceExact`
- `Return`, `ReturnRef`, `ReturnChecked`
- `Throw`, `TryCatchFinally`
- `Branch`, `Jump`, `Label`
- `ForeachInit`, `ForeachNext`
- Explicit unsupported nodes for `eval`, `include`, `resource` operations and
  other target-dependent features.

## Runtime ABI

Multiple backends should implement the same PHP semantic runtime ABI.

Example ABI operations:

```text
rt_call_function(name, args)
rt_call_method(receiver, method, args)
rt_call_static(class, method, args)
rt_get_property(object, name)
rt_set_property(object, name, value)
rt_get_property_ref(object, name)
rt_get_dim(value, key)
rt_set_dim(value, key, value)
rt_get_dim_ref(value, key)
rt_to_bool(value)
rt_instanceof(value, class_name)
rt_make_reference(value)
rt_deref(value)
rt_throw(value)
```

The C++ backend can map these operations to the existing `php::Var`, `php::Ref`
and Zend-based runtime. JavaScript and WASM can map them to their own runtime
representations.

## Value Model

The IR should use abstract value identities instead of physical backend types.

Useful concepts:

- `ValueId`
- `RefId`
- `ArrayId`
- `ObjectId`
- `ClassId`
- `FunctionId`
- `CallableId`
- `ExceptionId`

The backend decides how these are represented. For example:

- C++/Zend can use `php::Var`, `php::Ref`, `zval*` and `zend_class_entry*`.
- JavaScript can use tagged JS objects and boxed references.
- WASM can use handles and runtime imports.

## Semantics That Must Stay Explicit

The following PHP semantics must remain explicit in the Bound AST or semantic
IR. They should not be lowered too early:

- `isset` is not the same as a normal read.
- `empty` is not just `!toBool(value)`.
- `??` uses `isset` semantics, not only null comparison.
- `foreach` by value and by reference are different.
- Return by reference must be represented as `ReturnRef`.
- Call-by-reference arguments must preserve reference slots.
- Array element and object property references must use explicit `DimRef` and
  `PropRef` operations.
- PHP arrays are ordered maps, not native JS arrays or C++ vectors.
- Object property visibility and typed property checks are runtime semantics.
- Dynamic calls must preserve symbol lookup behavior.
- `eval`, `include`, resources, reflection and extension-dependent behavior must
  be explicit so each backend can accept, reject or emulate them.

## Target Capability Matrix

Each backend should declare a feature set. The compiler should validate IR
against the selected target before code generation.

Example:

```text
Feature                C++/Zend     JavaScript       WASM
function call          yes          yes              yes
class/object           yes          partial          partial
array COW              Zend         runtime          runtime
reference              Zend         boxed refs       boxed refs
internal PHP funcs     yes          shim/import      import
resource               yes          no/partial       no
eval/include           partial      no/partial       no
reflection             yes          partial          no/partial
extension classes      yes          shim             no
```

This should be represented in code as target capability metadata, for example:

```text
target: cpp-zend | js | wasm
```

Unsupported behavior should produce deterministic diagnostics in the validation
phase.

## JavaScript Backend Notes

JavaScript is a practical first non-C++ backend because it is dynamic and can
host a PHP-like runtime in user space.

Main runtime requirements:

- PHP array as ordered map.
- PHP references as boxes.
- PHP object/class model with visibility checks.
- PHP weak typing and cast helpers.
- PHP exception model.
- Closure capture semantics.
- Dynamic function, method and static method calls.
- Binary-safe string representation, because JS strings are not byte strings.
- Integer handling policy, because JS numbers are doubles.

The initial JS backend should target a strict PHP subset before trying to support
resources, reflection, extensions, `eval` or real stream behavior.

## WASM Backend Notes

WASM is less suitable for directly expressing dynamic PHP semantics. It should be
planned as a runtime-backed target.

Possible routes:

1. PHP -> C++ -> WASM

   This is the shortest path and reuses the current C++ backend, but Zend
   embedding is heavy and many OS/resource features are limited in WASM
   environments.

2. PHP Semantic IR -> WASM + runtime imports

   This is cleaner but more expensive. The generated WASM should call imported
   runtime functions for dynamic PHP operations such as arrays, objects,
   references and dynamic calls.

The second route is preferable long term. The first route may be useful for an
early proof of concept.

## Migration Strategy

Do not rewrite the whole compiler at once.

Recommended steps:

1. Introduce target capability metadata.
2. Add Bound AST metadata without changing existing C++ output.
3. Add semantic IR data structures and `--dump-ir`.
4. Lower a small expression subset to IR first.
5. Implement `Semantic IR -> C++` for that subset.
6. Keep mixed mode: unsupported IR nodes can temporarily fall back to the old
   direct AST-to-C++ path.
7. Gradually migrate high-risk constructs:
   - `??`
   - ternary
   - `instanceof`
   - nullsafe access
   - function, method and static calls
   - `return`
   - `foreach`
   - `try/catch/finally`
   - reference assignment and return-by-reference

The current `beforeStmtLines` and `afterStmtLines` mechanism should eventually
be replaced by explicit IR instruction ordering. This avoids hidden side effects
inside expression parsing and prevents formatting or comment text from changing
generated program behavior.

## Testing Strategy

Add IR snapshot tests in addition to PHPT output tests.

Example:

```text
--IR--
function main(): void
  %0 = load_local $value
  %1 = load_local $name
  %2 = instanceof %0, %1
  return %2
```

This separates frontend semantic correctness from backend code generation
correctness.

Recommended test categories:

- PHP AST -> Bound AST diagnostics.
- Bound AST -> Semantic IR snapshots.
- Semantic IR -> C++ output behavior.
- Target capability rejection tests.
- Backend-specific runtime conformance tests.

## Key Design Constraint

The central IR must be a PHP semantic IR, not a C++ builder IR.

If the IR is designed around `php::Var`, `php::Ref` and Zend symbols, C++ will
remain the compiler's semantic core and future JavaScript/WASM targets will be
hard to implement.

If the IR describes PHP behavior and leaves physical representation to backend
lowering, C++, JavaScript and WASM can evolve as separate backends.

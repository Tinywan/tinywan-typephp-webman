# TypePHP Compatibility Engineering Policy

TypePHP aims to make ordinary application code correct, type-safe, stable and
fast. It does not attempt to reproduce every observable ZendVM edge case. When
exact PHP compatibility conflicts with PHPX encapsulation, maintainability or
static compilation, the architecture takes precedence and the difference must
be documented.

## Priority

| Priority | Required work | Examples |
|---|---|---|
| P0 | Crashes, use-after-free, memory corruption, lifecycle bugs, invalid generated C++, and silent data corruption | Cache-domain confusion, invalid object lifetime, backend-specific crashes |
| P1 | Clearly wrong results in common language constructs, bypassed type checks, or repeated evaluation of ordinary side-effecting expressions | `ArrayAccess` assignment writing to an `offsetGet()` temporary; evaluating a receiver twice |
| P2 | Uncommon combinations whose exact behavior depends on dynamic aliases, callbacks, warnings, or deprecated conversions | A callback rebinding an array to an object during an operation; exact warning order |
| Non-goal | Mirroring ZendVM internals solely for obscure compatibility | Copying large parts of `zend_execute.c` or `zend_vm_def.h` into PHPX |

P0 and P1 issues must be fixed. P2 behavior is fixed only when the solution is
small, general, and consistent with the existing architecture. Otherwise it is
classified as Partial, an Intentional Rule, or a Hard Limit.

## Architectural Boundaries

- Do not copy substantial ZendVM executor logic into PHPX.
- Do not add a public PHPX API for one compiler edge case.
- Do not expose raw `zval *`, HashTable slots, or other Zend storage details to
  generated project code.
- A new PHPX API must describe a general operation, fit the existing naming and
  ownership model, and remain useful outside one PHPT reproducer.
- Emit a native fast path only when the compiler can prove it is safe.
- Prefer a clear TypePHP-specific rule or diagnostic over a large dynamic
  compatibility layer.
- A statically known invalid operation should fail during compilation. A
  runtime-only invalid operation may use a stable PHPX error; its exact Zend
  error level, wording and timing are not part of the compatibility promise.

## Review Decision

Before accepting a compatibility change, answer these questions:

1. Does the issue crash, corrupt memory/data, generate invalid C++, bypass a
   type rule, or break a common construct?
2. Can the compiler prove the required dispatch or evaluation order?
3. Can the fix use existing compiler and PHPX abstractions?
4. If a new API is proposed, is it independently useful and correctly placed?
5. Will the implementation remain valid across supported PHP versions without
   tracking private ZendVM code?

If the first answer is no and the remaining answers expose disproportionate
complexity, document the boundary instead of implementing it.

## Dimension-Assignment Example

For `$container[$key] ??= $value`, the important behavior is:

- a real array writes its bucket;
- an `ArrayAccess` object uses `offsetExists()`, `offsetGet()` and
  `offsetSet()` in the correct branch;
- ordinary receiver, key and RHS expressions are not evaluated more than
  required;
- assigning to the temporary returned by `offsetGet()` is never treated as an
  object-dimension write.

These are common correctness requirements. By contrast, exact Zend behavior
when callbacks rebind the container or key between phases, deprecated
false-to-array conversion, every string-offset result detail, and exact warning
ordering are edge compatibility. They must not cause PHPX to duplicate the
ZendVM dimension executor.

TypePHP intentionally treats a null array key as append. This established
language difference must be preserved unless a separate design decision changes
it.

## Documentation and Tests

- Every intentional or partial incompatibility must be recorded in
  `INCOMPATIBLE_PHP_FEATURES.md` and classified in
  `PHP_INCOMPATIBILITY_CLASSIFICATION.md` when appropriate.
- Regression tests should protect the supported boundary, not require behavior
  that the project deliberately does not promise.
- Do not make exact diagnostic text part of a test unless the diagnostic is a
  stable TypePHP contract.
- A test-only gap is normally completed after a sound implementation; it is not
  a reason to accept an unsound architecture.

# StdFunc / StdArgInfo Type Annotation Design

Status: the `StdFunc` / `StdArgInfo` design is deferred and is not part of the current implementation plan; this document is retained only as a historical design record. The referenced StdArray annotation is implemented. See [unified type annotations](TYPE_ANNOTATIONS.md) for terminology and container contracts.

## Goals and non-goals

`StdFunc` specifies a callable's complete invocation contract: argument counts, value types, reference modes, and result type. `StdArgInfo` describes one parameter and its modifiers.

This is not an arrow-function execution optimization. Ordinary statically verifiable PHP callbacks may continue to execute through Zend Bridge. Typed arrays, Native values, or other protected boundaries require separate controlled TypePHP paths. Signature checking does not require converting every closure into a C++ lambda.

## Declaration syntax

```php
function run(
    #[StdFunc(
        Type::Void,
        [
            Type::Int,
            new StdArgInfo(Type::Float, default: 1.0),
            new StdArgInfo(Type::Str, Type::Optional),
            new StdArgInfo(Type::Int, Type::Variadic),
        ],
    )]
    callable $callback
): void {
    $callback(10);
    $callback(10, 2.5, 'tag', 1, 2);
}
```

Proposed interface: `StdArgInfo(type, flags = 0, default = <unset>)`. A bare `Type::Int` is shorthand for `new StdArgInfo(Type::Int)`: required, non-nullable, by value.

All parameter Nullable, Ref, Optional, Variadic, and default information belongs in `StdArgInfo`. Separate `optional:` lists, `variadic:` signature arguments, and nested parameter `RefType` / `NullableType` wrappers are superseded.

PHP attribute arguments do not allow ordinary function calls such as `StdArgInfo(...)` or `std::list(...)`. PHP 8.1 permits `new` expressions in attribute arguments. TypePHP recognizes whitelisted descriptor ASTs without executing their constructors or arbitrary user code. [PHP attribute syntax](https://www.php.net/manual/en/language.attributes.syntax.php), [PHP 8.1 new initializers](https://www.php.net/manual/en/migration81.new-features.php).

Public `Type::Void`, `Type::Nullable`, `Type::Ref`, `Type::Optional`, `Type::Variadic`, and `StdArgInfo` still need implementation. An existing compiler-internal VOID does not implement the public Void descriptor.

## Flags and validation

Flags are integer bitmasks, separate from value type descriptors:

| Flag | Meaning | Omission |
|---|---|---|
| None | Required, by value, non-nullable | Forbidden |
| `Nullable` | Value may be null | Does not change requiredness |
| `Ref` | Writable reference; both input and output are constrained | Does not change requiredness |
| `Optional` | Contract supplies an omitted value | Allowed |
| `Variadic` | Zero or more extra values of the element type | Allowed, meaning zero elements |

Supplying `default` implies Optional. Explicit `default: null` also counts as a supplied default, but requires Nullable or a type that already admits null; it never implicitly adds Nullable.

Required parameters precede Optional parameters. There is at most one Variadic parameter, at the end. Modifiers must make sense for the value type; Void is not a parameter type. Unknown flags, conflicting primary types, and meaningless combinations are compilation errors.

## Optional and defaults

An Optional parameter with no explicit default uses its type's empty value:

| Type | Empty default |
|---|---|
| Int / Float | `0` / `0.0` |
| Bool | `false` |
| Str / String | `''` |
| Array | A fresh empty array |
| StdList / StdDict | A fresh empty PHP array retaining the contract |
| Nullable type | `null`, taking precedence over the non-null zero value |
| Non-nullable class object | No safe empty default; compilation error |
| Other types | Require defined safe initialization or a compilation error |

Do not implicitly instantiate class objects, put null into non-nullable objects, or treat an ordinary empty array as a Box. Automatic creation/destruction of non-nullable Box defaults requires separate evaluation and is not enabled merely by Optional.

Explicit defaults must match the value type. The first phase should accept statically verifiable scalar constants, null, and safe array initializers. Object, Box, and other complex defaults need further design; arbitrary factory functions are not executed to obtain defaults.

Defaults belong to the `StdFunc` contract. Equal parameter types with different defaults do not make two contracts freely interchangeable: omitted calls behave differently. They may share a low-level callable ABI type, but retain their invocation contracts or require an explicit adapter. Propagation must not erase defaults.

An absent default differs from explicit null. AST parsing records argument presence; cache/stub metadata includes a separate `hasDefault`. A future runtime descriptor interface also needs an unset sentinel instead of testing only whether `default` is null.

## Caller-side default completion

This is an agreed semantic choice, not merely a claim that the callback implementation has default parameters:

```php
// Contract: Optional Int without an explicit default, therefore default 0.
// Implementation: function handler(int $value = 10): void { ... }
// Calling through this StdFunc with the argument omitted passes 0, not 10.
```

Validate supplied arguments, evaluate them once in PHP order, fill omitted fixed positions using contract defaults, then pass variadic arguments. Default arrays and reference storage are fresh per call; mutable default objects must not be shared accidentally.

The implementation's defaults do not apply to completed positions. `func_num_args()`, `func_get_args()`, and callback backtrace arguments observe the completed fixed arguments. This observable difference is intentional.

Because the caller completes fixed positions, the target's corresponding parameters may be required if they accept the completed values. For example, `(Optional Int = 0) -> Void` can bind `function handler(int $value): void`. The earlier rule that an Optional contract requires an Optional implementation is superseded.

## Nullable values and nullable callback bindings

```php
new StdArgInfo(Type::Int, Type::Nullable | Type::Ref);
new StdArgInfo(Type::Int, Type::Nullable | Type::Optional);
```

The first is a required nullable-int reference. The second can be omitted and defaults to null. Nullable and Optional are independent.

A nullable callback binding is separate from nullable parameter values. Proposed `StdFunc(..., nullable: true)` can express it, with an omitted PHP type or compatible `?callable`. Non-null contracts permit omitted types or `callable`. Explicit mixed/any is rejected. Calling a nullable callback requires a non-null proof or a runtime non-null check.

The concrete syntax for nullable result types remains undecided. `StdArgInfo` describes parameters only; it must not be reused merely to solve result syntax. Reference returns are deferred.

## Reference parameters

Ref belongs to the signature. Contract and implementation must agree on by-value/by-reference modes. Referenced value types are invariant: `&Int` is not interchangeable with `&Nullable(Int)`, and input contravariance must not widen reference writes.

Calls use ordinary PHP `$callback($value)`, without an argument-side `&`. Initially support statically known writable locals and existing safe reference ABIs. Constants, expressions, and ordinary returned values are invalid reference arguments. Properties, array elements, returned references, and reference escape paths need separate acceptance.

Do not simulate references to existing variables with copy-in/copy-out temporaries. Preserve alias identity, evaluation order, exceptions, and mutations visible during nested calls.

`Optional | Ref` belongs to the target model. Omission creates independent, correctly typed writable storage for the duration of that call, and the callback must not retain its reference. Nullable Optional Ref storage starts with null. Storage lifetime and non-escape proofs are not implemented; the first phase may reject this combination explicitly, never silently lower it to by-value passing.

An unknown dynamic callback cannot gain trusted reference mutation privileges from an annotation alone.

## Variadic parameters

```php
new StdArgInfo(Type::Str, Type::Variadic);
new StdArgInfo(Type::Int, Type::Nullable | Type::Variadic);
```

The type describes each extra argument, not the collected parameter array. Zero arguments mean no elements, not one default element; Variadic therefore rejects defaults. An explicit Optional flag with Variadic may normalize to Variadic without additional behavior.

Fixed positions, including Optional positions, consume arguments first. Remaining arguments are variadic. Positional calls cannot skip an Optional parameter to supply the variadic tail. An unbounded contract requires compatible variadic capacity in the target.

Initially defer `Variadic | Ref`, named calls, and dynamic unpacking without a static proof of counts and per-item types. PHP user functions tolerating extra arguments is not an exception to contract checking.

## Container parameter types

```php
#[StdFunc(
    Type::Void,
    [
        new StdArgInfo(new StdList(MyUser::class), Type::Ref),
        new StdDict(Type::Str, Type::Int),
        new StdVector(Type::Int),
        new StdArray(Type::Int, [100, 200, 8]),
    ],
)]
```

Nested `new StdList`, `new StdDict`, `new StdVector`, `new StdMap`, `new StdOrderedMap`, and `new StdArray` are type descriptors sharing canonical contracts with standalone annotations. StdArray uses one length or an outer-to-inner dimension array, not recursive descriptor objects. `new StdArgInfo(new StdArray(Type::Int, 100))` describes a one-dimensional parameter; full shapes must match.

StdArray is a special container annotation form: its second argument describes fixed shape rather than a type. Structural nesting is not parsed using map key/value or vector element-only rules. Embedding it in StdArgInfo does not change this distinction; see the dedicated StdArray section in the unified design.

Container kind, key type, and value type must match. Ordinary array or var/any values do not automatically acquire a trusted contract. Lists and integer-key dicts are distinct. By-value PHP arrays retain COW; by-reference arrays retain aliases. Boxes retain their separate shared lifetime model.

Typed PHP arrays require a statically verifiable TypePHP callback that preserves the same contract. Existing direct calls use `php::Array` / `php::Array &`; an ordinary Zend callable path may lose the contract or lack that ABI. Provide a controlled path or reject the call; `isCallable()` alone is insufficient.

StdList/StdDict references cannot escape into dynamic PHP. StdVector and other Box references are separate capabilities: existing Box annotations reject reference parameters, and nested descriptors do not automatically enable them. Existing Native-object and container escape restrictions remain intact.

## Binding, calls, and propagation

- Accept signature-explicit, target-verifiable closures, arrow functions, first-class TypePHP function/method callables, and existing values with the same contract.
- Initially reject unknown dynamic strings, method arrays, ordinary var callables, and implementations without a provable signature. Zend Bridge execution does not imply unknown signature provenance.
- Verify all lowered calls: fixed counts/types, reference modes, variadic capacity, and the result contract.
- By-value matching follows input contravariance/output covariance. The first phase may restrict this to provable basic-type and Nullable compatibility, deferring complex unions and class variance. References are invariant; typed containers require matching kinds and type arguments.
- Known type errors, missing required arguments, and excess arguments are compilation errors. Ordinary var/any value arguments can receive strict runtime checks without coercion. This does not recover trusted typed arrays or callback signatures.
- Verified results propagate to call expressions and receiving locals. Void cannot be used as a value. Unknown dynamic results cannot be trusted and unboxed solely because an annotation says so.
- Ordinary local assignment propagates the complete contract, including defaults. Rebinding, capture, caching, and forwarding must not lose defaults or boundary rules. An ordinary unannotated boundary does not automatically retain trusted signature metadata.
- Reference transfer of callback bindings, dynamic replacement, properties, and escapes require separate protection. The first phase covers parameter declarations and controlled local propagation, not implicit property support.

## Implementation phases and acceptance

1. Register annotations and whitelisted descriptor ASTs; add signature/parameter metadata and unset-default handling, serialized into declaration caches and library stubs.
2. Verify bindings and ordinary required arguments/results; propagate result types at variable calls while retaining ordinary Zend closure execution.
3. Add Nullable, caller-side Optional completion, Variadic, and evaluation ordering. Contract changes invalidate callers because defaults are lowered at call sites.
4. Integrate existing safe scalar reference ABIs and prove alias/exception behavior. Independently validate Optional Ref lifetime, escape prevention, and controlled typed-array callbacks; never reuse unsafe copy-in/copy-out bridges.
5. Keep properties, named calls, unknown dynamic callbacks, reference returns, and dynamic unpacking closed until their boundaries are proven.

Tests cover flag combinations, absent versus explicit-null defaults, nullable defaults, invalid default types, different contract/implementation defaults, required target parameters, observable argument counts, once-only evaluation, variadic positions/types, reference aliases/exceptions/escapes, typed-array COW/references, rejection of dynamic mutation, results, caches, and stub round trips.

## Language references and deliberate differences

TypeScript erases concrete default values from function types while retaining Optional. Python callable specifications allow default placeholders and check statically known default types. This design borrows separation of parameter kinds from value types, but deliberately differs: `StdArgInfo` defaults belong to the invocation contract and are supplied by callers, so contract metadata retains them.

Passing null in PHP does not trigger a function default. Likewise, this design fills omitted arguments only, never treating null as an omission. References: [TypeScript functions](https://www.typescriptlang.org/docs/handbook/functions), [Python callable specification](https://typing.python.org/en/latest/spec/callables.html), [PHP arguments and references](https://www.php.net/manual/en/functions.arguments.php).

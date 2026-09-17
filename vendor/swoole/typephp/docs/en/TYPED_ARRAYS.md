# Typed PHP arrays and type annotations

This document records current implementation boundaries. See [Unified Type Annotation Design](TYPE_ANNOTATIONS.md) for complete container contracts and target design, and [StdFunc / StdArgInfo](STD_FUNC_DESIGN.md) for proposed callback parameter rules (not implemented).

`std::list(T)` and `std::dict(K, V)` retain PHP array storage and copy-on-write.
Type annotations declare their key and value contracts on parameters and properties:

Local values may also infer their types from non-empty initializers, for example `std::list([9, 3, 5])` and `std::dict(['v1' => 999, 'v2' => 1000])`. Entries may contain arbitrary expressions, but every static value type must match exactly, and explicit dict keys must all have the same Int or Str type. Any var/any/mixed key or value is a compile error because it cannot provide a trustworthy inferred contract.

```php
class State
{
    #[StdList(Type::Int)] public array $values = [];
    #[StdDict(Type::Str, Type::Int)] public $counts = [];
}

function append(#[StdList(Type::Int)] array &$values): void
{
    $values[] = 42;
}
```

PHP types may be omitted or declared as compatible storage types: `array` for
`StdList` / `StdDict`, and `box` for `StdVector` / `StdMap` / `StdOrderedMap`.
Explicit `mixed`, `any`, nullable types, unions, and incompatible types are rejected.

`$source->toStdList(Type::Int)` and `$source->toStdDict(Type::Str, Type::Int)` convert values into new local typed arrays. A typed array with the same contract uses ordinary PHP array assignment. An ordinary array has every key and value checked strictly at runtime; other values first pass through `toArray()` and then receive the same checks. `ClassName::class` is accepted as a value type and checks that every value is an instance of that class. Conversion leaves the source array unchanged.

**Performance:** Except for direct assignment from a typed array with the same contract, conversion traverses the entire array and checks every key and value, taking O(n) time. Non-array sources also run `toArray()` first. Repeated conversion of large arrays, especially inside loops, can be costly. Use these methods carefully; convert once at the typed boundary and reuse the result when possible.

Validation uses the array's actual runtime key types. PHP normalizes numeric string keys such as `'123'` to integer keys, so an ordinary array with such a key fails the strict `toStdDict(Type::Str, ...)` check. A `StdDict` with the same contract is assigned directly and is not checked again.

List keys are integers, including negative and sparse keys; no bounds checks
are inserted. Only lists allow `[]` append. Dicts require an explicit int or
string key. Dynamic `any` / `var` keys get internal strict type checks, not coercion.
Values require matching static types, except for `Type::Any` values.

Local typed arrays cannot escape to dynamic PHP through `std::ref()`, element
references, or mutable/by-reference array functions. Matching annotated native
parameters may accept references. String-key dict iteration converts numeric
PHP keys back to strings without changing PHPX or HashTable storage.

Property annotations currently check first-level direct element assignments.
They do not provide complete protection against whole-property replacement,
dynamic PHP object mutation, or object/property reference escapes. Property reads
are not automatically promoted to closed-contract local typed arrays.

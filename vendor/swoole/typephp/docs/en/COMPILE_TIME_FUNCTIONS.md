# AOT compile-time functions and keyword methods

This document records the compile-time functions, keyword methods, and related construction entry points that are specific to the AOT compiler. They are not part of standard PHP syntax, and an ordinary PHP runtime can only rely on the compatibility stubs provided by `src/polyfills.php`.

## Core compile-time functions

There are currently 5 core global compile-time functions.

| Name | Parameters | Purpose | Current primary handling location |
| --- | --- | --- | --- |
| `any($value)` | 1 | Degrades the expression to `mixed/any`, preventing further processing as a static native/object type. | General function-call expression entry. |
| `refval($target)` | 1 | Explicitly passes a variable, array element, or object property by reference to a dynamic call or a call whose reference parameter cannot be statically identified. | Argument parsing, dynamic calls, SSA/optimizer reference escape analysis. |
| `objval($value, ClassName::class or 'ClassName')` | 2 | Tells the compiler that `$value` is an object of the specified class, and generates the `php::toObject(..., target_ce)` runtime fallback check. | Function-call resolution, object type inference. |
| `expected($condition)` | 1 | Marks the condition as usually true, generating the Zend `EXPECTED(...)` branch prediction macro. | General function-call expression entry. |
| `unexpected($condition)` | 1 | Marks the condition as usually false, generating the Zend `UNEXPECTED(...)` branch prediction macro. | General function-call expression entry. |

Constraints:

- `refval()` only accepts variables, array elements, or object properties.
- The second parameter of `objval()` must be a compile-time-resolvable class-name string or `ClassName::class`.
- `any()` can be used in any expression position; it directly expands its single argument at compile time without generating a runtime function call.
- `expected()` / `unexpected()` accept exactly one non-expanded argument and return bool; they are usually used in `if`, `elseif`, and loop conditions, and do not change the argument's evaluation count or true/false semantics.

## Keyword methods

There are currently 12 built-in keyword methods.

| Name | Equivalent behavior | Description |
| --- | --- | --- |
| `toAny()` | `any($receiver)` | Returns the receiver itself, but with the type degraded to `mixed/any`. |
| `toRef()` | `refval($receiver)` | Returns a reference to the receiver; parameter restrictions are the same as `refval()`. |
| `toObject()` | `php::toObject($receiver)` | May take a target-class parameter, performing object conversion/checking. |
| `toInt()` | `php::toInt($receiver)` | Converts to a native int expression. |
| `toFloat()` | `php::toFloat($receiver)` | Converts to a native float expression. |
| `toString()` | `php::toString($receiver)` | Converts to a string expression. |
| `toBool()` | `php::toBool($receiver)` | Converts to a bool expression. |
| `toArray()` | `php::toArray($receiver)` | Converts to an array expression. |
| `toStream()` | `php::toStream($receiver)` | Converts to a stream expression. |
| `toBigInt()` | `php::BigInt::newInstance($receiver)` | Constructs a BigInt. |
| `toBigFloat()` | `php::BigFloat::newInstance($receiver)` | Constructs a BigFloat. |
| `toDecimal()` | `php::Decimal::newInstance($receiver)` | Constructs a Decimal. |

Constraints:

- `toAny()` and `toRef()` accept no parameters.
- `toRef()` only applies to receivers that can take references.
- Keyword methods take precedence over ordinary methods and universal method dispatch.

## `std::` compile-time construction entry points

There are currently 10 `std::` compile-time construction entry points.

| Name | Purpose | Main limitation |
| --- | --- | --- |
| `std::int($value)` | Explicitly creates a native int expression. | Requires 1 value parameter. |
| `std::float($value)` | Explicitly creates a native float expression. | Requires 1 value parameter. |
| `std::bool($value)` | Explicitly creates a native bool expression. | Requires 1 value parameter. |
| `std::bigInt($value)` | Constructs a BigInt. | Implicit construction from a float variable is not allowed. |
| `std::decimal($value)` | Constructs a Decimal. | A float variable must be converted via string or integer; float literals are handled per the original literal. |
| `std::bigFloat($value)` | Constructs a BigFloat. | Requires 1 value parameter. |
| `std::array($type, $size[, ...$sizes])` | Constructs a fixed-size std array. | Can only be used in the top-level scope of the variable's first assignment. |
| `std::vector($type[, $size])` | Constructs a std vector. | Can only be used in the top-level scope of the variable's first assignment. |
| `std::map($keyType, $valueType)` | Constructs a std map. | Can only be used in the top-level scope of the variable's first assignment. |
| `std::ordered_map($keyType, $valueType)` | Constructs a std ordered map. | Can only be used in the top-level scope of the variable's first assignment. |

## Std container conversion keyword methods

There are currently 4 Std container conversion keyword methods.

| Name | Purpose | Main limitation |
| --- | --- | --- |
| `toStdArray(...)` | Wraps the variable as a std array. | Can only be used in the top-level scope of the variable's first assignment. |
| `toStdVector(...)` | Wraps the variable as a std vector. | Can only be used in the top-level scope of the variable's first assignment. |
| `toStdMap(...)` | Wraps the variable as a std map. | Can only be used in the top-level scope of the variable's first assignment. |
| `toStdOrderedMap(...)` | Wraps the variable as a std ordered map. | Can only be used in the top-level scope of the variable's first assignment. |

## Mechanisms not counted in this list

- `$array->any()` is a universal method that maps to PHP `array_any()`, not the `any()` compile-time function.
- `Type::*` are compile-time type-description constants, not functions.
- keyword extension methods are a user-defined extension method mechanism and are not part of the fixed built-in compile-time function list.

## Implementation constraints

Compile-time functions should be usable in any legal expression position and maintain consistent semantics across all paths:

- `any()` is already handled uniformly at the ordinary function-call expression entry; assignments, parameters, return values, array elements, and operator subexpressions share the same semantics.
- `refval()` / `toRef()` have many special cases in argument parsing and dynamic call paths and should later be unified into a single "reference-wrapping expression" resolution entry.
- `objval()` is currently recognized through the function-call resolution and type-inference paths and is relatively centralized.
- `expected()` / `unexpected()` generate `EXPECTED(...)` / `UNEXPECTED(...)` respectively at the ordinary function-call entry and produce no PHP runtime function call.

Future refactoring goals:

- Establish a unified `CompileTimeFunctionResolver` or equivalent module.
- Reuse the same compile-time function metadata in `parseExpr()` / `detectTypeOfExpr()` / `detectClassOfExpr()` / argument parsing paths.
- Continue unifying the behavior of `refval()` and `objval()` across different expression paths.

# `#[Immutable]` compile-time effect checking

## Purpose

`#[Immutable]` is a TypePHP compile-time annotation modelled after C++ `const`.
It prevents accidental mutation in statically compiled code without adding a
wrapper object, Zend metadata, runtime branch, or ABI change.

It is intentionally a best-effort static tool rather than a security boundary.
Calls whose target is deliberately made dynamic are an escape hatch and do not
receive a runtime guard.

## Supported targets

```php
#[Immutable]
public function name(): string
{
    return $this->name;
}

function inspect(#[Immutable] User $user): string
{
    return $user->name();
}
```

The attribute is valid on methods and on function, method, and closure
parameters. On an instance method it makes `$this` immutable. On a parameter it
makes the binding immutable and, when it can contain an object, treats the
referenced object as immutable as well.

## Rejected operations

For an immutable root such as `$this` or `$user`, the compiler rejects:

- assignment, destructuring, and array-element or object-property writes;
- compound assignment, `++`, `--`, `unset()`, taking a reference, and
  `foreach (... as &$value)`;
- a statically named method call unless the resolved method is also marked
  `#[Immutable]`;
- a mutating value extension such as `$array->sort()`; read-only array/string
  methods remain available;
- passing an object to a statically resolved parameter that is not itself
  `#[Immutable]`;
- passing any immutable value to a mutable by-reference parameter, including
  extension functions such as `sort()`;
- storing an immutable object identity in an object property, array,
  global/static variable, or returning/yielding it as a mutable value.

An immutable by-reference parameter is supported. It acts like a C++ `const &`:
the reference is accepted because the callee is checked against mutation.

`#[MethodsFor]` follows the same contract. An object extension is callable on
an immutable receiver only when its receiver parameter is marked
`#[Immutable]`.

## Aliases, closures, generators, and inheritance

Local aliases of immutable objects remain immutable:

```php
$alias = $user;
$alias->rename('new'); // compile-time error
```

`clone` creates a distinct mutable object and therefore intentionally drops the
annotation. Captured variables, arrow functions, closure `$this`, and Fiber
generator bodies carry immutable metadata into their generated function
contexts.

An overriding class or interface method may strengthen an ordinary contract by
adding `#[Immutable]`, but it cannot remove `#[Immutable]` from an inherited
method or parameter.

## Value versus object semantics

Scalar values and PHP copy-on-write values can be read and copied normally. For
example, `count($values)` and `$copy = $values` do not modify an immutable array.
The compiler propagates immutability through an expression only when object
identity is possible.

## Explicit escape hatches

The following intentionally bypass static method-effect checking:

```php
$method = 'rename';
$user->$method('new');

$callable = getRuntimeCallable();
$callable($user);
```

The same applies to other runtime-only mechanisms that hide the target from the
compiler, including reflection and dynamic ZendVM code. TypePHP neither inserts
a runtime read-only proxy nor attempts to recover the escaped value later.

This boundary is deliberate: `#[Immutable]` should cost nothing in generated
code and should not complicate PHPX/ZendVM object semantics.

## Property hooks and magic access

Property-hook reads are lowered to generated method calls. Consequently, a hook
used through an immutable receiver must itself carry an `#[Immutable]` method
contract; otherwise the generated call is rejected. Fully dynamic magic access
is covered by the same escape-hatch rule as other runtime-only behavior.

## Implementation boundaries

The implementation is isolated in `src/Immutable/ImmutableSupportTrait.php`.
`FunctionDef` and `ArgInfo` retain only compile-time effect bits, while each
`FunctionContext` stores the immutable roots and object aliases relevant to that
body. Checks run during AST lowering and emit no C++ code when successful.

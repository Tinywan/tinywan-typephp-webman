# yield / Generator

TypePHP compiles functions, methods, anonymous functions, and arrow functions that contain `yield` or `yield from` into Fiber generators. Calling them does not immediately execute the generator function body; instead it returns a global `\FiberGenerator` object. The Fiber is created and started only on the first iteration, or when `current()`, `valid()`, `send()`, or `throw()` is called.

The generated C++ function body runs inside the Fiber. Each `yield` hands its key/value to the iteration driver via `Fiber::suspend()`, and resumes the original C++ call stack on `next()`, `send()`, or `throw()`. The runtime distinguishes not-started, running, suspended, normally-returned, and exceptionally-closed states via `NEW`, `RUNNING`, `SUSPENDED`, `CLOSED_RETURNED`, and `CLOSED_FAILED`.

## Iteration interop

The following combinations already work:

| Driver | Iterated object |
|---|---|
| TypePHP Native `foreach` | TypePHP Native generator |
| TypePHP Native `foreach` | Zend `Generator` returned by dynamic PHP |
| ZendVM `foreach` | `\FiberGenerator` returned by a TypePHP Native generator |
| TypePHP `yield from` | arrays, `Iterator`, `IteratorAggregate`, Zend `Generator`, `\FiberGenerator` |

`\FiberGenerator` implements `Iterator` with an interface signature consistent with PHP:

```php
rewind(): void
next(): void
valid(): bool
current(): mixed
key(): mixed
send(mixed $value): mixed
throw(Throwable $exception): mixed
getReturn(): mixed
```

The current state machine already covers first and repeated `rewind()`, `next()`/`send()`/`throw()` when not started, calls after closure, automatic integer keys, normal and exceptional `getReturn()`, exception propagation, and executing `finally` when destructing in a suspended state.

## Differences from PHP

### Not a Zend Generator

TypePHP generators return the global `\FiberGenerator`, not PHP's built-in `Generator`:

```php
$generator instanceof Iterator;  // true
$generator instanceof Generator; // false
```

Therefore the following differences exist:

- A generator function cannot declare the precise return type `Generator`.
- `Iterator`, `Traversable`, `iterable`, `object`, `mixed`, or union types containing these compatible types can be used.
- `ReflectionGenerator` only accepts Zend `Generator` and cannot be used with `\FiberGenerator`.
- `get_class()`, Reflection class info, and the class name in exception stacks differ from Zend `Generator`.
- No guarantee that `var_dump()`, debug properties, or the internal object layout match Zend `Generator`.
- `\FiberGenerator` is a final runtime-internal type; business code is forbidden from directly instantiating, inheriting, cloning, or serializing it.

### By-reference Generators not supported

The following PHP syntax is not yet supported:

```php
function &values(): iterable
{
    yield $value;
}

foreach (values() as &$value) {
}
```

TypePHP does not support:

- generator functions or methods returning by reference.
- by-reference yield semantics.
- by-reference `foreach` over a generator.
- maintaining element reference identity through a generator.

`current()`, `send()`, `throw()`, and `getReturn()` all return ordinary PHP values; the runtime unwraps `INDIRECT` and `REFERENCE` wrappers and does not return reference containers.

### Parameter limitations

TypePHP generators do not yet support the following parameter declarations:

- by-reference parameters, e.g. `function values(&$value)`.
- variadic parameters, e.g. `function values(...$values)`.
- by-reference variadic parameters, e.g. `function values(&...$values)`.

Ordinary parameters, defaults, union-typed parameters, object parameters, and `$this` in methods can be used. Parameter type checks and constructor property promotion execute when the generator object is created, while the function body remains lazily executed.

### Traversable boundary

`yield from` and TypePHP Native object `foreach` use different underlying paths. `foreach` uniformly drives arrays, ordinary objects, and Zend `Traversable` through PHPX `ForeachIterator`; `yield from` still performs delegation within the generator itself.

This means:

- userland `Iterator`, `IteratorAggregate`, Zend `Generator`, and internal `Traversable` provided by extensions are all iterated through the class's `get_iterator` handler.
- ordinary objects directly traverse the live property table, performing public, protected, and private visibility checks under the current TypePHP class scope.
- `foreach ($iterable as $value)` that does not read the key does not call `Iterator::key()`.
- TypePHP `yield from` detects `IteratorAggregate::getIterator()` returning itself or forming an object cycle, and throws an exception.
- TypePHP Native `foreach` delegates `IteratorAggregate` unwrapping and cycle detection to the Zend iterator handler, keeping behavior consistent with the current PHP runtime.
- the exception type, message text, and stack info for an invalid `getIterator()` return value may not fully match ZendVM.

### Fiber observable differences

Zend `Generator` is a dedicated ZendVM execution object; TypePHP generators use PHP Fibers to save the full C/C++ stack, so:

- the runtime environment must provide PHP Fibers.
- `Fiber`, internal closures, or TypePHP runtime frames may appear in exception stacks.
- file names, line numbers, and call-stack shapes are not guaranteed to exactly match `ReflectionGenerator` or Zend Generator.
- normal iteration, exception propagation, and `finally` during suspended destructors already have regression tests, but execution order for complex object cycles, request shutdown, process exit, and destructors that throw again may still differ from Zend Generator.
- when a Fiber is forcibly closed, Zend's internal graceful-exit is used to unwind the C++ stack; this object is not a public exception type that business code can catch or rely on.

### yield from differences

Key/value forwarding for arrays, ordinary Iterators, and generators, generator return values, and `send()`/`throw()` delegation are implemented, but the underlying implementation is not the Zend `yield from` opcode:

- delegation is done through the `rewind()`, `valid()`, `key()`, `current()`, `next()`, `send()`, `throw()`, and `getReturn()` methods.
- side effects, exception stacks, and call counts produced by custom Iterator methods should avoid depending on Zend Generator's internal implementation details.
- `yield from` over a non-generator Iterator yields `null`; only Zend `Generator` and `\FiberGenerator` read `getReturn()`.

## Performance differences

Fiber generators do not change the generated code for ordinary array or ordinary container `foreach`. Extra cost arises only when a generator is actually created and driven.

Each yield currently requires:

- Fiber suspend/resume.
- `Iterator` method calls.
- generator state and object property reads/writes.
- creation and release of the key/value payload array.
- additional delegation calls in the `yield from` scenario.

Therefore TypePHP Fiber generators are usually slower than Native C++ array `foreach`, and may also be slower than Zend's dedicated Generator opcode. High-frequency, short-element iteration should prefer arrays or Native containers; generators are more suited to lazy computation, streaming, and scenarios that need to preserve the full Native call stack.

## Incompatibility checklist

The following PHP behaviors currently cannot be relied upon:

- the returned object being a Zend `Generator`.
- `instanceof Generator`.
- declaring the precise `Generator` return type.
- `ReflectionGenerator`.
- by-reference generator returns, by-reference yield, or by-reference foreach.
- by-reference or variadic generator parameters.
- debug output and internal properties identical to Zend Generator.
- all internal extension `Traversable` being iterable through Zend iterator handlers.
- Fiber closure, complex destruction, and process exit with exactly the same stack and destruction order as Zend Generator.

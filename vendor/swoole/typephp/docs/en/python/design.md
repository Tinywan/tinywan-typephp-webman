# TypePHP and Python Language-Level Interop Design

> Status: the core design is confirmed and is being implemented in phases according to `python/implementation-plan.md`.
>
> This document is the design specification for syntax, type semantics, runtime boundaries, and compatibility goals; details that are not yet confirmed continue to be maintained at the end of the document.

## 1. Goals

TypePHP should provide, at the language level, the ability to call Python packages from TypePHP:

1. TypePHP imports Python modules, accesses module members, and calls Python functions and classes.
2. TypePHP operates on Python objects, including attributes, methods, subscripts, iteration, operators, and the call protocol.
3. TypePHP functions, closures, and objects can be passed as arguments to Python calls, and Python is allowed to synchronously call back into TypePHP within that dynamic call relationship.
4. The two VMs interoperate directly within the same process, without going through JSON, RPC, or subprocesses.
5. Python object identity and type information are preserved by default, avoiding unnecessary deep copies.
6. The syntax targets ordinary TypePHP/PHP developers; routine calls do not require understanding the CPython C API, the GIL, or reference counting.
7. This feature is an optional extension-level capability; projects that do not use Python syntax do not depend on phpy.

The most important language change is the Python special root namespace. `python\module\member()` in the global namespace, or `\python\module\member()` in other namespaces, can directly access module members; `use python\module` works entirely according to PHP's ordinary namespace alias rules, and the compiler does not apply any Python-specific treatment to the `use` statement. Both forms elevate what phpy originally required writing by hand as `PyCore::import('module')` and a returned variable into a compile-time-recognizable lazy module binding. Capabilities such as attributes, methods, subscripts, iteration, argument conversion, return wrapping, and exceptions for Python objects reuse phpy's existing implementation in principle, without re-establishing a separate runtime in TypePHP.

Non-goals:

- Do not compile Python source code, and do not attempt to replace CPython.
- Do not promise to statically type the dynamic Python API.
- Permanently do not support Python threads, `asyncio`, or CPython subinterpreters.
- Do not generate a Python extension, and do not register TypePHP functions, classes, or modules with Python.
- Do not provide `#[PythonExport]` or any other TypePHP symbol export mechanism.
- Do not aim to be compatible with Python syntax; the goal is to let TypePHP programs call Python packages conveniently and reliably.
- Do not automatically and recursively copy arbitrary Python containers into TypePHP arrays.

## 2. Reference Designs

### 2.1 Mojo

Mojo uses an unmodified CPython runtime to guarantee Python ecosystem compatibility, and wraps dynamic Python values with a unified `PythonObject`. TypePHP only borrows its embedding and object-wrapping design, not its export mechanism.

Borrowable parts:

- Python values remain as wrapper objects by default.
- TypePHP base values can be automatically converted when passed into Python.
- Conversion from Python values back to TypePHP native types is explicit.
- Dynamic Python values are carried by a unified proxy type.

References: [Mojo Python interoperability](https://docs.modular.com/stable/mojo/manual/python/), [Mojo Python types](https://docs.modular.com/mojo/manual/python/types).

### 2.2 pybind11

pybind11 explicitly distinguishes object ownership, return value policies, interpreter lifetime, GIL guards, positional arguments, and keyword arguments. Its experience shows that the most dangerous part of cross-language calls is not the call syntax, but object lifetime and exception paths.

TypePHP should not expose pybind11's ownership policies to ordinary users, but the runtime must establish an equally strict internal contract.

References: [pybind11 embedding](https://pybind11.readthedocs.io/en/stable/advanced/embedding.html), [pybind11 functions](https://pybind11.readthedocs.io/en/stable/advanced/functions.html).

### 2.3 PyO3

PyO3 uses GIL tokens and Python object pointers with lifetimes, distinguishing held objects, borrowed objects, and GIL-bound objects at the type system level.

TypePHP does not need to expose lifetime parameters to users, but phpy's C++ layer should borrow from this: every CPython API call must be able to prove the GIL is currently held, and every `PyObject*` must be clearly an owned, borrowed, or stolen reference.

References: [PyO3 object model](https://pyo3.rs/main/doc/pyo3/), [PyO3 Python object types](https://pyo3.rs/main/types).

## 3. The Positioning of phpy

phpy is a runtime foundation candidate for this feature, not a validated stable dependency.

Reusable capabilities include:

- Initializing CPython within the ZendVM process.
- Boundary conversion between `zval` and `PyObject*`.
- Proxy objects for Python modules, objects, strings, sequences, dictionaries, sets, iterators, and callables.
- Callable proxies for TypePHP/PHP closures passed into Python.
- Basic mapping from Python exceptions to Zend exceptions.
- Infrastructure for Python synchronous calls to functions, objects, and callable proxies actively passed in by the ZendVM.
- A prototype of the GIL RAII guard.

However, it cannot be assumed that the existing implementation is completely correct. Subsequent implementation must simultaneously review phpy, refactor the boundaries, add tests, fix bugs, and optimize performance.

Key audit items already identified during the design phase:

- CPython initialization, repeated initialization, shutdown ordering, and destruction of still-alive objects.
- The owned/borrowed/stolen reference rules for every CPython API.
- The `Py_INCREF/Py_DECREF` symmetry on all success and exception paths.
- GIL acquisition, reentrant calls, and the behavior of TypePHP calling Python, which calls back into TypePHP, which calls Python again.
- The conversion process has been changed so that each top-level conversion creates an independent C++ converter object; the conversion policy, recursion stack, and depth limit are all per-object state, restored by RAII, and no longer use process-level or thread-level temporary function pointers. Cross-VM callbacks and lifetime boundaries still need to continue to be audited.
- After Python exceptions are converted to Zend exceptions, whether the CPython error indicator is always correctly cleared.
- When Zend exceptions are converted to Python exceptions, preservation of the original exception type, message, and traceback.
- Whether the operator protocol is correct. For example, PHP `/` must not be mapped to Python floor division.
- Python big integers, invalid UTF-8, bytes containing NUL, recursive containers, and cyclic references.
- When Python proxies hold Zend objects, the cross-VM reference cycles that may form between the Zend GC and the CPython GC.
- Python threads, `asyncio`, and subinterpreters must be permanently and explicitly rejected, rather than producing undefined behavior.

TypePHP dynamically calls the `PyCore`, `PyObject`, `PyDict`, and other Facades exposed by the phpy extension through the ZendVM, without directly linking `libphpy.so` and without generating any phpy C++ symbol references. Existing public names must be preserved; TypePHP does not establish a second user-visible naming system.

Division of responsibilities:

- phpy is responsible for all runtime concerns: CPython initialization, the GIL, reference counting, object proxies, type conversion, exceptions, and the dual-VM lifecycle.
- phpy is responsible for providing a stable, testable Zend internal class/function/object-handler API.
- TypePHP is only responsible for recognizing language syntax, static types, and evaluation order, and generating dynamic calls based on `zend_function*` and the PHPX/Zend generic object API.
- TypePHP does not directly operate on raw `PyObject*`, and does not duplicate phpy's GIL, reference counting, or exception implementation.
- When fixing runtime bugs, fix phpy first, rather than only adding patches in TypePHP's generated code to bypass them.

Minimal adaptation principle:

- TypePHP's core new capabilities are Python `use` resolution, module alias symbols, and corresponding code generation.
- `python\name()`, `module\name`, `module\name()`, and operator lowering should all land on phpy's Zend Facade; Python operators call the complete CPython operator protocol through the standard library `operator` module.
- For behavior that phpy has already correctly solved, only add tests and reuse it; only modify phpy when review or tests prove a bug, that implicit conversion does not conform to TypePHP rules, or that a Zend dynamic entry point is missing.
- TypePHP does not implement CPython protocol details, and does not duplicate `PyCore`, `PyObject`, or `PyModule` logic in generated code.

## 4. Optional Extension and Runtime Detection

Python interop is an extension-level feature, not a mandatory dependency of the TypePHP core program.

- TypePHP generated code only depends on ZendVM/PHPX, does not include phpy headers, and does not link `libphpy.so`.
- The compiler recognizes Python syntax and retains logical `PyObject` type information, but does not check whether the phpy SDK, dynamic library, ABI, or Python module exists.
- phpy must be loaded by the runtime environment like an ordinary PHP extension and register `PyCore`, `PyObject`, and other Zend internal classes.
- The first time a Python symbol is actually used, TypePHP resolves `PyCore` and the corresponding `zend_function*` through the class map/func map.
- When phpy is not loaded, the Zend class lookup throws a catchable PHP `Error`; if not caught, it becomes a fatal error under ordinary PHP rules.
- When phpy is loaded but the Python module does not exist, `PyCore::import()` throws a `PyError` through phpy.
- When there is only `use python\sys` without actually accessing any Python symbol, no runtime resolution occurs, so no error is reported even if phpy is not installed.

This model allows the same TypePHP binary to run Python-free paths in environments without phpy installed, and avoids TypePHP establishing a native C++ ABI dependency on phpy.

### 4.1 TypePHP Code Isolation

All Python-specific implementation in TypePHP must be concentrated in an independent subdirectory, tentatively:

```text
src/Python/
```

This directory is responsible for:

- Recognition of the `python` special root namespace.
- The import/module symbol table.
- Python Zend class/method name and logical return type mapping.
- Python syntactic sugar and static return type mapping.
- C++ lowering for Python calls, attributes, subscripts, iteration, and operators.
- Python-specific diagnostics.

The general Parser, TypeSystem, Optimizer, and Generator may only keep minimal, stable extension entry points, and should not scatter `if ($isPython...)` special cases. When the Python feature is not enabled, Python-specific analyzers are not loaded, and existing code generation paths are not changed.

Tests are likewise organized independently; it is recommended to use:

```text
phpunit/src/Python/
phpunit/code/python/
tests/compiler/python/
```

The specific directory names will be confirmed during the coding plan phase, but "implementation and test isolation" is a design constraint.

## 5. Overall Runtime Model

The following model is adopted:

- One ZendVM and one CPython main interpreter coexist in one process.
- CPython is initialized and shut down entirely through phpy's existing extension lifecycle; TypePHP does not establish a second initialization path.
- All Python API boundaries automatically acquire the GIL; ordinary users do not operate the GIL.
- `PyObject` and its subclasses such as `PyDict`, `PyList`, and `PyStr` hold CPython strong references.
- Copying Python proxy objects increments the reference count; destruction decrements it within a valid interpreter/GIL context.
- Borrowed references are only allowed to exist in phpy's internal short-lifetime scopes and are not exposed to TypePHP.
- TypePHP calling Python, Python synchronously calling back a callable that TypePHP passed in as an argument, and that callable calling Python again must all support synchronous reentrancy.
- Python cannot independently import TypePHP applications, nor look up TypePHP functions or types through a global registry.

All Python objects held by TypePHP must be released before the interpreter shuts down. One must not rely on `Py_Finalize()` to automatically repair incorrect lifetimes.

## 6. Module Names and Import Syntax

`python` is a reserved root namespace recognized by the compiler:

```php
python\math\sqrt(16);
python\os\path\join('/tmp', 'file.txt');

use python\sys;
use Python\numpy as np;
use python\numpy\linalg as linalg;
```

A fully qualified name does not require writing `use` first:

```php
$root = python\math\sqrt(16);
$pi = Python\math\pi;
```

All segments after the Python root and before the last `\` constitute the Python module path, and the last segment is the module member. PHP's `\` is converted to Python's `.` during import. Therefore `python\os\path\join()` in the global namespace unambiguously means the `join` callable of module `os.path`.

Python module names still strictly obey PHP's namespace resolution rules. When inside an ordinary PHP namespace, a fully qualified module name must use a leading `\`:

```php
namespace App;

\python\math\sqrt(16); // Python module math
python\math\sqrt(16);  // ordinary PHP name App\python\math\sqrt, not a Python module
```

This is part of PHP syntax; `python` is not treated as an exception that bypasses the current namespace. `use python\math;` imports from the root name like any other PHP `use` declaration, so an alias shorthand can also be used inside a namespace.

`use` is only used to shorten a fully qualified name, not a precondition for accessing a Python module:

PHP's `use function` and `use const` apply equally, and ordinary `as` aliases are supported:

```php
use function python\len;
use function python\math\sqrt as py_sqrt;
use const python\math\pi as py_pi;

$length = len([1, 2, 3]);
$root = py_sqrt(16);
$pi = py_pi;
```

These declarations are still handled entirely by PHP name resolution. TypePHP only enters Python lowering when the final fully qualified name of a `FuncCall` or `ConstFetch` is in the root namespace `python\...`; the `use` declaration itself does not import a Python module.

Respectively equivalent to:

```python
import sys
import numpy as np
import numpy.linalg as linalg
```

In the existing phpy PHP API, semantically corresponding to:

```php
$sys = PyCore::import('sys');
$np = PyCore::import('numpy');
$linalg = PyCore::import('numpy.linalg');
```

`PyCore::import()` returns a `PyModule`/`PyObject` variable, and subsequent attributes and methods are accessed through that variable. `use python\module` is just an ordinary PHP namespace alias; it does not perform the import immediately, and does not generate a ZendVM class, namespace, or user-visible variable. The compiler only checks the fully qualified name resolved by PHP when processing a function call or constant read.

When the compiler finds `module\attr` or `module\func()` in function code, it uses the same compiler structure as the existing `funcMap`: it allocates an integer ID for the actually-used fully qualified module name, generates a unified `THREAD_LOCAL` zval array, and dynamically calls `PyCore::import()` through a lazy getter. The following names are only design sketches:

```cpp
THREAD_LOCAL zval php_python_module_map[module_count];

php::Object php_get_python_module(int module_id, const php::Str &module_name)
{
    zval *module = &php_python_module_map[module_id];
    if (UNEXPECTED(Z_ISUNDEF_P(module))) {
        // Resolve PyCore::import through classMap/funcMap and invoke zend_function*.
        php::Variant value = php::call(/* cached zend_function* */, php::ArgList{module_name});
        ZVAL_COPY(module, value.ptr());
    }
    return php::Object(module);
}
```

Corresponding lowering:

```text
use Python\numpy as np
    -> compile-time namespace marker: np => "numpy"
    -> module id allocated only when np is actually referenced

np\version
    -> php::Object(php_get_python_module(module_id, "numpy")).attr("version")

np\array($value)
    -> php::Object(php_get_python_module(module_id, "numpy")).call("array", converted($value))

python\numpy\array($value)
    -> the same module id and lowering as np\array($value)

python\os\path\join($left, $right)
    -> php::Object(php_get_python_module(module_id, "os.path")).call("join", ...)
```

The same fully qualified module name is allocated only one ID across the entire TypePHP build; when the fully qualified name and any `use` alias refer to the same module, they also share that ID. If the current `.php` file only has `use python\sys` but no `sys\attr`, `sys\func()`, or other `sys` symbol access appears, the compiler does not allocate a module ID for it, the runtime does not call `import('sys')`, and no error is reported because the Python environment lacks that module.

An unused module does not trigger any phpy runtime resolution. `tpc` only checks the syntax and alias conflicts of `use python\sys` itself, does not check the phpy SDK/ABI, and does not add phpy link dependencies.

### 6.1 Relationship with `funcMap`

`pythonModuleMap` reuses the overall validated pattern of `funcMap`:

- At compile time, a `fully qualified module name -> integer ID` map is used for deduplication.
- Data declarations are generated centrally; ordinary `.cc` files only reference the extern array and getter.
- The getter initializes on first access, and subsequent accesses hit the array directly.
- IDs are only allocated for modules where member access or calls actually occur.
- Cleanup is centralized during the application/request clean phase.

However, the two cannot mechanically use the exact same cleanup code:

- `funcMap` stores non-owning `zend_function*` owned by the Zend function table, so it can be cleaned up directly with `memset`.
- `pythonModuleMap` stores Zend `PyModule` object zvals returned by phpy, and cannot directly `memset` over valid objects.
- Request clean must perform `zval_ptr_dtor()` on each entry and restore it to `UNDEF`, letting phpy's own Zend object destructor handle Python references and the GIL.
- On import failure, the slot stays `UNDEF`; exception values or half-initialized objects must not be cached.

Cleanup is done by TypePHP using the ordinary Zend zval API, without calling phpy C++ symbols:

```cpp
for (zval &module : php_python_module_map) {
    if (!Z_ISUNDEF(module)) {
        zval_ptr_dtor(&module);
        ZVAL_UNDEF(&module);
    }
}
```

TypePHP only releases the Zend object; its internal Python reference counting, GIL, and error state are still handled by the phpy object handler.

### 6.2 `sys.modules` Remains the Global Source of Truth

Python import is itself global. When the getter first calls the underlying import, CPython returns the already-loaded module from `sys.modules` or performs the first load. `pythonModuleMap` is not a second import system; it is only equivalent to the binding stored in a Python file's namespace after it executes `import numpy as np`:

```text
php_get_python_module(id, "numpy")
    -> PyModule zval binding within the TypePHP request
    -> CPython sys.modules (global module identity and load state)
```

It avoids re-entering the Python import API on every function call, while not taking on package lookup, loading, or reload logic. Even if the same module is referenced by multiple TypePHP files under different aliases, as long as the fully qualified module name is the same, the same ID and `PyModule` Zend object zval are used.

This binding is consistent with ordinary Python import: if Python code later deletes or replaces `sys.modules['numpy']`, the already-completed `np` binding is not automatically changed; an explicit `PyCore::import('numpy')` is handled according to the `sys.modules` state at the time of the call.

Rules:

- The `python` root namespace is case-insensitive; `python`, `Python`, and `PYTHON` are all recognized as the same language symbol.
- Only the root namespace is case-insensitive. Subsequent module paths, members, methods, and keyword argument names are strictly case-sensitive.
- `python\package\module\member` in the global namespace, and `\python\package\module\member` in other namespaces, are fully qualified module accesses that do not require `use` and perform lazy import on first actual execution.
- `python\...` without a leading `\` inside a namespace is a relative PHP name and must have the current namespace prepended under PHP rules; it cannot be recognized as a Python module.
- `use python\...` can only import Python modules.
- Whether the module exists can only be determined by CPython at runtime.
- `from package import *` is not supported.
- The first version does not design a separate `from package import name` syntax; members are uniformly accessed through module aliases.
- The root namespace `\python` is reserved for language interop; for example, `App\python` is still an ordinary PHP namespace.
- Module aliases must not conflict with TypePHP classes, namespace imports, or other Python module aliases in the current file.
- Users can still call `PyCore::import()` directly and save the returned `PyModule` into an ordinary variable; fully qualified names and names resolved through ordinary PHP `use` both use the `pythonModuleMap` lazy binding.

Example:

```php
python\len($value); // correct
Python\len($value); // correct, different root namespace casing
python\Len($value); // error, wrong casing for the Python builtin name
Python\Len($value); // error
```

When the resolution result is in the root namespace `\python`, it is converted by the TypePHP compiler into a Python language symbol; when it resolves to another name such as `App\python`, an ordinary PHP function or class lookup is still performed.

## 7. Module Members

A Python module appears in TypePHP as a namespace, not a class. Names in the module are still dynamically resolved by the Python VM as attributes.

### 7.1 Package Variables

Reading a Python package variable uses the PHP namespace constant syntax `module\name`:

```php
use python\math;
use python\os;
use python\numpy as np;

$pi = math\pi;
$environ = os\environ;
$arrayType = np\ndarray;
$directPi = python\math\pi;
$text = math\pi->__str__();
```

This uses PHP's legal namespace constant expression, but TypePHP does not register it as a Zend constant and does not perform constant folding. The compiler lowers each read to a Python module attribute lookup, and the result remains a `PyObject`, so object methods can continue to be called.

`math::pi` or `math::$pi` is not allowed for reading package variables; both are class member syntax and would incorrectly express the module as a class. When the compiler finds such legacy syntax, it produces a targeted FatalError and suggests using `math\pi` instead.

### 7.2 Package Functions and Class Construction

Calling a callable in a Python package uses the PHP namespace function syntax `module\name(...)`:

```php
$a = np\array([1, 2, 3]);
$b = np\array([4, 5, 6]);
$c = np\add($a, $b);
$root = python\math\sqrt(16);
$joined = python\os\path\join('/tmp', 'file.txt');
```

The compiler reads the module's `name` attribute and calls the resulting Python object. That object can be:

- A Python function.
- A Python class, in which case the call performs that class's construction process and returns an instance.
- Another Python object implementing `__call__`.

TypePHP does not need to, and cannot, determine from the `np\array()` syntax alone whether it is a function or a class construction; callability is determined by Python at runtime. When a member does not exist, a Python `AttributeError` is produced; when a member is not callable, a Python `TypeError` is produced; both are uniformly mapped to `PyError`.

The first version only supports reading module attributes. PHP namespace constant syntax itself cannot be an assignment target; when writing is needed, it should be done explicitly through the Python object API:

```php
$os = PyCore::import('os');
python\setattr($os, 'name', $value);
```

## 8. Python Builtins and phpy Syntactic Sugar

`python\name()` means calling a Python builtin:

```php
python\print('hello'); // equivalent to PyCore::print('hello')
$length = python\len($value)->toValue()->toInt();
$range = python\range(0, 10);
$type = python\type($value);
```

It is not an ordinary TypePHP namespace function. The compiler resolves the `zend_function*` corresponding to `PyCore` through the class/func map and dynamically calls it; the runtime semantics are consistent with directly writing the corresponding `PyCore` call.

Names are strictly case-sensitive. For known wrong names in the compiler's builtin mapping, errors can be reported at compile time; failures of other dynamic builtin lookups produce a Python `AttributeError`.

Some names are syntactic sugar for existing phpy type constructors, rather than directly calling the same-named Python builtin:

| TypePHP syntax | Equivalent phpy API |
|---|---|
| `python\dict($array)` | `new PyDict($array)` |
| `python\list($array)` | `new PyList($array)` |
| `python\tuple($array)` | `new PyTuple($array)` |
| `python\set($array)` | `new PySet($array)` |
| `python\str($value)` | `new PyStr($value)` |
| `python\object($value)` | `new PyObject($value)` |
| `python\print(...)` | `PyCore::print(...)` |
| `python\scalar($value)` | `PyCore::scalar($value)` |

For example:

```php
$dict1 = new PyDict([1, 2, 3, 4]);
$dict2 = python\dict([1, 2, 3, 4]);
```

The two must have exactly the same runtime semantics. Here one cannot simply forward to CPython's `dict([1, 2, 3, 4])`, because the native Python builtin would interpret the argument as a key/value pair iterable, which differs from `PyDict`'s PHP array construction rules.

The mapping of all syntactic sugar must form a closed, tested table; it must not be guessed based on function names alone.

This mapping also determines compile-time static types:

```php
$list1 = new PyList();
$list2 = python\list();

$dict1 = new PyDict();
$dict2 = python\dict();
```

- Both `$list1` and `$list2` are `PyList` typed objects.
- Both `$dict1` and `$dict2` are `PyDict` typed objects.
- Both forms must use the same type checking, method resolution, and Native Call optimization.
- Syntactic sugar must not degrade to `mixed`, `var`, or only the base type `PyObject`.
- Python builtin calls also obey the object-retention rule. For example, `python\len()` returns a `PyObject` wrapping a Python int; to obtain a definite type, one must first leave the Python object rules via `toValue()` (or the function entry `python\scalar()`), then use ordinary TypePHP conversion. The Python `None` result of `python\print()` also remains a `PyObject`; when used as a standalone statement, it can simply be discarded.
- Neither `PyObject::toValue()` nor `python\scalar()` is an ordinary Python builtin call; both are conversion boundaries that explicitly require exiting the Python type rules, so they return a TypePHP `var`.
- Dynamic Python module member calls uniformly return `PyObject`.

## 9. Python Object Types

All Python values whose static type cannot be determined at compile time are uniformly represented as:

```php
PyObject
```

`PyObject` is phpy's existing public type and TypePHP's official runtime type. `python\Object` or `python\Any` will not be introduced.

Python built-in types continue to use phpy's existing concrete proxy classes, such as `PyDict`, `PyList`, `PyTuple`, `PySet`, `PyStr`, `PyType`, `PyFn`, and `PyIter`. In this way, ordinary PHP and TypePHP users see the same type system.

Python's `None` is also a legal Python object. Its automatic conversion rules with TypePHP `null` need to be defined separately; Python `None` cannot be represented by a null pointer.

## 10. Object Operations

### 10.1 Attributes and Methods

```php
$env = os\environ;
$items = $env->items();
$name = $object->name;
$object->name = 'new value';
unset($object->name);
```

These are respectively mapped to Python's `getattr`, call, `setattr`, and `delattr` protocols.

`PyObject` explicitly provides two PHP Facade methods, `toValue()` and `toArray()`. `toValue()` is equivalent to `PyCore::scalar()` / `python\scalar()`, recursively converting a Python value into a PHP builtin value. Its return value then uses ordinary TypePHP conversion methods to determine the type:

```php
$pyValue = np\int64(42); // PyObject
$value = $pyValue->toValue()->toInt(); // TypePHP int
```

Here `toInt()` acts on the TypePHP value already returned by `toValue()`, not on the `PyObject`.

`toArray()` only converts Python `list`, `tuple`, `set`, `dict`, and iterators. Container elements are recursively converted to PHP values; an iterator is consumed, and subsequent conversion can only obtain its remaining elements. Python types that are not convertible return an empty array. `toArray()` is also a TypePHP keyword method, but PHPX's object conversion path calls `PyObject::toArray()`; `toString()` continues to call `PyObject::__toString()` through the keyword method, and phpy does not declare `toString()` again.

### 10.2 Subscripts

```php
$value = $object[$key];
$object[$key] = $value;
unset($object[$key]);
isset($object[$key]);
```

These are respectively mapped to the Python mapping/sequence protocol.

`isset()` keeps PHP's emptiness semantics: it returns `false` when the key or index does not exist, and also returns `false` when the corresponding value is Python `None`. The runtime only recognizes `KeyError` / `IndexError` as "missing"; other exceptions thrown by the Python protocol must continue to be mapped to `PyError` and must not be swallowed by `isset()`. Integer subscripts of list and tuple follow Python's negative index rules.

### 10.3 Calling Objects

```php
$result = $callable($arg1, $arg2);
```

The runtime uses `PyObject_Call`. A non-callable object produces a Python `TypeError`, mapped to a TypePHP-catchable Python exception.

### 10.4 Iteration

```php
foreach ($pythonIterable as $value) {
    // Python __iter__ / __next__
}
```

The keyed form:

```php
foreach ($pythonIterable as $index => $value) {
}
```

A generic Python iterator uses the TypePHP iteration ordinal starting from `0` as `$index`, and `$value` is the object produced by `__next__()`. `PyDict` is phpy's dedicated mapping wrapper; a keyed `foreach` uses PHP mapping conventions: `$index` is the dict key, and `$value` is the corresponding dict value. Python exceptions from `__iter__()` / `__next__()` must be propagated as `PyError` and must not be treated as normal end-of-iteration.

## 11. Arguments and Keyword Arguments

Ordinary arguments are evaluated from left to right, then Python positional args are constructed:

```php
$model = AutoModel\from_pretrained(
    'model-name',
    trust_remote_code: true,
    device_map: 'auto',
);
```

TypePHP named arguments are mapped to Python keyword arguments. Argument names are strictly case-sensitive.

PHP/TypePHP array unpacking rules can be used to construct positional and keyword arguments, but must satisfy:

- Integer keys produce positional arguments.
- String keys produce keyword arguments.
- Positional arguments must not appear after keyword arguments.
- Duplicate keywords produce a Python `TypeError`.

Whether to add explicit `python\args()` / `python\kwargs()` types is left for later discussion; the first version reuses the existing call and array unpacking syntax as much as possible.

## 12. Explicit Conversion Principle

TypePHP does not inherit phpy's return value implicit conversion behavior at the ZendVM Facade/opcode level. At the language level it adopts the principle of "automatic conversion when arguments enter the Python boundary, Python return values remain objects, and explicit conversion when returning to TypePHP."

Scenarios where automatic conversion is allowed must be syntactically explicit that the code is entering Python:

- `python\name(...)`.
- Python module calls, such as `np\array(...)`.
- Calls to `PyObject` methods or callables.
- Explicit Python container construction, such as `new PyList(...)` or `python\list(...)`.
- Parameter declarations requiring `PyObject`, `PyDict`, or other phpy types.
- Mixed arithmetic expressions composed of `PyObject` and TypePHP values.

Within these call boundaries, all argument expressions are first strictly evaluated in TypePHP left-to-right order, then converted into objects that Python can accept. TypePHP scalars are converted to the corresponding Python scalars; TypePHP arrays are recursively converted to Python list/dict, a process that produces deep copies. This must not spread into a global implicit conversion in ordinary TypePHP expressions that do not contain Python objects.

"Automatic conversion of all arguments" only applies to TypePHP types explicitly supported by the conversion table; resource or other values without a Python representation must throw a clear type error, not silently convert or pass invalid pointers.

Implicit conversion is not allowed in the following scenarios:

- Assigning a `PyObject` directly to `int`, `float`, `bool`, `string`, or `array`.
- Implicitly deep-copying a Python container into a TypePHP array.
- Arbitrarily turning a Python object into a TypePHP scalar due to arithmetic, comparison, or string context.
- Changing the static type of a TypePHP variable based on the runtime Python type.

`echo $pyObject` can continue to be compatible with the existing `PyObject::__toString()`, but this only belongs to the output protocol and cannot be treated by the compiler as a general string implicit conversion.

## 13. TypePHP to Python Conversion

The Python call boundary allows the following automatic conversions:

| TypePHP | Python | Semantics |
|---|---|---|
| `null` | `None` | singleton, not an empty `PyObject*` |
| `bool` | `bool` | value conversion |
| `int` | `int` | Python arbitrary-precision integer |
| `float` | `float` | double |
| `string` | `str` | requires valid UTF-8 |
| list array | `list` | recursive copy |
| map array | `dict` | recursive copy |
| `PyObject` and subclasses | the original object | zero copy, only passing a reference |
| TypePHP callable | Python callable proxy | Python can synchronously call back TypePHP |
| TypePHP object | Zend object proxy | object attributes are not automatically copied |

PHP arrays use rules such as `zend_array_is_list()` to decide whether to convert to a Python `list` or `dict`. An empty array defaults to a Python `list`; if an empty dict is needed, an explicit construction API must be provided.

Arrays and ordinary TypePHP strings may incur allocation and copying each time they enter the Python boundary. Documentation and performance diagnostics should suggest constructing and reusing native Python proxy types such as `PyDict`, `PyList`, and `PyStr` early for high-frequency calls, loop calls, or large-data scenarios, to avoid repeated deep copies. When `PyObject` and its subclasses enter the Python boundary, only a reference to the original object is passed; no content copying occurs.

Recommended form:

```php
// Convert only once; subsequent calls pass the same Python object.
use python\processor;

$pyItems = python\list($items);
for ($i = 0; $i < 1000; $i++) {
    processor\consume($pyItems);
}
```

Passing the same TypePHP container repeatedly as an argument inside a loop should be avoided, because each crossing of the Python call boundary deep-copies it again:

```php
for ($i = 0; $i < 1000; $i++) {
    processor\consume($items);
}
```

Strings and bytes must be distinguished. TypePHP `string` maps to Python `str` by default; binary content uses explicit `python\bytes()`.

Recursive arrays, cyclic references, and overly deep nesting must be detected and throw exceptions, rather than recursing infinitely.

## 14. Python to TypePHP Conversion

### 14.1 Default Rules

TypePHP's Python-specific call paths must turn off phpy's return value implicit conversion; all Python function, method, construction call, and operation results remain phpy objects. The static return type of dynamic calls is uniformly `PyObject`; it must not implicitly convert to a TypePHP value just because the runtime result happens to be a Python `bool`, `int`, `float`, `str`, `list`, or `dict`.

The current implementation has the generated code dynamically call `PyCore::setOptions(['return_as_object' => true])` the first time a Python expression is actually executed. This initialization is a request-level lazy guard: only writing `use python\module` without accessing Python symbols does not trigger phpy; a constructor-only program also completes configuration before construction; request clean resets TypePHP's own guard. If phpy later provides an object-retention standalone entry point without global mode, this runtime implementation can be replaced without changing the language semantics.

The phpy construction syntactic sugar known to the compiler still retains the precise subclasses; for example, `python\list()` returns `PyList` and `python\dict()` returns `PyDict`; these types are all `PyObject` subclasses and do not constitute return value implicit conversion.

The phpy Zend Facade should provide mutually independent "retain Python object" and "explicitly convert to TypePHP" entry points. It must not temporarily switch by modifying process-level global function pointers or global conversion modes, otherwise nested calls, synchronous reentrancy, and exception paths may leak the wrong policy to subsequent calls. Ordinary Python calls generated by TypePHP only dynamically call the object-retention entry point; `PyObject::toValue()` and `python\scalar()` ultimately call the explicit scalar conversion entry point.

phpy internally already uses `PythonToPhpConverter` and `PhpToPythonConverter` to implement this constraint. Each top-level conversion has an independent instance, and recursive sub-values reuse the same instance; container entry and exit are managed by RAII guards, and cyclic containers or inputs exceeding the depth limit throw a `PyError` without polluting subsequent conversions or causing a process crash.

Reasons:

- Preserve Python object identity and precise type.
- Avoid immediate deep copying when containers are returned.
- A Python `int` may exceed the TypePHP `int` range.
- Subclasses of Python types may override protocols and must not be forcibly expanded as base containers.
- Avoid phpy's current "partially auto-converted scalars, partially wrapped objects" behavior entering the TypePHP static type system.

### 14.2 Explicit Conversion

A Python object can only enter the TypePHP type rules through `toValue()`, `python\scalar()` (or the equivalent hand-written `PyCore::scalar()`):

```php
$nativeValue1 = PyCore::scalar($value);
$nativeValue2 = python\scalar($value); // fully equivalent syntactic sugar
$nativeValue3 = $value->toValue();
$integer = $value->toValue()->toInt();
$float = $value->toValue()->toFloat();
$boolean = $value->toValue()->toBool();
$string = $value->toValue()->toString();
$array = $value->toArray();
```

Rules:

- `toValue()` is an ordinary public method of `PyObject`, not registered as a TypePHP keyword method; internally it reuses the same converter as `PyCore::scalar()` in phpy.
- `toArray()` retains TypePHP global keyword method semantics. When PHPX performs array conversion on an object, it preferentially calls its public `toArray()`, so it enters the phpy implementation.
- After explicit conversion completes, the result fully enters TypePHP's static type, operator, and argument passing rules, no longer using Python protocols.
- Container conversion is an explicit deep conversion and detects recursive references.
- Python big integers must not silently overflow; the existing conversion rules need review before the precise mapping to TypePHP `BigInt` is determined.
- Python `str` and `bytes` must be distinguished; both must not be unconditionally converted to a TypePHP string.
- phpy is responsible for the general value conversion of `PyObject::toValue()` / `PyCore::scalar()`, and the limited container conversion of `PyObject::toArray()`. `toInt/toFloat/toBool` are post-conversion PHP values; `toString()` still calls `PyObject::__toString()` through the TypePHP keyword method.

Existing phpy PHP users can retain compatible behavior; TypePHP calls phpy's object-retention Zend API. For this, phpy internal class methods can be refactored or added, but no C++ link dependency from TypePHP to phpy is introduced.

## 15. Operators via the Python `operator` Module

For `PyObject` and its subclasses:

- `+ - * / % ** << >> & | ^` map to the corresponding functions of the Python standard library `operator` module.
- `/` maps to `operator.truediv()`, and must not map to `operator.floordiv()`.
- Python floor division temporarily uses `python\floordiv($a, $b)`, because TypePHP has no `//` operator.
- `== != < <= > >=` map to `operator.eq/ne/lt/le/gt/ge()` respectively.
- `===` / `!==` map to `operator.is_()` / `operator.is_not()` respectively.
- `if ($object)`, `!$object` use `operator.truth()`.
- Compound assignments map to `operator.iadd/isub/...()`, and the returned object updates the lvalue.

Base mapping:

| TypePHP | Generated dynamic call |
|---|---|
| `$a + $b` | `operator\add($a, $b)` |
| `$a - $b` | `operator\sub($a, $b)` |
| `$a * $b` | `operator\mul($a, $b)` |
| `$a / $b` | `operator\truediv($a, $b)` |
| `$a % $b` | `operator\mod($a, $b)` |
| `$a ** $b` | `operator\pow($a, $b)` |
| `$a << $b` | `operator\lshift($a, $b)` |
| `$a >> $b` | `operator\rshift($a, $b)` |
| `$a & $b` | `operator\and_($a, $b)` |
| bitwise OR | `operator\or_($a, $b)` |
| `$a ^ $b` | `operator\xor($a, $b)` |
| `-$a` | `operator\neg($a)` |
| `+$a` | `operator\pos($a)` |
| `~$a` | `operator\invert($a)` |
| `$a += $b` | `$a = operator\iadd($a, $b)` |

All operands must be evaluated strictly from left to right.

Even if the source code does not explicitly write `use python\operator`, when a Python operator appears, the compiler treats it as an implicit module binding used only for internal lowering, and obtains the `operator` module through the same `pythonModuleMap`. It does not inject a visible alias into the user file, so it does not conflict with the user's own `operator` class or use alias. When the user explicitly writes `use python\operator`, internal lowering and user access reuse the same module ID.

Identity comparison calls `operator\is_()` / `operator\is_not()`. Even if the `operator\eq()` result of two objects is true, as long as they are not the same Python object, `===` is still false.

Mixed arithmetic between Python objects and TypePHP values is allowed. As long as one side of the current operation node has static type `PyObject` or its subclass, the TypePHP expression on the other side is first fully evaluated under TypePHP rules, then the resulting value is converted to a Python object, and finally CPython executes the protocol corresponding to the current operation node.

For example:

```php
$result1 = $pyInt + 10;          // 10 is converted to a Python int; Python performs the addition
$result2 = $pyList * getCount(); // getCount() is evaluated first, then converted to a Python int
$native = $pyInt->toValue()->toInt() + 10; // already explicitly converted to a TypePHP int; TypePHP addition is used
```

The result of an `operator` call is still a `PyObject`, to preserve arbitrary objects that Python custom operators may return. `===` / `!==` and conditional branches are exceptions: the Python bool results of `operator.is_/is_not/truth()` are subsequently converted through the explicit phpy conversion entry to a TypePHP `bool`. Both operands must be strictly evaluated once each from left to right, and the conversion process must not cause the expression to be executed repeatedly.

When phpy runs as an ordinary PHP extension, it can continue to use Zend opcode handlers to provide operator overloading compatibility; TypePHP does not depend on these handlers.

Dynamic ZendVM code has one explicitly reserved limitation: Zend compiles `-$value` / `+$value` into multiplication by `-1` / `1`, so phpy's opcode handler can no longer recognize the unary operation in the source. Therefore dynamic code retains the `$value * -1` / `$value * 1` protocol behavior and does not rewrite ordinary PHP code through a global AST hook; when a custom Python object's `__neg__()` / `__pos__()` differs from its `__mul__()`, the result may differ. TypePHP AOT still generates `operator.neg()` / `operator.pos()` according to the table above. The external user document `python.md` has already stated this limitation.

When the TypePHP compiler recognizes static types such as `PyObject`, `PyDict`, and other phpy objects, it rewrites operators into ordinary Python module callable calls:

```text
TypePHP operator
    -> compile-time lowering
    -> implicit python\operator module binding
    -> operator\add/sub/... dynamic call
    -> CPython complete operator protocol
```

This abstraction does not directly link phpy and does not go through phpy's user opcode handler, but it is not a zero-cost C++ inline operation:

- `zend_function*` and class entries use the existing func/class map lazy cache.
- Arguments still need to be constructed as Zend values and converted by phpy into Python objects.
- Python module member lookup, GIL, CPython call, and reference counting costs still exist.
- The advantage is that the TypePHP binary only depends on ZendVM/PHPX, and phpy can be a truly optional runtime extension.

Using the standard library `operator.add()` instead of directly calling `__add__()` reuses CPython's complete rules for `NotImplemented`, `__radd__()`, right-operand subclass priority, and so on; TypePHP does not implement reflected-operation fallback.

The current implementation already covers binary arithmetic and bitwise operations, comparison, identity, unary operations, conditional truthiness, short-circuit logic, and compound assignment for variable, attribute, and subscript lvalues. The results of Python module functions/properties, builtins, dynamic methods, attributes, subscripts, and callables all continue to propagate the `PyObject` static type, so they can be chained or participate in subsequent Python operations.

## 16. Exceptions

When a Python call fails, a unified TypePHP exception type is thrown, tentatively:

```php
PyError
```

The exception preserves at least:

- The Python exception type.
- The message.
- The Python traceback object.
- The formatted traceback string.
- The original Python exception instance.

Example:

```php
try {
    np\array('invalid')->reshape(2, 2);
} catch (PyError $error) {
    echo $error->pythonType();
    echo $error->pythonTraceback();
}
```

When Python synchronously calls a TypePHP callable proxy and TypePHP throws an exception, it should be converted into an ordinary Python exception, preserving the original TypePHP class name and message. That exception only propagates along the current dynamic call stack; registering a `typephp` Python module or a dedicated global exception type is not required.

After an exception crosses a VM, the source VM's pending exception state must be cleared. Any exception conversion failure must not cause a coredump, duplicate throw, or leftover error state.

## 17. Passing TypePHP Callables to Python

TypePHP functions, closures, and callable objects can be automatically wrapped as Python callables:

```php
$values = python\list([1, 2, 3]);
$result = python\map(fn (int $value): int => $value * 2, $values);
```

When Python calls the proxy:

1. Python arguments are converted or wrapped into TypePHP values according to boundary rules.
2. Enter the ZendVM to call the callable.
3. The return value is converted into a Python value.
4. TypePHP exceptions are converted into Python exceptions.

A closure proxy must hold the Zend callable to prevent it from being released while Python still references it. Cross-VM reference cycles must be explicitly detected by the runtime or provided with a predictable collection policy.

A TypePHP callable proxy is only an argument value, not an export mechanism: only after TypePHP actively passes the proxy to Python can Python dynamically call it during the object's lifetime. TypePHP does not generate a module that Python can independently import, nor register global functions or classes.

## 18. phpy Lifecycle and Integration

TypePHP reuses phpy's own PHP extension entry points and lifecycle, and does not add an independent CPython bootstrap:

1. phpy's `MINIT` initializes the shared runtime, CPython, and the `PyObject`, `PyDict`, and other Zend classes.
2. phpy's `RINIT` establishes the state needed for the current request.
3. During the request, the TypePHP program dynamically calls Python through the internal classes, methods, and object handlers that phpy registers with the ZendVM.
4. phpy's `RSHUTDOWN` releases request-level resources and proxies.
5. phpy's `MSHUTDOWN` shuts down the shared runtime and CPython after all proxies have been safely released.

TypePHP should execute these entry points through the same mechanism as other statically or dynamically linked PHP extensions; it must not reinitialize CPython, and must not bypass phpy's lifecycle to directly call `Py_Initialize()` or `Py_Finalize()`.

The only artifact is the main program or library with TypePHP as its entry point. No `.so` / `.pyd` importable by CPython is generated, no TypePHP modules, functions, or classes are registered with Python, and there is no `#[PythonExport]`.

## 19. Performance Principles

- Passing a `PyObject` only adds the necessary reference count and does not copy the Python object.
- `pythonModuleMap` only caches the `PyModule` Zend object zval that has already been bound; real loading and global identity directly reuse CPython `sys.modules`; builtin/member lookup remains simple in the first version, and a dedicated cache is only designed when benchmarks prove it necessary.
- Arguments should directly construct the array required by vectorcall, preferring the CPython vectorcall API.
- Avoid first constructing a PHP array and then having phpy convert it a second time into a Python tuple/dict.
- Converting a TypePHP array to a Python container is an explicit O(n) conversion and is not claimed to be zero-cost.
- TypePHP arrays and strings entering hot Python calls should be promoted to reusable `PyList`, `PyDict`, `PyStr`; the compiler does not cache conversion results on its own, because the original TypePHP value may have changed.
- The GIL guard should cover the minimum necessary region; correct interpreter state must be maintained during single-threaded synchronous reentrancy.
- Exception paths and normal paths must be equally tested for reference counting and memory leaks.

## 20. Permanent Boundaries and Unsupported Capabilities

- Python threads, including threads created by `threading` and any call entering the phpy/TypePHP bridge from a non-main thread.
- `asyncio`, Python coroutines, `async`/`await`, and cross-language event loop scheduling.
- CPython subinterpreters and the per-interpreter GIL mode.
- Python as the entry point independently loading a TypePHP program.
- Generating a Python extension or registering TypePHP functions, classes, or objects as importable Python modules.
- Runtime reflection generating TypePHP static types.
- Automatic import of `from module import *`.
- pickle/serialization of Python objects.
- Passing `PyObject` across processes.
- Python interop in the WASM target.

Forbidden capabilities must have explicit guardrails: the compiler gives a FatalError for `threading`, `_thread`, `asyncio`, and subinterpreter APIs that can be statically recognized; phpy records the owner thread that created the runtime and refuses to enter the ZendVM bridge from other threads. Dynamic imports, reflection, or third-party packages cannot be fully recognized by the compiler, so runtime checks cannot be omitted.

Computation threads inside third-party native packages that are completely closed and never enter the CPython API or the phpy/ZendVM bridge do not belong to the Python thread capability here; they are invisible to TypePHP and must not produce cross-thread callbacks.

## 21. TDD and Test Gates

The implementation and refactoring of this project must strictly follow TDD; the order must not be reversed:

1. Write tests based on the confirmed design semantics.
2. Run the tests and confirm that they fail because the target capability is not yet implemented or an existing bug exists.
3. Write the minimal implementation that makes the tests pass.
4. Run the relevant tests and the full regression.
5. Refactor, clean up, and optimize under test protection.
6. Run the full regression, memory checks, and coverage checks again.

It is forbidden to first complete the implementation and then add tests that only verify the current implementation details. Every bug must first have a regression test that reliably reproduces the problem.

### 21.1 Three-Layer Mandatory Testing

#### PHPUnit

The TypePHP repository's PHPUnit is used to verify the compiler itself:

- Python import and special name resolution.
- AST, symbol table, and type inference.
- C++ code generation.
- Compile-time errors and diagnostic locations.
- Compile-time diagnostics for permanently disabled capabilities, and successful code generation in environments without phpy.
- Boundary logic that does not require starting CPython.

The phpy repository's existing PHPUnit is used to verify the ZendVM/PHP Facade and the shared Runtime:

- Public PHP APIs such as `PyCore`, `PyObject`, and `PyDict`.
- Conversion between PHP values and Python objects.
- Mapping of Python exceptions to `PyError`.
- That the opcode handler and the Zend dynamic-call API used by TypePHP have consistent semantics.
- The object-retention call paths that TypePHP needs.
- GIL, reference counting, destruction, and exception paths.

#### PHPT

Used to verify end-to-end behavior of the language and runtime from the TypePHP user's perspective:

- Import, `module\name` package variable reads, `module\name()` callable calls, and keyword arguments.
- That `use python\module as alias` and hand-written `$alias = PyCore::import('module')` are equivalent in result, exception, and object identity.
- Multiple aliases, nested modules, and repeated imports across `.cc` files.
- When there is only `use python\module` without accessing any related symbol, no helper is generated, no import is called, and the existence of that Python module is not checked.
- The same fully qualified module name is allocated only one ID across functions and across `.cc` files, and the import API is only called on first access.
- On import failure, the map slot stays `UNDEF`; after the exception is caught, the next access can retry.
- Request clean performs `zval_ptr_dtor()` on each module zval and restores it to `UNDEF`; valid Zend objects must not be directly `memset`.
- Deleting or replacing `sys.modules` entries does not change already-cached TypePHP module bindings.
- Compile-time FatalError for the legacy `module::name` / `module::$name` class member syntax, and runtime exceptions for nonexistent members and non-callable members.
- Attributes, subscripts, iteration, operators, and truthiness.
- Conversion from TypePHP arguments to Python, and explicit conversion of Python return values.
- Empty TypePHP arrays default to a Python list, and array recursive deep copying, exception abort, and repeated conversion behavior.
- Python builtins, module functions, methods, and operation results do not implicitly become TypePHP scalars.
- Explicit boundaries such as `$obj->toValue()->toInt()`, `$obj->toArray()`, `python\scalar($obj)->toInt()`, and the subsequent ordinary TypePHP conversions that restore static types and operation rules.
- Conversion from Python exceptions to TypePHP exceptions.
- When phpy is not loaded, the first Python call throws a PHP `Error`, while a merely declared unused Python `use` does not error.
- TypePHP callables being called back by Python.
- Reference counting, object destruction, and repeated calls.
- The real output of compiled programs, not just checking the generated code string.

#### pytest

pytest is used for regression testing of phpy's own existing Python-facing bridge; it does not mean TypePHP will generate a Python extension. It needs to verify:

- Python calling PHP functions, objects, and callables.
- Synchronous reentrancy and the phpy module lifecycle.
- Python's holding, release, and exception mapping of Zend callable/object proxies.
- The guard that permanently forbids entering the ZendVM from Python threads.

The three layers of testing cannot replace each other. C++/GoogleTest can cover phpy's internal reference counting, RAII, and low-level conversion, but cannot replace PHPUnit, PHPT, or pytest.

### 21.2 Test Matrix for Each Semantic

Each supported capability should at least consider the following dimensions:

- The normal path.
- Error types and error messages.
- Boundary values and empty values.
- Python subclasses and dynamic protocols.
- TypePHP → Python → TypePHP reentrancy.
- Python → TypePHP → Python synchronous reentrancy initiated only by TypePHP and occurring through callable proxies.
- Normal destruction and exceptional destruction.
- Repeated execution, `sys.modules` identity, and repeated import not re-executing module code.
- Debug, Release, and supported platforms.

Conversion tests must include:

- `PHP_INT_MIN/PHP_INT_MAX` and out-of-range Python ints.
- `NaN`, `INF`, `-INF`, and negative zero.
- Empty strings, Unicode, invalid UTF-8, and bytes containing NUL.
- Empty list/dict, mixed keys, deep containers, recursive containers, and cyclic references.
- Identity of the same Python object after multiple wrappings.

### 21.3 Memory and Stability Tests

Modifications involving `PyObject*` or `zval` ownership must, in addition to functional tests, also perform:

- PHP memory leak report.
- Python debug build/refcount checks (when the environment is available).
- ASan/UBSan builds.
- Exception injection tests covering every branch that can return early.
- Stress tests that cyclically create and destroy objects.
- Tests where cross-VM proxy objects still exist at process exit.

Coredumps, leaks, or uncleaned pending exceptions must not be marked as "expected behavior" to bypass tests.

### 21.4 Coverage Requirements

- Every normative behavior in the design document must correspond to at least one test.
- New and modified bridge code needs to cover both normal and error branches.
- Overall project coverage must not decrease because of this feature.
- For GIL, reference counting, exception, and destruction code, line coverage alone is not sufficient; the branch matrix must be manually inspected.
- The final coding plan must first list the test checklist, then the implementation tasks.

## 22. Confirmed and Pending Questions

Confirmed:

1. Python interop is an optional extension-level feature; TypePHP does not link or compile-time check `libphpy.so`, and if phpy is not loaded at the first actual call, Zend throws a PHP `Error`.
2. TypePHP adopts explicit conversion as much as possible and does not inherit all of phpy's implicit conversion behavior.
3. TypePHP operators are rewritten at compile time into Python standard library calls of the form `operator\add($left, $right)`, without using phpy opcode handlers and without generating phpy C++ symbol calls.
4. The `python` root namespace is case-insensitive; all Python symbols after it are case-sensitive.
5. `python` is a special language namespace handled by the compiler.
6. Runtime classes continue to use phpy's public names such as `PyObject` and `PyDict`.
7. Construction syntax such as `python\dict()` is syntactic sugar for existing phpy class constructors; `python\print()` and the like are syntactic sugar for `PyCore` APIs.
8. `new PyList()` and `python\list()` have the same `PyList` typed object type and optimization capability.
9. phpy solves runtime concerns; TypePHP only dynamically calls the phpy Facade through cached `zend_function*` and the PHPX/Zend generic object API.
10. TypePHP's Python-specific implementation and tests are placed in independent subdirectories and integrated into the general compilation flow through controlled entry points.
11. Python threads, `asyncio`, and subinterpreters are permanently forbidden and are not future compatibility targets.
12. `===` / `!==` map to Python identity `is` / `is not` respectively; `==` / `!=` use Python value comparison.
13. Only TypePHP actively calling Python is supported; no Python extension is generated, no `#[PythonExport]` is provided, and no TypePHP symbols are registered with Python.
14. The CPython and bridge lifecycle fully reuses phpy's `MINIT/RINIT/RSHUTDOWN/MSHUTDOWN` entry points.
15. Python package variables are read using the PHP namespace constant syntax `math\pi`, but at runtime a dynamic Python attribute lookup is performed; `math::pi` and `math::$pi` are incorrect class member expressions.
16. `np\array()` means reading and calling a Python package member; that member can be a function, a class, or another callable, and the specific type is determined by the Python runtime.
17. `PyObject` can be mixed with TypePHP values in operations; after TypePHP operands are converted to Python objects, the entire operation is executed by the CPython protocol, and the result remains a `PyObject`.
18. The results of Python functions, methods, class construction, and builtin calls all remain `PyObject` or known phpy subclasses; phpy return value implicit conversion is disabled.
19. `PyObject::toValue()` is an explicit scalar/container conversion method, equivalent to `python\scalar()`; `PyObject::toArray()` only accepts convertible containers and iterators, and unsupported types return an empty array. After conversion, ordinary TypePHP conversion can continue to be used, for example `$obj->toValue()->toInt()`.
20. When TypePHP calls Python, all arguments are automatically converted to Python types; TypePHP arrays are recursively deep-copied, and empty arrays default to a Python list.
21. Performance-sensitive code should reuse proxy objects such as `PyDict`, `PyList`, and `PyStr`, avoiding repeated conversion and deep copying of the same TypePHP value.
22. TypePHP's main language increment is `use python\...` and module aliases; when using aliases, phpy import is called through a lazy indexed map of the same kind as `funcMap`, and other runtime capabilities preferentially reuse phpy directly.
23. `use python\module` is handled entirely by PHP namespace resolution; when the current `.php` file does not actually access symbols that resolve to that module, no helper is generated and no runtime import is performed.
24. An ID is only allocated for a fully qualified module name when `module\attr` or `module\func()` is found; unused `use` does not occupy a map slot and does not perform import.
25. `pythonModuleMap`, like `funcMap`, is declared centrally and looked up lazily by ID; the difference is that modules are stored as owning-reference Zend object zvals and must be `zval_ptr_dtor()`-ed one by one during request clean and restored to `UNDEF`.
26. `sys.modules` is responsible for global load state and identity; `pythonModuleMap` only represents the module bindings that TypePHP has completed and cached.
27. TypePHP generated code only depends on PHPX/ZendVM; `PyCore::import()`, builtins, object methods, and conversions are all resolved as `zend_function*` dynamic calls.
28. Python operators implicitly use the `python\operator` module; the complete operator protocol is handled by CPython `operator` functions, without directly calling dunders and without TypePHP implementing reflected fallback.

Module namespace attributes are read-only in the first version. A PHP namespace constant expression cannot be an assignment target; if write capability is added later, an explicit API should be used, and tests should be added first after the semantics are determined.

# Python Tools Submodule

TypePHP integrates the Python IDE helper generator and the Python source converter into `tpc`. Both are located in an independent
`src/PythonTools` directory, only reusing the `tpc` command entry point, and do not enter the normal PHP preprocessing, C++ generation, and compilation pipeline.

## Python namespace IDE helper

```shell
./tpc --gen-python-helper math
./tpc --gen-python-helper numpy.linalg
./tpc --gen-python-helper numpy --output-dir .ide-helper
```

The command imports the specified Python module through PHPy, and uses the Python `inspect` API to collect functions, parameters, classes, methods, and
module attributes. The PHPy extension and the target Python module must be installed in the host environment where `tpc` is executed.

By default, generated files are located in `ide-helper` in the current directory. `--output-dir` can replace this output root directory, supporting both
paths relative to the current directory and absolute paths:

```text
ide-helper/python/math.php
ide-helper/python/numpy/linalg.php
ide-helper/python.php
ide-helper/PyObject.php
```

Each time a module helper is generated, the Python `builtins` are also scanned and the root namespace file
`python.php` is generated, providing IDE completion for builtin symbols such as `python\tuple()` and `python\len()`. This file is
regenerated according to the current Python environment.

When a module helper is first generated, the common `PyObject.php` is also generated. It contains method hints for dynamic access, calls,
array access, iteration, and `toArray()`, `toValue()` of `PyObject`, shared by all Python module helpers. If this file
already exists, the generator keeps the original file and does not overwrite it.

Generated content uses TypePHP's module-as-namespace form, e.g. `python\math\sqrt()`, and is compatible with the IDE name resolution of ordinary
`use`, `use function`, and `use const`. The end of the file contains `die`, which is used to explicitly
terminate the program when executed by mistake. Helpers can only be handed to the IDE for indexing; they cannot be included, nor added to a TypePHP project's sources or compilation inputs.

`PyObject::IDE_HELPER_ONLY` is a hint constant shared by all helpers. The method bodies of non-`void` stubs use
`die(\PyObject::IDE_HELPER_ONLY)` to satisfy the IDE's control-flow checks on return types, no longer producing a "missing return
statement" diagnostic. Module attributes use namespace `const` declarations, supporting the IDE's constant completion and `use const`.
PHP 8.1 and above allow `new` in constant initializer expressions. Module attributes therefore directly use an IDE-analysis-only
`PyObject` instance as a placeholder value:

```php
const pi = new \PyObject();
```

This way the IDE precisely recognizes the constant as `PyObject`, instead of inferring a wrong type from `null`.

The common `PyObject` helper also declares TypePHP's virtual keyword methods, including `toInt()`, `toFloat()`,
`toString()`, `toBool()`, `toStream()`, high-precision type conversions, `toObject()`, `toAny()`, and `toRef()`.
These declarations are only used for IDE completion; calls are expanded at compile time and are not entity methods of the PHPy `PyObject` runtime class.
`toArray()` and `toValue()` are still real methods provided by PHPy.

Python class constructors explicitly call `parent::__construct()`. If a Python object defines `count()`, the helper
does not declare it again, because `PyObject::count(): int` is already used for PHP `Countable`. When the Python object's own
`count()` needs to be called, it should be written explicitly as `$object->__call('count', $arguments)`.

PHP function/class names are case-insensitive, while Python names are case-sensitive; PHP reserved words also cannot be declared as ordinary
stub symbols. The generator reports symbols that cannot be expressed with legal PHP declarations as comments, and does not rename the Python API on its own.
The call syntax of `python\print()` is legal, but PHP forbids declaring a function named `print`, so a plain
PHP helper file cannot provide a syntax-error-free symbol declaration for it. Reserved words such as `list`, `int`, and `float` have
the same limitation.

## Python to TypePHP

```shell
./tpc --convert-python-to-php script.py > script.php
```

The converter invokes `python3` in the PATH to parse the Python AST, then outputs PHP source using the TypePHP Python namespace
syntax. Ordinary module imports are converted to namespace imports:

```python
import math
print(math.sqrt(16))
```

```php
use python\math;

function main(): void
{
    python\print(math\sqrt(16));
}
```

Currently it supports ordinary imports, functions, assignments, calls, container literals, basic operations, single comparisons, if/while/for,
lambda, and basic f-strings. Module top-level variables are converted to PHP globals to preserve the ability of functions to read module variables.
When semantics can be strictly preserved, the converter directly uses PHP native syntax: `print()` with no arguments or safely convertible arguments
generates `echo` with a newline, and `sys.exit()` with integer literal exit codes generates `exit`. `print()` with
`sep`, `end`, `file`, or `flush` arguments, and `sys.exit()` with strings or objects,
are not fully consistent with PHP behavior and remain as Python calls.

The converter follows the principle of "reject when semantics cannot be reliably preserved". Not-yet-completed syntax such as class, async, generator, try/with, decorator,
destructuring assignment, chained comparison, nested functions, and loop-else throws an error with the
source file and line number, rather than generating PHP code that looks usable but is semantically wrong.

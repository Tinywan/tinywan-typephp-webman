# py2php: Python → TypePHP source conversion tool

## Usage

```bash
./bin/tpc.php --convert-python-to-php examples/python/version.py > examples/python/version.php
```

The generated PHP source is written to stdout, errors to stderr, with exit code 0 for success / 1 for failure.

## Architecture

```
.py source
  └─ PythonAstLoader      python3 subprocess (ast module) → JSON AST
       └─ PythonToTypePhpConverter   AST → TypePHP source string
            └─ Command::execute      CLI dispatch (--convert-python-to-php)
```

- Source: `src/PythonTools/Command.php`, `src/PythonTools/Converter/`
- Unsupported syntax throws `RuntimeException("{file}:{line}: unsupported Python syntax {node type}[: details]")`, which the CLI layer converts to stderr + exit code 1.
- Tests: `phpunit/src/PythonTools/` (`PythonToTypePhpConverterTest`, `PythonAstLoaderTest`, `PythonToolsCommandTest`), corresponding item by item to this document.

## Statement support matrix

| Python syntax | Status | Conversion rule / error |
|---|---|---|
| `x = expr` | ✅ | `$x = expr;`, module-level variables are automatically injected as `global` |
| `x = y = 1` (chained assignment) | ✅ | `$x = $y = 1;` (name targets only; errors on property/subscript targets) |
| `x += expr` and other augmented assignments | ✅ | supports the `+ - * / % ** << >> \| ^ &` families; `//=` `@=` expand to `python\operator\floordiv/matmul($x, ...)` calls |
| `x: int = expr` | ✅ | annotation ignored, converted to an ordinary assignment |
| `x: int` (annotation only) | ✅ | converted to comment `// annotation-only declaration: x`, not registered as a module global |
| `a, b = x` (destructuring) | ✅ | `[$a, $b] = $x->toArray();` (PyObject converted to PHP array then destructured; elements may be names/properties/subscripts. Nested destructuring, star destructuring `a, *b = x`, and chained destructuring are not supported. Element-count mismatches fill `null` per PHP semantics rather than raising Python's ValueError) |
| `def f(...)` | ✅ | see "Function signatures"; a function named `main` is renamed to `main_` (to avoid conflict with the TypePHP entry point), and call sites are rewritten accordingly |
| nested `def` | ❌ | `FunctionDef: nested functions require Python closure scope analysis` |
| `@decorator` | ✅ | see "Function decorators" |
| `return [expr]` | ✅ | `return [expr];` |
| `if / elif / else` | ✅ | isomorphic conversion |
| `while` | ✅ | isomorphic conversion; `while/else` is not supported |
| `for i in iter` | ✅ | `foreach (iter as $i)`; `for/else` and tuple targets are not supported |
| `break` / `continue` / `pass` | ✅ | `pass` → `// pass` comment |
| `global x` | ✅ | `global $x;` (when combined with the auto-injected global it appears twice — redundant but valid, a known behavior) |
| `del x` / `del o.a` / `del d[k]` | ✅ | `unset(...)`; `del (a, b)` tuple/list targets expanded item by item; invalid del targets (such as `del f()`) are rejected first by the Python parser |
| module-level string literal (docstring) | ✅ | converted to `/** ... */` comment (`*/` escaped as `* /`) |
| `import a.b` | ✅ | `use python\a;` (only the first segment as the alias, see "Known behaviors") |
| `import a.b as x` | ✅ | `use python\a\b as x;` (`as` omitted when the alias equals the last segment) |
| `from m import f [as g]` | ✅ | call sites mapped to `python\m\f(...)` |
| `from . import m` | ❌ | `ImportFrom: relative imports are not supported yet` |
| `from m import *` | ❌ | `ImportFrom: star imports are not supported` |
| `class` | ❌ | `ClassDef` |
| `with` | ❌ | `With` |
| `raise` / `try` / `assert` | ❌ | `Raise` / `Try` / `Assert` |
| `async def` / `await` | ❌ | `AsyncFunctionDef` (`await` unreachable, outer level errors first) |
| `match` | ❌ | `Match` |
| `nonlocal` | ❌ | `Nonlocal` |

## Function signatures

| Python form | Status | TypePHP output |
|---|---|---|
| `def f(x, y=4)` | ✅ | `function f($x, $y = 4)` |
| `def f(a, *, b)` | ✅ | `function f($a, $b = null)` (keyword-only parameters without defaults are padded with `null`) |
| `def f(*args)` / `def f(**kw)` | ✅ | `function f(...$args)` |
| `def f(*a, **kw)` | ❌ | `FunctionDef: simultaneous *args and **kwargs cannot be represented by one PHP signature` |
| `lambda a, b=2: a + b` | ✅ | `fn ($a, $b = 2) => $a + $b` |

## Expression support matrix

| Python syntax | Status | Conversion rule / error |
|---|---|---|
| literals `int / float / str / True / False / None` | ✅ | `var_export`; `None` → `null` |
| `b'...'` bytes | ❌ | `{file}: Python bytes literals are not supported yet` (no line number) |
| `1j` complex | ❌ | `{file}: Python complex literals are not supported yet` (no line number) |
| variable names | ✅ | `$name`; `this` escaped as `$this_` |
| module alias as a value | ❌ | `a Python module cannot be used as a first-class value in TypePHP namespace syntax` |
| attribute chain `o.a.b` | ✅ | `$o->a->b`; for module alias chains only the first segment is a module member: `sys.version_info.major` → `sys\version_info->major` |
| module attribute assignment/deletion | ❌ | `Attribute: Python module attributes cannot be assigned or deleted` |
| function call | ✅ | defined functions connect directly `f(...)`; built-ins mapped `python\len(...)`; `from m import f` mapped `python\m\f(...)`; other names callable as variables `$f(...)` |
| keyword arguments / `*args` / `**kwargs` calls | ✅ | `f(x: 1, ...$args)` |
| container literals `[] () {} {:}` | ✅ | `python\list/tuple/set/dict([...])`, supports `...` unpacking |
| binary operators `+ - * / % ** << >> \| ^ &` | ✅ | isomorphic conversion |
| `//` floor division / `@` matrix multiplication | ✅ | `python\operator\floordiv(a, b)` / `python\operator\matmul(a, b)` |
| unary operators `- + not ~` | ✅ | `- + ! ~` |
| comparisons `== != < <= > >=` | ✅ | isomorphic conversion |
| `is` / `is not` | ✅ | `===` / `!==` |
| `in` / `not in` | ✅ | `python\operator\contains(b, a)` (arguments swapped) / negated |
| chained comparison `a < b < c` | ❌ | `Compare: chained comparisons require explicit temporary variables` |
| `a and b` / `a or b` | ❌ | `BoolOp` |
| `x if c else y` | ✅ | `(c ? x : y)` |
| subscript `a[i]` / slice `a[l:u:s]` | ✅ | `$a[$i]` / `$a[python\slice(l, u, s)]` (defaults to `null`) |
| f-string | ✅ | concatenation + `->toString()`; operator-precedence-sensitive expressions are parenthesized as a whole |
| f-string `!r` conversion / `:03d` format spec | ❌ | `FormattedValue: formatted f-string conversions are not supported yet` |
| walrus `:=` | ✅ | assignment within expression `($n = 10)` |
| comprehensions / generator expressions | ❌ | `ListComp` / `SetComp` / `DictComp` / `GeneratorExp` |
| `yield` / `yield from` | ❌ | `Yield` / `YieldFrom` |

## Function decorators

Decorators rebind the function to the same-named module variable at the start of `main()` (before other top-level statements), bottom-up per Python semantics:

```python
@a
@b
def greet(): ...
```

```php
function greet() { ... }

function main(): void
{
    global $greet;
    $greet = b('greet');
    $greet = a('greet');
    ...
}
```

- A decorator can be a defined function, a `from m import f` imported symbol, a module attribute, or a decorator factory (`@dec('x')` → `$greet = dec('x')('greet');`)
- The decorated function name is registered as a module global, and all call sites (including inside other function bodies) call the decorated result indirectly via `global` + variable: `$greet()`
- Recursive calls inside the decorated function body also resolve to the decorated variable, consistent with Python semantics

## print / sys.exit degradation rules

Degrade to native statements only when PHP behavior is fully identical to Python:

| Form | Output |
|---|---|
| `print()` | `echo "\n";` |
| `print("a", "b")` (string/integer constants, module attributes, containers, f-strings) | `echo 'a', ' ', 'b', "\n";` |
| `print(1.5)`, `print(True)`, `print(x, sep=...)` | not degraded: `python\print(...)` |
| after user-defined/imported/assigned shadowing of `print` | not degraded |
| `sys.exit()` / `sys.exit(2)` (including the `from sys import exit` form) | `exit;` / `exit(2);` |
| `sys.exit("fail")` | not degraded: `sys\exit('fail');` |

## Known behaviors (not errors, but worth noting)

1. `import os.path` (no alias) only introduces the first segment `use python\os;`.
2. An explicit `global x` inside a function and the auto-injected `global x` for a module global appear twice (valid PHP).
3. Writing `print = str` (assigning a built-in name to a variable) treats the right side as a variable (`$print = $str;`), not as built-in name resolution.
4. Errors for bytes/complex literals have no line number (constants are encoded during the AST load stage; position information is not passed through).
5. Decorator rebinding uniformly happens at the start of `main()`, slightly differing from Python's exact "decorated at the def site" position; if a decorator expression depends on assignments later in the top-level statements, the evaluation timing may differ.
6. Decorated function names are registered as module globals, so the name appears in every function's auto-injected `global` list (redundant but valid).

## Running tests

```bash
vendor/bin/phpunit --filter 'PythonToTypePhpConverterTest|PythonAstLoaderTest|PythonToolsCommandTest'
```

Converter tests depend on a real `python3` to parse the AST, and are skipped automatically when the environment lacks it.

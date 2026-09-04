# TypePHP Python Interop Phased Implementation Plan

> This plan takes `python/design.md` as its specification. Each phase strictly follows: first add PHPUnit/PHPT/pytest tests and confirm they fail, then implement, then run the relevant tests and the full regression.

## Phase 1: Python module names, use, and lazy binding

The goal is to complete a minimal runnable closed loop, without implementing operators and general conversion:

1. Recognize `python\module\member()` / `python\module\member` in the global namespace, `\python\module\member()` / `\python\module\member` in other namespaces, the optional `use python\module` shorthand, case-insensitive root names, and case-sensitive Python subsequent names.
2. Do not build a Python-specific alias table, and do not specially handle `use`; use PHP's ordinary namespace, `use function`, `use const`, `as` alias, conflict checking, and fully qualified name resolution. Inside a namespace it must be written as `\python\module`; the relative `python\module` still resolves to a name under the current PHP namespace.
3. Allocate a module ID only when `python\module\attr`, `python\module\func()`, or their alias forms appear.
4. Generate a `pythonModuleMap` of the same kind as `funcMap`, a lazy getter, and request-clean code.
5. Dynamically call `PyCore::import()` using the Zend class/function map; do not include, link, or detect phpy.
6. Use the Zend object API to read module attributes and call module callables.
7. When phpy is not loaded, throw a PHP `Error` at the first actual use; an unused Python use does not trigger an error.

Test order: PHPUnit code generation and diagnostic tests → PHPT runtime tests → existing compiler regression.

Implementation status: completed. Fully qualified names and any alias share the same runtime slot per Python dotted module name; fully qualified names do not require `use`, and both syntaxes lazy-import on first actual execution. Before recognition, PHP namespace resolution is strictly applied: for example, in `namespace App`, the relative name `python\math\sqrt()` is an ordinary `App\python\math\sqrt()`, and only `\python\math\sqrt()` points to the Python root namespace.

## Phase 2: builtins, construction syntactic sugar, and static types

1. `python\name()` is dynamically called through the phpy Zend Facade: explicit `PyCore` methods are reused directly, and other names go through a Python `builtins` module lookup.
2. `python\list/dict/tuple/set/str/object()` maps to existing phpy Zend classes or methods.
3. `new PyList()` and `python\list()` and the like obtain the same logical static type.
4. Python call results remain `PyObject` or known phpy subclasses, turning off implicit scalar conversion on the TypePHP path.
5. Tests for missing phpy, nonexistent builtins, argument errors, and exception mapping.

Implementation status: completed. TypePHP lazily enables phpy's `return_as_object` the first time a Python expression is actually executed; merely declaring unused Python symbols still does not trigger a runtime dependency.

## Phase 3: argument conversion and explicit result conversion

1. TypePHP arguments are automatically converted to Python values after left-to-right evaluation.
2. Conversion of scalars, arrays, empty arrays, nested containers, and TypePHP callables.
3. Leave the Python object rules via `$py->toValue()` or `python\scalar($py)`; when a native type is needed, continue to use ordinary TypePHP conversion, e.g. `$py->toValue()->toInt()`. Containers and iterators can directly use `$py->toArray()`.
4. Tests for deep copying, recursive containers, overflow, Unicode/bytes, and exception paths.
5. Review and refactor phpy's conversion strategy, removing global temporary conversion state that affects synchronous reentrancy.

Implementation status: the core boundary is complete. TypePHP arguments are strictly evaluated left to right, supporting scalars, empty arrays, nested list/dict, and callables; `PyObject::toValue()` and `python\scalar()` reuse phpy's explicit conversion entry point, and `PyObject::toArray()` converts supported containers and iterators. phpy has removed the process-level conversion function pointer, replacing it with a local stateful converter, RAII recursion protection, and a 128-level depth limit, and covers invalid UTF-8, PHP self-referential arrays, and Python cyclic container error paths. The final language mapping for Python big integers and bytes remains in this phase's follow-up work.

## Phase 4: operators

1. Rewrite operators into dynamic calls to the Python standard library `operator` module.
2. Mixed operands are first converted to `PyObject`.
3. Strictly guarantee left-to-right evaluation, each evaluated once.
4. Use `operator.is_/is_not/truth` to implement identity and truthiness, and `iadd/isub/...` to implement compound assignment.
5. Verify that `operator` automatically handles `NotImplemented`, reflected dunders, and subclass priority.
6. Against phpy's opcode-handler behavior, fix existing issues such as `/` incorrectly mapping to floor division.

Implementation status: completed. Binary arithmetic, bitwise operations, comparison, `===`/`!==`, unary operations, conditional truthiness, short-circuit logic, and compound assignment are all executed through the implicit `operator` module binding; `/` uses `truediv`. Mixed TypePHP operands are converted by phpy at the call boundary, results continue to remain `PyObject`, and comparison and truthiness results explicitly converge to a TypePHP `bool`. Attribute and subscript lvalues are written back through the dynamic write protocol of Phase 5.

## Phase 5: the complete object protocol

1. Reading, writing, and deleting Python object attributes.
2. Subscript read/write, delete, and `isset()`.
3. iterator/foreach.
4. Synchronous reentrancy of Python callables and TypePHP callable proxies.
5. keyword arguments, argument unpacking, and error semantics.

Implementation status: completed. Dynamic attributes, unknown methods, subscripts, deletion, `isset()`, `foreach`, and callables of Python proxies all reuse phpy's Zend object protocol, without generating phpy C++ symbols. Method, attribute, subscript, and callable results continue to propagate as `PyObject`, supporting chained access and subsequent Python operations. Named arguments and unpacking reuse the unified call argument pipeline and keep left-to-right evaluation; attribute and subscript compound assignments use the returned object of `operator.i*()` to write back the original lvalue.

phpy simultaneously completed object protocol hardening: `__set()` conversion reference release, `__unset()`, list/tuple negative indexing, list deletion, `isset()` semantics for missing keys and Python `None`, deletion and contains state checks, and iterator/count exception propagation. The related bugs are independently covered by phpy PHPUnit and TypePHP PHPT.

## Phase 6: phpy stability and performance finishing

1. Full audit of the CPython/ZendVM lifecycle, GIL, and owned/borrowed/stolen references.
2. Audit of Python/Zend exception state and traceback.
3. Cross-VM reference cycles, destruction, and exception injection tests.
4. ASan/UBSan, PHP leak report, Python debug build, and stress tests.
5. Benchmark dynamic Zend calls, the module map, argument conversion, and `operator` module calls; only optimize hotspots proven by data.
6. Full PHPUnit, pytest, PHPT, and existing TypePHP compiler regression.

Implementation status: completed. The first round of CPython failure-path audits covered the construction and subscript writes of general objects, list, dict, tuple, and set, as well as sequence/set `contains()`. PHP-to-Python key/value conversion failures are now immediately mapped to `PyError`, all newly acquired references are released by scope guards; construction failure no longer leaves an unhandled CPython error indicator, and the `-1` error result of `contains()` is no longer misjudged as `true`. Paths such as invalid UTF-8, unhashable set members, and containers remaining usable after failure have phpy PHPUnit regression tests.

The second round covered module import, exception conversion, callable checks, and the explicit iterator API. New references transferred to the caller by `PyImport_ImportModule()`, `PyErr_Fetch()`, and `PyIter_Next()` are now uniformly released after the Zend wrapper acquires an independent reference; repeated imports, Python exceptions, or explicit iterator next no longer continuously increase the reference count. Calling a non-callable Python attribute or `PyObject` reliably throws `PyError(TypeError)`, no longer silently returning `null` because `PyCallable_Check()` did not set an error indicator. `PyCore::next()` also distinguishes normal end-of-iteration from iterator exceptions. All the above paths first establish failing phpy PHPUnit regression tests, with object call behavior additionally covered by TypePHP PHPT integration.

The third round covered conversion failures and function caching of the `PyCore` Facade. `PyCore::eval()` throws a `PyError` immediately after globals conversion fails, and `PyCore::bytes()` uses the converted `zend_string` for non-string scalars; neither dereferences a null pointer or the wrong zval union field, which would crash the process. `PyCore::next()` simultaneously releases the iterator reference produced by argument conversion. The builtin/operator function cache uses a `std::string` content key, no longer using request-level `char*` addresses as long-term keys, and also avoids the same-named dynamic call repeatedly caching and increasing Python function reference counts; calling a builtin that exists but is not callable releases the temporary reference and throws `PyError(TypeError)`. All issues are covered by independent PHPUnit tests that first fail, of which two crashes were confirmed in isolated processes with core dumps disabled (exit code 139) before being fixed.

The fourth round covered the Python-to-PHP synchronous callback boundary. phpy converts Python keyword arguments into Zend named parameters and stops immediately after any positional or named argument conversion fails, so a PHP callable that only received part of its arguments is never executed. PHPX generates and manages Zend `arg_info` parameter name metadata for AOT native closures, so Python kwargs can be bound to TypePHP closures by name rather than depending on parameter positions or degrading to a string callable. phpy PHPUnit, PHPX unit tests, and TypePHP PHPT respectively cover conversion failure, Zend named binding, and the complete Python→TypePHP callback chain.

The fifth round covered exceptions and ownership when Python strings cross the Zend boundary. When a Python lone surrogate character cannot be encoded as UTF-8, `phpy.String`, dynamic PHP class names, dictionary keys, `PyObject::__toString()`, and Python exception message formatting no longer use null pointers or uninitialized lengths; before the fix, the relevant isolation tests would exit with 139 or attempt to allocate abnormally large memory. `StrObject` now has an explicit valid state, and all callers must check the conversion result before accessing the pointer; stringification of exception messages is only best-effort auxiliary information, preserving the original Python error/type/value on failure and cleaning up the temporary CPython error indicator. `new_string()` also completes Zend carrier destruction registration, and fixed-length strings directly obtain a unique `zend_string` reference, eliminating the leak and uninitialized zval on the success path.

The sixth reference audit fixed the new-reference leak in `PySequence::slice()`. After the slice is wrapped as a Zend `PyObject`, the original ownership returned by the CPython API is released while the wrapper keeps its own reference; a `sys.getrefcount()` stress test verifies that repeatedly creating and destroying slices does not continue to increase the element reference count. Slice creation failure is also converted to `PyError` before touching a null pointer.

The seventh round covered the Python operator protocol of dynamic PHP. The phpy opcode handler now uses CPython `PyNumber_*` / `PyObject_RichCompareBool()`, `/` and `/=` use true division, compound assignment updates the Zend lvalue with the object returned by the in-place API, and correctly handles immutable Python objects, Zend reference variables, expression results, and the lvalue state after failure. `===` / `!==` use Python object identity, bool cast, `!`, and conditional branches use the Python truth protocol; new references on the PHP operand conversion, result, and exception paths are uniformly managed by RAII guards. TypePHP's `operators.phpt` runs with both ZendPHP + opcode handler and AOT + `operator` module, so it is the output-consistency gate for the two implementations.

Unary positive/negative operations in dynamic code have been confirmed as a compatibility boundary: PHP compiles `-$value` / `+$value` into multiplication by `-1` / `1`, and the opcode handler cannot distinguish this from explicit multiplication in the source. Dynamic ZendVM code no longer attempts to rewrite the AST, but explicitly retains the `$value * -1` / `$value * 1` behavior. Python builtin numerics and common objects such as NumPy usually produce consistent results, but a custom object's `__neg__()` / `__pos__()` may differ from its `__mul__()`. AOT TypePHP retains the original AST and still lowers to `operator.neg()` / `operator.pos()` respectively, so the semantics are unaffected. The external user document `python.md` has listed this difference as a compatibility limitation.

The performance benchmarks separately cover module properties, operator module calls, existing `PyObject` arguments, and PHP scalar argument conversion. In the current unoptimized build, module properties are about 0.7–0.9 μs/op, and operator calls about 1.8–2.5 μs/op; whether arguments are pre-wrapped as `PyObject` shows no stable difference. After attempting to eliminate the per-access reference count of the module map with an indirect zval wrapper, the A/B medians remained within the same noise range, so the more lifetime-sensitive optimization with unproven benefit was not retained.

phpy's CMake Python-extension target also completed the out-of-tree build fix, and is compatible with the `int` ABI of older PHP and the `zend_result` ABI of newer PHP by deriving the return type from Zend's standard cast handler. Both PHP 8.1 and PHP 8.4 have completed clean-build verification.

The memory gate uses Valgrind Memcheck. Tests disable the Zend allocator and PCRE JIT, and in a minimal independent process loop 100 times each over PHP Closure kwargs callbacks, callable PHP object kwargs callbacks, sequence slice creation/destruction, object stringification of invalid Unicode, dictionary key conversion, and exception formatting. The result is 0 invalid-access, 0 definite leak, 0 indirect leak; the 493,106 bytes retained by PHP/CPython at process exit are all still-reachable and not counted as leaks. The ASan extension cannot be safely `dlopen`-ed into the current non-ASan PHP with `RTLD_DEEPBIND` enabled, so this round uses Valgrind, which does not require recompiling PHP, as the memory checking tool.

Phase 6 final gate results: phpy PHPUnit 135 tests / 469 assertions passed (1 existing warning, 1 environment-related skip), pytest 26/26 passed, TypePHP Python PHPT 14/14 passed, TypePHP PHPUnit 1103 tests / 2729 assertions passed; the TypePHP compiler full PHPT has 934 items in total, of which 932 PASS, 2 SKIP, 0 FAIL, 0 WARN. The dynamic operator stress test under Valgrind is 0 invalid access, 0 definite leak, 0 indirect leak.

## Phase Gates

- Do not start implementation before the current phase's failing tests are established.
- Do not enter the next phase before all tests of the current phase pass.
- phpy behavior changes must first add PHPUnit/pytest tests in the phpy repository.
- Every fixed bug must retain an independent regression test.
- Do not mask implementation differences by modifying third-party test expectations.

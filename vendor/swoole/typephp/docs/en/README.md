# TypePHP Compiler Internal Documentation

This directory contains compiler implementation, compatibility, build-mode, and special-topic design documents. The user-facing manual lives in the separate `aot/docs` repository; the research reports and refactoring plans here may describe historical state, and current behavior should be determined by the code, tests, and compatibility checklist.

## Current authoritative documents

- [AOT and PHP Incompatible Features Checklist](INCOMPATIBLE_PHP_FEATURES.md): a concise list of current limitations.
- [Incompatibility Classification](PHP_INCOMPATIBILITY_CLASSIFICATION.md): distinguishes Hard Limit, Intentional Rule, Pending, and Partial.
- [Compatibility Engineering Policy](COMPATIBILITY_POLICY.md): prioritization, PHPX abstraction boundaries, and review rules for PHP compatibility work.
- [Compiler CLI](COMPILER_CLI.md): current CLI arguments and project configuration.
- [Compilation Modes](COMPILATION_MODES.md): binary, extension, library modes.
- [Quick Start](QUICKSTART.md): the minimal compile flow.
- [Compile-time Functions](COMPILE_TIME_FUNCTIONS.md): `any()`, `refval()`, `objval()`, `expected()`, `unexpected()`, and keyword methods.
- [Native Types](NATIVE_TYPES.md), [High-Precision Types](HIGH_PRECISION_TYPES.md), [Std Containers](STD_CONTAINERS.md).
- [Three Object Storage and Passing Models](OBJECT_STORAGE_AND_PASSING_MODELS.md): the responsibilities, ABI, and non-substitutable boundaries of Zend Object, PHPX Box, and Native Class Object.
- [Universal and Extension Methods](UNIVERSAL_METHODS.md), [Generator](YIELD_GENERATOR.md).
- [`#[Immutable]` compile-time read-only contract](IMMUTABLE.md): methods, parameters, aliases, call boundaries, and dynamic escape rules.
- [`#[ArrayDef]` array property contract](ARRAY_DEF.md): List/Map metadata, direct-write checks, and dynamic escape boundaries.
- [Class Inheritance](CLASS_INHERITANCE.md), [Mixed C++/PHP](MIXED_CPP_PHP.md).

## Architecture and maintenance

- [Backend-Neutral IR](BACKEND_NEUTRAL_IR.md)
- [TypePHP WASM Technical Plan and Implementation Plan](TYPEPHP_WASM_IMPLEMENTATION_PLAN.md)
- [Building TypePHP WASI Programs](WASI_BUILD.md)
- [Rebuilding the PHPX WASM Static Library](PHPX_WASM_BUILD.md): incremental rebuild of `libphpx.a`, numeric-dependency rebuild, and full SDK rebuild boundaries.
- [Core Refactoring Plan](REFACTORING_PLAN.md)
- [Scope Management Design](SCOPE_MANAGEMENT.md): responsibilities and usage boundaries of `CallableScope`, `UserCodeScopeGuard`, and `FakeScopeGuard`.
- [Runtime Initialization and Shutdown Flow](RUNTIME_LIFECYCLE.html): the four-layer lifecycle of PHP, PHPX, TypePHP, and the project, covering bin/ext/lib, multi-module, and WASM.
- [C++ Namespaces, Prefixes, and Symbol ABI](CPP_SYMBOL_NAMING.md): responsibility boundaries and conflict rules for `typephp_`, `php::`, `typephp_<project>`, and user callables `php_`.
- [Zend Object Creation and Property Default Value Initialization](OBJECT_CREATION.md): the `gen_stub.php` default property table, trigger conditions for custom `create_object`, execution flow, and performance boundaries.
- [Native Class Object Design](NATIVE_CLASS_OBJECT.md) and [Implementation Acceptance Matrix](NATIVE_CLASS_IMPLEMENTATION_AUDIT.md).
- [PHP 8.4 Property Hook Integration Design](PROPERTY_HOOKS.md): compile-time lowering, Zend Hook metadata, object introspection, and PHPX ABI boundaries.
- [Interface Property Hook Implementation Plan](INTERFACE_PROPERTY_HOOKS.md): interface property contracts, compile-time variance checks, and PHP 8.4 abstract Hook metadata.
- [Build Speed Research](AOT_BUILD_SPEED_RESEARCH.md)
- [Optimization Priority](aot-optimization-priority.md)
- [High-Precision Type In-Place Operation Optimization Plan](BIG_NUMBER_INPLACE_OPTIMIZATION_PLAN.md)
- [GMP Differences](GMP_GAP.md)

## Research and historical materials

`hhvm-review.md`, `kphp-review.md`, `peachpie-review.md`, `phpstan-design-analysis.md`, `php-src-optimizer-analysis.md`, and the patent drafts record comparisons and design background at the time of investigation, and do not serve as the current feature list.

## Maintenance rules

1. When current compatibility changes, update `INCOMPATIBLE_PHP_FEATURES.md` and the classification document simultaneously.
2. All syntax and semantic limitations link uniformly to the current compatibility checklist to avoid maintaining duplicate lists.
3. Feature support should be determined by PHPT/PHPUnit regression tests.
4. Historical research documents preserve the original comparison conclusions and note the investigation date where necessary; they should not be silently rewritten to reflect the current state.

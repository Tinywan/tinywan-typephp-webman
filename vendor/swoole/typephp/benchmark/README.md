# TypePHP benchmarks

This directory contains repeatable performance workloads used to guide and
verify compiler/runtime optimizations. Benchmark results depend on the CPU,
PHP build, compiler, and system load, so compare PHP and TypePHP on the same
machine instead of committing absolute timing expectations.

- `bench.php` and `micro_bench.php` are the original general workloads moved
  from `examples/`.
- `bridge/` measures calls, property operations, and container operations that
  cross the generated-code/PHPX/Zend boundary.
- `dynamic-call/` measures monomorphic and polymorphic runtime callables so
  call-site cache changes can be evaluated independently from direct AOT calls.
- `property-access/` builds and compares dynamic/static property access under
  Zend PHP and TypePHP.

Run the property benchmark from the repository root:

```bash
php benchmark/property-access/run.php
```

The property benchmark builds the generated application with `-O3` and LTO.
For meaningful results, link it against a Release build of PHPX as well; a
Debug/`-O0` `libphpx` makes property helper calls several times slower and is
not representative of a release package.

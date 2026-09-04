# Dynamic call benchmark

This benchmark compares direct calls with the runtime callable forms handled
by PHPX. It deliberately separates stable call sites from alternating and
megamorphic sites. TypePHP's function-call cache keeps one name inline and
promotes polymorphic sites to a request-local table. Its method-call cache is
monomorphic and disables itself after observing a different class or name.

It also measures dynamic method names with a stable receiver, alternating
method names, and a fixed method name on changing receiver classes. Those
cases require a class-entry guard in addition to a callable-name guard.

Run it from the repository root against a release PHP/PHPX build:

```bash
PHPX_HOME=../phpx PHP_BIN=/opt/php-8.5-nts/bin/php php benchmark/dynamic-call/run.php
```

The TypePHP binary is built with `-O3` and LTO. `PHP_BIN` selects both the Zend
PHP baseline and, by default, the PHP executable used to run the compiler.
`TPC_PHP_BIN` may override the latter, but the runner rejects different PHP
versions, ZTS/debug modes, or integer widths. PHPX must also be a Release build
for that same PHP ABI. Add `--skip-build` to reuse the binary or
`--case=<name>` to measure one workload while profiling.

Reported values are the best of five rounds after two warm-up rounds. The
checksum must match between PHP and TypePHP; absolute timing is intentionally
not used as a pass/fail condition.

# Static class cache benchmark

This benchmark contains isolated reads and writes of statically resolved
`self::$property` and `Class::$property` slots. It also covers a common
metadata-cache pattern: a static array keyed by `static::class`, guarded by
`isset()`, plus a wrapper method using `static::method()`. The latter measures
static-property lookup, array lookup, strict return checks, and late-static
dispatch together.

Run it from the repository root against matching Release PHP and PHPX builds:

```bash
PHPX_HOME=../phpx PHP_BIN=/opt/php-8.5-nts/bin/php php benchmark/static-cache/run.php
```

The TypePHP binary is built with `-O3` and LTO. Results use the best of five
measured rounds after two warm-up rounds; checksums must match before ratios are
reported. Use `--skip-build` to reuse the existing binary.

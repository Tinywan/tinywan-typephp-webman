# Test Coverage Checklist

`bin/analyze-test-coverage.php` generates a coverage checklist from the source of PHPT and compiler PHPUnit fixtures. It is a static test-intent analysis tool and does not replace test execution.

## Usage

```bash
# terminal summary
php bin/analyze-test-coverage.php

# reviewable full matrix
php bin/analyze-test-coverage.php \
  --format=markdown \
  --output=build/test-coverage.md

# for CI or other tools to read
php bin/analyze-test-coverage.php \
  --format=json \
  --output=build/test-coverage.json \
  --strict
```

By default it scans `tests/compiler`, `phpunit/src`, and `phpunit/code`. One or more PHPT files or directories can also be passed at the end of the command; `--no-phpunit` analyzes only PHPT, and `--php-versions=8.4,8.5` sets the PHP version columns of the matrix.

`--strict` returns a non-zero status when there are unexpected source-parse failures or unresolvable PHPUnit fixture references. Samples in negative data providers that are intentionally unparseable by php-parser are recorded separately under `expected_parser_diagnostics` and are not disguised as tool failures.

## Three categories of coverage evidence

Each applicable `PHP version × feature` row records:

- `positive_compile`: valid PHPT, or positive PHPUnit compile fixtures;
- `runtime_semantics`: valid PHPT containing `EXPECT`, `EXPECTF`, or `EXPECTREGEX`;
- `negative_diagnostic`: PHPT expecting diagnostics, or PHPUnit tests/data providers that explicitly expect failure.

`XFAIL` and unconditional `SKIPIF` are not counted on any evidence axis. PHP version ranges are inferred from test titles, `PHP_VERSION_ID` conditions in `SKIPIF`, and version strings in PHPUnit data rows.

## Denominator

The report only gives ratios with an explicit denominator:

- AST node coverage denominator: the concrete AST node kinds provided by the currently installed `nikic/php-parser`; `Expr_Error`, used for error recovery, is not counted.
- Feature-axis coverage denominator: the number of rows in the feature catalog with `introduced <= target PHP version`. Each of the positive-compile, runtime-semantics, and negative-diagnostic axes is computed independently.

The tool does not combine the three axes of different meanings into a single "overall project coverage". The full JSON preserves the feature catalog, per-item evidence sources, matrix, AST node occurrence counts, parse issues, and exclusion reasons, for further inspection by CI.

## Classification boundaries

AST nodes are extracted automatically by the parser. Semantic features that cannot be distinguished by nodes alone (such as DNF occurrence positions, property hook variants, `exit(message: ...)`) are supplemented by the explicit feature catalog in the analyzer. When adding a new language feature, register its introduction version and detection rule at the same time to keep the version matrix's denominator explicit.

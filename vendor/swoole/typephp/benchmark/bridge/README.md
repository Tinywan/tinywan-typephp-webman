# PHP bridge benchmark

This benchmark measures the cost of common operations that cross between
generated TypePHP code and PHPX/Zend. It is the maintained version of the
original `debug/bridge-bench` reproducer.

Run it from the repository root:

```bash
PHPX_HOME=../phpx PHP_BIN=/opt/php-8.5-nts/bin/php php benchmark/bridge/run.php
```

The TypePHP binary is built with `-O3` and LTO. `PHP_BIN` selects the Zend PHP
binary used for the baseline; `TPC_PHP_BIN` can independently select the PHP
binary that runs `bin/tpc.php`. Add `--skip-build` to reuse an existing binary.
Use `--case=magic_property` to run only one workload while profiling.

Results are the best of five rounds after warm-up. Always compare PHP and
TypePHP in the same run on an otherwise idle machine.

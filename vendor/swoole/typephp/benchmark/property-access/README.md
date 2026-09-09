# Dynamic property benchmark

This benchmark compares the same dynamic and static property operations under
Zend PHP and a TypePHP `-O3` + LTO binary. Each metric is the best of seven
rounds after three warm-up rounds and is reported in nanoseconds per property
access.

Run it from the repository root:

```bash
php benchmark/property-access/run.php
```

To reuse an existing binary, add `--skip-build`. For local regression checks,
`--max-ratio=1.5` exits unsuccessfully when a dynamic read or write takes more
than 1.5 times the corresponding Zend PHP result. Use
`--case=dynamic_write`, `--case=dynamic_read`, `--case=static_write`, or
`--case=static_read` to isolate one workload while profiling.

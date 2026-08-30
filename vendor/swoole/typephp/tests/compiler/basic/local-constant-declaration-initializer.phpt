--TEST--
Only compile-time constants initialize hoisted local declarations
--FILE--
<?php

namespace LocalConstantInitializer {
    const VALUE = 42;

    function readValues(): array
    {
        $compiled = VALUE;
        $internal = \PHP_INT_MAX;

        define('LocalConstantInitializer\\RUNTIME_VALUE', 99);
        $runtime = RUNTIME_VALUE;

        return [$compiled, $internal === PHP_INT_MAX, $runtime];
    }
}

namespace {
    function main(): void
    {
        var_dump(\LocalConstantInitializer\readValues());
    }
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(42)
  [1]=>
  bool(true)
  [2]=>
  int(99)
}

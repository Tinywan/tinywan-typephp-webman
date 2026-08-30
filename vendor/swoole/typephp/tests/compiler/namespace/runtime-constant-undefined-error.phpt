--TEST--
Undefined runtime constants in a namespace throw Error after global fallback
--FILE--
<?php

namespace RuntimeConstantUndefined {
    function readMissing()
    {
        return MISSING_RUNTIME_CONSTANT;
    }
}

namespace {
    function main(): void
    {
        try {
            \RuntimeConstantUndefined\readMissing();
        } catch (\Error $error) {
            echo $error->getMessage(), PHP_EOL;
        }
    }
}
?>
--EXPECT--
Undefined constant "RuntimeConstantUndefined\MISSING_RUNTIME_CONSTANT"

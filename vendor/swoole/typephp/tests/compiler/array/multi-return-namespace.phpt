--TEST--
Tuple multi-return fast path supports namespaced functions
--FILE--
<?php
namespace MultiReturnExample {
    function values(): array
    {
        return [10, 'namespaced'];
    }
}

namespace {
    function main(): void
    {
        [$number, $text] = \MultiReturnExample\values();
        var_dump($number, $text);
    }
}
?>
--EXPECT--
int(10)
string(10) "namespaced"

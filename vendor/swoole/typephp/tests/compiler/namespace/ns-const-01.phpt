--TEST--
namespace constants
--FILE--
<?php
namespace foo {
    const bar = 42;
}

namespace {
    const bar = 35;
    function main() {
        var_dump(bar);
        var_dump(foo\bar);
        echo "Done\n";
    }
}
?>
--EXPECT--
int(35)
int(42)
Done

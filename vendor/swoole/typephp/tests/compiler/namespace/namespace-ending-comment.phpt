--TEST--
A namespace block ending with a comment must not be treated as stray code
--FILE--
<?php

declare(strict_types=1);

namespace Test {
    /* named namespace trailing block comment */
}

namespace {
    function main()
    {
        var_dump('done');
    }

    // global namespace trailing line comment
}
?>
--EXPECT--
string(4) "done"

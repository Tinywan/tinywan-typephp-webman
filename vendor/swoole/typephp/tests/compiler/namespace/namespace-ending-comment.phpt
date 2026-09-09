--TEST--
A namespace block ending with a comment must not be treated as stray code
--FILE--
<?php


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

--TEST--
aliasing imported constants to resolve naming conflicts
--FILE--
<?php

namespace foo {
    const baz = 42;
}

namespace bar {
    const baz = 43;
}

namespace {
    use const foo\baz;
    use const bar\baz as bar_baz;
    function main() {
        var_dump(baz);
        var_dump(bar_baz);
        echo "Done\n";
    }
}

?>
--EXPECT--
int(42)
int(43)
Done

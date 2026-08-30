--TEST--
namespace relative qualified constants
--FILE--
<?php
namespace App\Sub {
    const VALUE = 77;
}

namespace App {
    function readRelative(): void {
        var_dump(Sub\VALUE);
    }
}

namespace {
    function main(): void {
        App\readRelative();
        var_dump(\App\Sub\VALUE);
    }
}
?>
--EXPECT--
int(77)
int(77)

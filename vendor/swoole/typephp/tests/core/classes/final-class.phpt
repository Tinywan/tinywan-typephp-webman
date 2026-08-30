--TEST--
ZE2 A method may be redeclared final
--FILE--
<?php

class first {
    function show() {
        echo "Call to function first::show()\n";
    }
}

class second extends first {
    final function show() {
        echo "Call to function second::show()\n";
    }
}

function main() {
    $t = new first();
    $t->show();
    $t2 = new second();
    $t2->show();

    echo "Done\n";
}
?>
--EXPECT--
Call to function first::show()
Call to function second::show()
Done

--TEST--
Bug #18872 (class constant used as default parameter)
--SKIPIF--
<?php die("skip");?>
--FILE--
<?php
class FooBar {
    const BIFF = 3;
}

function foo($biff = FooBar::BIFF) {
    echo $biff . "\n";
}

foo();
foo();
?>
--EXPECT--
3
3

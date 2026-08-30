--TEST--
abstract class and abstract method
--FILE--
<?php

function main() {
    $dt = new DateTime();
    echo $dt->format('Y-m-d H:i:s');
}
?>
--EXPECTF--
%s-%s%s %s:%s:%s
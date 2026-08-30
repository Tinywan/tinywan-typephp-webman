--TEST--
declare bare anonymous class
--FILE--
<?php
function main() {
    var_dump(new class{});
}
?>
--EXPECTF--
object(%s)#%d (0) {
}

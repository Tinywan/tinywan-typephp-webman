--TEST--
Testing eval function inside user-defined function
--FILE--
<?php
function F ($a) {
    eval($a);
}
function main() {
    error_reporting(0);
    F("echo \"Hello\";");
}
?>
--EXPECT--
Hello

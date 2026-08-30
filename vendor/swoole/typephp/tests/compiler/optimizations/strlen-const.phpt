--TEST--
strlen(constant_string) compile-time folding
--FILE--
<?php
function main(): void {
    var_dump(strlen("hello"));
    var_dump(strlen(""));
    var_dump(strlen("hello world"));
    var_dump(strlen("你好世界"));
    var_dump(strlen("a" . "b"));
}
?>
--EXPECT--
int(5)
int(0)
int(11)
int(12)
int(2)

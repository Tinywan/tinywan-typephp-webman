--TEST--
assign coalesce on undefined variable
--FILE--
<?php
$a ??= 123;
var_dump($a);

$b ??= 'foo';
$b ??= 'bar';
var_dump($b);

$c ??= null;
var_dump(isset($c));
$c ??= 'after-null';
var_dump($c);

for ($i = 0; $i < 2; $i++) {
    $d ??= printf("default\n");
}
var_dump($d);
?>
--EXPECT--
int(123)
string(3) "foo"
bool(false)
string(10) "after-null"
default
int(8)

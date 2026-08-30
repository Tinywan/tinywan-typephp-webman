--TEST--
Misc functions - md5, version_compare, print_r
--FILE--
<?php

echo "== md5() ==\n";
echo md5("hello") . "\n";
echo md5("") . "\n";
var_dump(strlen(md5("test", true)) === 16);

echo "== version_compare() ==\n";
var_dump(version_compare("1.2.3", "1.2.3"));
var_dump(version_compare("1.2.3", "1.2.4"));
var_dump(version_compare("2.0.0", "1.9.9"));
var_dump(version_compare("1.0", "1.0.0"));

echo "== print_r() ==\n";
$s = print_r([1, 2, 3], true);
echo $s;
$s2 = print_r("hello", true);
echo $s2;

?>
--EXPECT--
== md5() ==
5d41402abc4b2a76b9719d911017c592
d41d8cd98f00b204e9800998ecf8427e
bool(true)
== version_compare() ==
int(0)
int(-1)
int(1)
int(-1)
== print_r() ==
Array
(
    [0] => 1
    [1] => 2
    [2] => 3
)
hello

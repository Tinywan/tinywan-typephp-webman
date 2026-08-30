--TEST--
Filesystem and math functions - is_dir, is_file, round
--FILE--
<?php

echo "== is_dir() ==\n";
var_dump(is_dir("/"));
var_dump(is_dir("/nonexistent_path_xyz123"));
var_dump(is_dir(sys_get_temp_dir()));

echo "== is_file() ==\n";
var_dump(is_file("/nonexistent_file_xyz123"));
var_dump(is_file(__FILE__));

echo "== round() ==\n";
var_dump(round(3.4));
var_dump(round(3.6));
var_dump(round(3.14159, 2));
var_dump(round(3.5));
var_dump(round(4.5));
var_dump(round(-1.5));
var_dump(round(1234.5678, 2));
var_dump(round(1234.5678, -2));

?>
--EXPECT--
== is_dir() ==
bool(true)
bool(false)
bool(true)
== is_file() ==
bool(false)
bool(true)
== round() ==
float(3)
float(4)
float(3.14)
float(4)
float(5)
float(-2)
float(1234.57)
float(1200)

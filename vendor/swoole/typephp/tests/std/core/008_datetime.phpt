--TEST--
Date/time functions - time, date, strtotime
--FILE--
<?php

echo "== time() ==\n";
$t = time();
var_dump($t > 1000000000);

echo "== date() ==\n";
$d = date("Y");  // current year, always works
echo "year: $d\n";
var_dump(strlen(date("Y-m-d")) === 10);

echo "== strtotime() ==\n";
$ts = strtotime("2021-01-01 00:00:00 UTC");
var_dump($ts > 0);
var_dump(strtotime("not a valid date"));
var_dump(is_int(strtotime("+1 day", 1609459200)));

?>
--EXPECT--
== time() ==
bool(true)
== date() ==
year: 2026
bool(true)
== strtotime() ==
bool(true)
bool(false)
bool(true)

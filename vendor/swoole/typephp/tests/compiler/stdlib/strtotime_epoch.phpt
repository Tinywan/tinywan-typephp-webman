--TEST--
strtotime: absolute, relative, epoch 0 and invalid dates
--FILE--
<?php
date_default_timezone_set("UTC");
var_dump(strtotime("@0"));
var_dump(strtotime("1970-01-01 00:00:00 UTC"));
var_dump(strtotime("1970-01-02 00:00:00 UTC"));
var_dump(strtotime("2000-01-01 00:00:00 UTC"));
var_dump(strtotime("-1 day", 1609459200));
$before = time();
$relative = strtotime("-1 day");
$after = time();
var_dump($relative >= $before - 86401 && $relative <= $after - 86399);
var_dump(strtotime("invalid-date-string"));
?>
--EXPECT--
int(0)
int(0)
int(86400)
int(946684800)
int(1609372800)
bool(true)
bool(false)

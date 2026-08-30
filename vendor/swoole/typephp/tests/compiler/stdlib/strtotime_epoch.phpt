--TEST--
strtotime: epoch 0 and invalid date
--FILE--
<?php
var_dump(strtotime("@0"));
var_dump(strtotime("1970-01-01 00:00:00 UTC"));
var_dump(strtotime("1970-01-02 00:00:00 UTC"));
var_dump(strtotime("2000-01-01 00:00:00 UTC"));
var_dump(strtotime("invalid-date-string"));
?>
--EXPECT--
int(0)
int(0)
int(86400)
int(946684800)
bool(false)

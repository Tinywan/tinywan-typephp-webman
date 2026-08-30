--TEST--
Bug #69198 (Compact function generate array with length but no content)
--SKIPIF--
<?php die("skip AOT compact function behavior differs from PHP"); ?>
--FILE--
<?php
if (false) {
        $willNeverBeDefined = true;
}
$result = compact('willNeverBeDefined');
var_dump($result, empty($result), $result === array(), empty($willNeverBeDefined));
?>
--EXPECTF--
Warning: compact(): Undefined variable $willNeverBeDefined in %s on line %d
array(0) {
}
bool(true)
bool(true)
bool(true)

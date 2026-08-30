--TEST--
bool 001
--FILE--
<?php
function main()
{
    $offset = 1;
    $maxCount = any(10);
    var_dump($maxCount-- > 0 && $offset);
}
?>
--EXPECT--
bool(true)
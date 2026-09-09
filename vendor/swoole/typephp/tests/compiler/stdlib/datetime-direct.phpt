--TEST--
date, gmdate and time use direct PHPX wrappers
--FILE--
<?php

function main(): void
{
    date_default_timezone_set('Asia/Shanghai');

    var_dump(date('Y-m-d H:i:s', 0));
    var_dump(gmdate('Y-m-d H:i:s', 0));
    var_dump(time() > 0);
}
?>
--EXPECT--
string(19) "1970-01-01 08:00:00"
string(19) "1970-01-01 00:00:00"
bool(true)

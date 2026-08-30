--TEST--
defined() function
--FILE--
<?php

define("MY_CONST", 42);
define("MY_STRING_CONST", "hello");

echo "defined:\n";
echo defined("MY_CONST") ? "ok-defined\n" : "fail\n";
echo defined("MY_STRING_CONST") ? "ok-string-defined\n" : "fail\n";
echo defined("PHP_VERSION") ? "ok-php-version\n" : "fail\n";
echo defined("NON_EXISTENT") ? "fail\n" : "ok-undefined\n";

echo "done\n";
?>
--EXPECT--
defined:
ok-defined
ok-string-defined
ok-php-version
ok-undefined
done

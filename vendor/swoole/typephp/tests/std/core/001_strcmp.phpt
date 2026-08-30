--TEST--
strcmp() / strncmp() / strcasecmp() / strncasecmp() functions
--FILE--
<?php

echo "strcmp:\n";
echo strcmp("abc", "abc") === 0 ? "ok-equal\n" : "fail\n";
echo strcmp("abc", "abd") < 0 ? "ok-less\n" : "fail\n";
echo strcmp("abd", "abc") > 0 ? "ok-greater\n" : "fail\n";
echo strcmp("", "") === 0 ? "ok-empty\n" : "fail\n";

echo "strncmp:\n";
echo strncmp("abcdef", "abcxyz", 3) === 0 ? "ok-equal3\n" : "fail\n";
echo strncmp("abcdef", "abcxyz", 6) < 0 ? "ok-less6\n" : "fail\n";
echo strncmp("abc", "abc", 5) === 0 ? "ok-overflow\n" : "fail\n";

echo "strcasecmp:\n";
echo strcasecmp("ABC", "abc") === 0 ? "ok-equal\n" : "fail\n";
echo strcasecmp("ABC", "abd") < 0 ? "ok-less\n" : "fail\n";

echo "strncasecmp:\n";
echo strncasecmp("ABCDEF", "abcxyz", 3) === 0 ? "ok-equal3\n" : "fail\n";
echo strncasecmp("ABCDEF", "abcxyz", 6) < 0 ? "ok-less6\n" : "fail\n";

?>
--EXPECT--
strcmp:
ok-equal
ok-less
ok-greater
ok-empty
strncmp:
ok-equal3
ok-less6
ok-overflow
strcasecmp:
ok-equal
ok-less
strncasecmp:
ok-equal3
ok-less6

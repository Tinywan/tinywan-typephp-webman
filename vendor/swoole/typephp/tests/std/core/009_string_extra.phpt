--TEST--
String functions - dirname, basename, strtr
--FILE--
<?php

echo "== dirname() ==\n";
echo dirname("/etc/passwd") . "\n";
echo dirname("/etc/") . "\n";
echo dirname("/") . "\n";
echo dirname("relative/path/file.txt") . "\n";

echo "== basename() ==\n";
echo basename("/etc/passwd") . "\n";
echo basename("/etc/passwd", ".passwd") . "\n";
echo basename("relative/path/file.php", ".php") . "\n";
echo "[" . basename("/") . "]\n";

echo "== strtr() ==\n";
echo strtr("hello world", "wo", "WO") . "\n";
echo strtr("abcdef", "abc", "123") . "\n";

?>
--EXPECTREGEX--
== dirname\(\) ==
\/etc
[\\\/]
[\\\/]
relative\/path
== basename\(\) ==
passwd
passwd
file
\[\]
== strtr\(\) ==
hellO WOrld
123def

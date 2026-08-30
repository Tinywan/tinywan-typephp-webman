--TEST--
Symfony Process pattern: nested isset on commandline string offset
--FILE--
<?php

function normalizeCommand(array $commandline): array
{
    if (isset($commandline[0][0]) && strlen($commandline[0]) === strcspn($commandline[0], ':/\\')) {
        $commandline[0] = 'resolved:'.$commandline[0];
    }

    return $commandline;
}

function main(): void
{
    var_dump(normalizeCommand(['php', '-v']));
    var_dump(normalizeCommand(['/usr/bin/php', '-v']));
    var_dump(normalizeCommand(['', '-v']));
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(12) "resolved:php"
  [1]=>
  string(2) "-v"
}
array(2) {
  [0]=>
  string(12) "/usr/bin/php"
  [1]=>
  string(2) "-v"
}
array(2) {
  [0]=>
  string(0) ""
  [1]=>
  string(2) "-v"
}

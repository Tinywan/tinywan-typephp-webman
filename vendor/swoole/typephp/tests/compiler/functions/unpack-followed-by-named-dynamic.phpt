--TEST--
unpack followed by named argument should use dynamic call semantics
--FILE--
<?php

function unpack_named_target($a, $b = 20, $c = 30, ...$rest) {
    var_dump($a, $b, $c, $rest);
}

function main() {
    unpack_named_target(...[10], c: 300, extra: 400);

    $fn = 'unpack_named_target';
    $fn(...[11], c: 301, extra: 401);
}
?>
--EXPECT--
int(10)
int(20)
int(300)
array(1) {
  ["extra"]=>
  int(400)
}
int(11)
int(20)
int(301)
array(1) {
  ["extra"]=>
  int(401)
}

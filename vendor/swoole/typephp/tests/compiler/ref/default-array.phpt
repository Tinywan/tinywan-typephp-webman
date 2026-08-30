--TEST--
ref call arg
--FILE--
<?php
function foo(array &$ref = []) {
    $ref[] = uniqid();
    return $ref;
}

function main()
{
    $rs = foo();
    var_dump($rs);

    foo($rs);
    var_dump($rs);
}
?>
--EXPECTF--
array(1) {
  [0]=>
  string(13) "%s"
}
array(2) {
  [0]=>
  string(13) "%s"
  [1]=>
  string(13) "%s"
}
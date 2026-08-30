--TEST--
std ordered_map: unset
--FILE--
<?php
function main() {
    $map = std::ordered_map(Type::String, Type::Int);
    $map["alpha"] = 10;
    $map["beta"] = 20;
    var_dump($map);
    unset($map["alpha"]);
    var_dump($map);
}
?>
--EXPECT--
array(2) {
  ["alpha"]=>
  int(10)
  ["beta"]=>
  int(20)
}
array(1) {
  ["beta"]=>
  int(20)
}
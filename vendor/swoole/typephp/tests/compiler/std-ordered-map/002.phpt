--TEST--
std ordered_map: string key
--FILE--
<?php
function main() {
    $map = std::ordered_map(Type::String, Type::Int);
    $map["alpha"] = 10;
    $map["beta"] = 20;
    $map["beta"] += 22;

    $key = "alpha";
    var_dump($map[$key]);
    var_dump($map["beta"]);
    var_dump(count($map));

    $map2 = std::ordered_map(Type::String, Type::Float);
    $map2["pi"] = 3.14;
    var_dump($map2["pi"] == 3.14);
}
?>
--EXPECT--
int(10)
int(42)
int(2)
bool(true)

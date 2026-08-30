--TEST--
std map: string key
--FILE--
<?php
function main() {
    $map = std::map(Type::String, Type::Float);
    $map["alpha"] = 1.5;
    $map["beta"] = 2.5;
    $map["beta"] += 0.25;

    $key = "alpha";
    var_dump($map[$key] == 1.5);
    var_dump($map["beta"] == 2.75);
    var_dump(count($map));

    $map2 = std::map(Type::String, Type::Int);
    $map2["answer"] = 42;
    var_dump($map2["answer"]);
}
?>
--EXPECT--
bool(true)
bool(true)
int(2)
int(42)

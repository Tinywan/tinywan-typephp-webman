--TEST--
std ordered_map: unset
--FILE--
<?php
function main() {
    $map = std::ordered_map(Type::String, Type::Int);
    $map["alpha"] = 10;
    $map["beta"] = 20;
    $map["gamma"] = 30;

    foreach ($map as $k => $v) {
        var_dump($k, $v);
    }
    unset($map["beta"]);
    echo "unset-------------\n";
    foreach ($map as $k => $v) {
        var_dump($k, $v);
    }
}
?>
--EXPECT--
string(5) "alpha"
int(10)
string(4) "beta"
int(20)
string(5) "gamma"
int(30)
unset-------------
string(5) "alpha"
int(10)
string(5) "gamma"
int(30)
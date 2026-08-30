--TEST--
std array: foreach
--FILE--
<?php
function main() {
    $a = std::array(Type::Int, 5);
    foreach($a as $k => $item) {
        $a[$k] = random_int(1, 1000);
    }
    $count = array_sum($a);
    var_dump($count >= 5);
}
?>
--EXPECT--
bool(true)
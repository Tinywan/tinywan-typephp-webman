--TEST--
std vector: foreach
--FILE--
<?php
use native_types;
function main() {
    $a = std::vector(Type::Int);
    $n = 5;
    while($n--){
        $a[] = random_int(1, 1000);
    }
    $count = array_sum($a);
    var_dump($count >= 5);
    $total = 0;
    foreach($a as $v){
        $total += $v;
    }
    var_dump($total == $count);
}
?>
--EXPECT--
bool(true)
bool(true)

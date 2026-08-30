--TEST--
std map: foreach allows value updates and blocks structural writes
--FILE--
<?php
function main() {
    $map = std::map(Type::String, Type::Int);
    $map['a'] = 1;
    $map['b'] = 2;

    foreach ($map as $key => $value) {
        $map[$key] += 10;
        try {
            $map['new'] = 3;
        } catch (Throwable $e) {
            echo "blocked\n";
        }
    }

    var_dump(count($map));
    var_dump($map['a']);
    var_dump($map['b']);
}
?>
--EXPECT--
blocked
blocked
int(2)
int(11)
int(12)

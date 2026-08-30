--TEST--
std map: missing reads do not insert keys
--FILE--
<?php
function main() {
    $map = std::map(Type::String, Type::Int);
    try {
        var_dump($map['missing']);
    } catch (Throwable $e) {
        echo "missing\n";
    }
    var_dump(count($map));

    $ordered = std::ordered_map(Type::String, Type::Int);
    try {
        var_dump($ordered['missing']);
    } catch (Throwable $e) {
        echo "ordered missing\n";
    }
    var_dump(count($ordered));
}
?>
--EXPECT--
missing
int(0)
ordered missing
int(0)

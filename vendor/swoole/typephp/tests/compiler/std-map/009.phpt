--TEST--
std map: unset
--FILE--
<?php
function main() {
    $map = std::map(Type::String, Type::Int);
    $map["alpha"] = 10;
    $map["beta"] = 20;

    // Windows and linux std::map implementation may have different iteration order, so we cannot verify the order of keys. Instead, we verify the count and existence of keys.

    // verify all expected keys exist with correct values
    var_dump($map["alpha"] === 10);
    var_dump($map["beta"] === 20);
    // verify count is 2
    $found = [];
    foreach ($map as $k => $v) {
        $found[$k] = ($found[$k] ?? 0) + 1;
    }
    var_dump($found["alpha"] ?? 0);
    var_dump($found["beta"] ?? 0);
    var_dump(count($found) === 2);

    unset($map["alpha"]);
    // verify alpha gone, beta remains
    $found = [];
    foreach ($map as $k => $v) {
        $found[$k] = ($found[$k] ?? 0) + 1;
    }
    var_dump(($found["alpha"] ?? 0) === 0);
    var_dump(($found["beta"] ?? 0) === 1);
    var_dump(count($found) === 1);
}
?>
--EXPECT--
bool(true)
bool(true)
int(1)
int(1)
bool(true)
bool(true)
bool(true)
bool(true)

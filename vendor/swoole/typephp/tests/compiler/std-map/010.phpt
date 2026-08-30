--TEST--
std map: unset
--FILE--
<?php
function main() {
    $map = std::map(Type::String, Type::Int);
    $map["alpha"] = 10;
    $map["beta"] = 20;
    $map["gamma"] = 30;

    // Windows and linux std::map implementation may have different iteration order, so we cannot verify the order of keys. Instead, we verify the count and existence of keys.

    // verify all keys exist with correct values
    $found = [];
    foreach ($map as $k => $v) {
        $found[$k] = ($found[$k] ?? 0) + 1;
    }
    var_dump(($found["alpha"] ?? 0) === 1);
    var_dump(($found["beta"] ?? 0) === 1);
    var_dump(($found["gamma"] ?? 0) === 1);
    var_dump(count($found) === 3);

    unset($map["beta"]);
    // verify beta gone, alpha and gamma remain
    $found = [];
    foreach ($map as $k => $v) {
        $found[$k] = ($found[$k] ?? 0) + 1;
    }
    var_dump(($found["alpha"] ?? 0) === 1);
    var_dump(($found["beta"] ?? 0) === 0);
    var_dump(($found["gamma"] ?? 0) === 1);
    var_dump(count($found) === 2);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)

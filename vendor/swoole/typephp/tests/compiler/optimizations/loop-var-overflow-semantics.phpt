--TEST--
Loop var optimizer preserves PHP overflow-to-float semantics without native_types
--FILE--
<?php
function main(): void {
    $value = 0;

    for ($i = 9223372036854775806; $i < 9223372036854775807; $i++) {
        $value = $i + 2;
    }

    var_dump($i);
    var_dump(is_float($value));
}
?>
--EXPECT--
int(9223372036854775807)
bool(true)

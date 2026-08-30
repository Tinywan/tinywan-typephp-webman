--TEST--
internal function with union return type does not crash type detection
--FILE--
<?php
function main()
{
    $funcs = get_extension_funcs('date');
    var_dump(is_array($funcs));

    if (get_extension_funcs('extension_does_not_exist')) {
        echo "unexpected\n";
    } else {
        echo "false branch\n";
    }

    $count = count(get_extension_funcs('date') ?: []);
    var_dump($count > 0);

    $tz = new DateTimeZone('UTC');
    $transitions = $tz->getTransitions(0, 1);
    var_dump(is_array($transitions));
}
?>
--EXPECT--
bool(true)
false branch
bool(true)
bool(true)

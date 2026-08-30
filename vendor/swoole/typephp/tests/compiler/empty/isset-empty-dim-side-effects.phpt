--TEST--
isset and empty evaluate array dimension expressions in order
--FILE--
<?php

function dim_key(string $key): string
{
    echo "key:$key\n";
    return $key;
}

function main(): void
{
    $data = [
        'user' => ['name' => 'Alice'],
        'zero' => 0,
    ];

    var_dump(isset($data[dim_key('user')][dim_key('name')]));
    var_dump(isset($data[dim_key('missing')][dim_key('nested')]));
    var_dump(empty($data[dim_key('zero')]));
    var_dump(empty($data[dim_key('missing')][dim_key('empty-nested')]));
}
?>
--EXPECT--
key:user
key:name
bool(true)
key:missing
key:nested
bool(false)
key:zero
bool(true)
key:missing
key:empty-nested
bool(true)

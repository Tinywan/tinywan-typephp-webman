--TEST--
TypePHP values convert recursively at the Python call boundary
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php

function main(): void
{
    $values = [
        null,
        true,
        42,
        1.5,
        '你好',
        [],
        [1, 2],
        ['name' => 'TypePHP'],
        ['nested' => [1, ['ok' => true]]],
    ];

    foreach ($values as $value) {
        echo python\scalar(python\repr($value))->toString(), "\n";
    }
}
?>
--EXPECT--
None
True
42
1.5
'你好'
[]
[1, 2]
{'name': 'TypePHP'}
{'nested': [1, {'ok': True}]}

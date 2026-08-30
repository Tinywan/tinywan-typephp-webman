--TEST--
Native class: include scope does not expose raw native pointers to ZendVM
--FILE--
<?php

#[Native]
class NativeIncludeScopeValue {}

function main(): void
{
    $nativeValue = new NativeIncludeScopeValue();
    include __DIR__ . '/include-native-scope.inc';
    var_dump($nativeValue instanceof NativeIncludeScopeValue);
}

?>
--EXPECT--
bool(false)
bool(true)

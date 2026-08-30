--TEST--
native generator and native foreach interoperate
--FILE--
<?php
function native_child(): iterable
{
    yield 'child-a' => 1;
    yield 'child-b' => 2;
    return 3;
}

function native_parent(): iterable
{
    yield 'parent-start' => 0;
    $ret = yield from native_child();
    yield 'parent-ret' => $ret;
}

function main(): void
{
    foreach (native_parent() as $key => $value) {
        echo $key, ':', $value, "\n";
    }
}
?>
--EXPECT--
parent-start:0
child-a:1
child-b:2
parent-ret:3

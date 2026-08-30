--TEST--
AOT keyword methods toAny() and toRef()
--FILE--
<?php

function append_text(&$value, string $suffix): void
{
    $value .= $suffix;
}

function set_value(&$value, $newValue): void
{
    $value = $newValue;
}

function set_named(string $label, &$value): void
{
    $value = $label;
}

function main(): void
{
    $a = 10;
    $b = 4;
    var_dump($a->toAny() / $b->toAny());

    $name = 'php ';
    append_text($name->toRef(), 'keyword');
    var_dump($name);

    $arr = ['key' => 'original'];
    set_value($arr['key']->toRef(), 'array');
    var_dump($arr['key']);

    $obj = new stdClass();
    $obj->prop = 'object';
    append_text($obj->prop->toRef(), ' property');
    var_dump($obj->prop);

    $fn = 'set_value';
    $dynamic = 'old';
    $fn($dynamic->toRef(), 'dynamic');
    var_dump($dynamic);

    $named = 'old';
    $fn = 'set_named';
    $fn(label: 'named', value: $named->toRef());
    var_dump($named);
}
?>
--EXPECT--
float(2.5)
string(11) "php keyword"
string(5) "array"
string(15) "object property"
string(7) "dynamic"
string(5) "named"

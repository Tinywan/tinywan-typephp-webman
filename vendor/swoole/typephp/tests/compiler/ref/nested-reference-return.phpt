--TEST--
Reference-returning functions preserve aliases to nested array elements and object properties
--FILE--
<?php

final class NestedReferenceBox
{
    public string $value = 'object-before';
}

function &array_element_ref(array &$values): mixed
{
    return $values['item'];
}

function &nested_array_element_ref(array &$values): mixed
{
    return $values['outer']['inner'];
}

function &object_property_ref(NestedReferenceBox $box): mixed
{
    return $box->value;
}

function main(): void
{
    $values = [
        'item' => 'before',
        'outer' => ['inner' => 'nested-before'],
    ];

    $item =& array_element_ref($values);
    $item = 'after';
    var_dump($values['item']);

    $inner =& nested_array_element_ref($values);
    $inner = 'nested-after';
    var_dump($values['outer']['inner']);

    $box = new NestedReferenceBox();
    $property =& object_property_ref($box);
    $property = 'object-after';
    var_dump($box->value);
}
?>
--EXPECT--
string(5) "after"
string(12) "nested-after"
string(12) "object-after"

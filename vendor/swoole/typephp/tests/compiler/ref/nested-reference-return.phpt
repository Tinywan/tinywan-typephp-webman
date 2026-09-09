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
    $fixedValues = ['item' => 'fixed-before'];
    $fixedItem =& array_element_ref($fixedValues);
    $fixedItem = 'fixed-after';
    var_dump($fixedValues['item']);

    $values = std::any([
        'item' => 'before',
        'outer' => ['inner' => 'nested-before'],
    ]);

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
string(11) "fixed-after"
string(5) "after"
string(12) "nested-after"
string(12) "object-after"

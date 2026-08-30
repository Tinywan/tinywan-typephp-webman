--TEST--
Native any properties support PHP references without runtime type dispatch
--FILE--
<?php

#[Native]
class NativeAnyReference
{
    public any $value = 1;
    public ?NativeAnyReference $child;
}

function replaceAny(mixed &$value, mixed $replacement): void
{
    $value = $replacement;
}

function &getNativeAnyReference(NativeAnyReference $object): mixed
{
    return $object->value;
}

function main(): void
{
    $object = new NativeAnyReference();
    $reference =& $object->value;
    $reference = 'changed';
    var_dump($object->value);

    $object->value = 42;
    var_dump($reference);

    $object->child = new NativeAnyReference();
    $childReference =& $object->child->value;
    replaceAny($childReference, ['native', 'reference']);
    var_dump($object->child->value);

    replaceAny($object->child->value, 'direct argument');
    var_dump($childReference);

    $source = 'assigned reference';
    $object->value =& $source;
    $source = 'source changed';
    var_dump($object->value);

    $returnedReference =& getNativeAnyReference($object);
    $returnedReference = 'returned reference';
    var_dump($source);
}

?>
--EXPECT--
string(7) "changed"
int(42)
array(2) {
  [0]=>
  string(6) "native"
  [1]=>
  string(9) "reference"
}
string(15) "direct argument"
string(14) "source changed"
string(18) "returned reference"

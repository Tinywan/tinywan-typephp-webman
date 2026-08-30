--TEST--
dynamically typed array element assignment preserves references and ArrayAccess writes
--FILE--
<?php
function writeElement(mixed $container, mixed $key, mixed $value): void
{
    $container[$key] = $value;
}

function writeReferencedContainer(mixed &$container, mixed $value): void
{
    $container[0] = $value;
}

function main()
{
    $referenced = 10;
    $array = [&$referenced];
    writeElement($array, 0, 123);
    var_dump($referenced, $array[0]);

    $referencedAgain = 20;
    $arrayByReference = [&$referencedAgain];
    writeReferencedContainer($arrayByReference, 234);
    var_dump($referencedAgain, $arrayByReference[0]);

    $object = new ArrayObject();
    writeElement($object, 'key', 456);
    var_dump($object['key']);
}
?>
--EXPECT--
int(123)
int(123)
int(234)
int(234)
int(456)

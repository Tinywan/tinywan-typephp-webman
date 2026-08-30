--TEST--
Native class: Stream properties use fixed fields with runtime type validation
--FILE--
<?php

#[Native]
class NativeStreamProperty
{
    public Stream $stream;
}

function assignStreamFromMixed(NativeStreamProperty $object, mixed $value): void
{
    $object->stream = $value;
}

function main(): void
{
    $object = new NativeStreamProperty();
    var_dump($object->stream);

    $stream = fopen('php://memory', 'w+');
    assignStreamFromMixed($object, $stream);
    fwrite($object->stream, 'native stream');
    rewind($object->stream);
    echo stream_get_contents($object->stream), "\n";

    try {
        assignStreamFromMixed($object, 42);
    } catch (TypeError $error) {
        echo "invalid stream rejected\n";
    }
}
?>
--EXPECT--
NULL
native stream
invalid stream rejected

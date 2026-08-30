--TEST--
attribute strings must not be rewritten through pseudo-type aliases
--FILE--
<?php

#[Attribute(Attribute::TARGET_CLASS)]
final class StringMetadata
{
    public function __construct(
        public string $stream,
        public string $any,
        public string $box,
    ) {
    }
}

#[StringMetadata(stream: 'stream', any: 'any', box: 'box')]
final class AttributedClass
{
}

function main(): void
{
    $attribute = (new ReflectionClass(AttributedClass::class))->getAttributes()[0];
    var_dump($attribute->getArguments());

    $metadata = $attribute->newInstance();
    var_dump($metadata->stream, $metadata->any, $metadata->box);
}
?>
--EXPECT--
array(3) {
  ["stream"]=>
  string(6) "stream"
  ["any"]=>
  string(3) "any"
  ["box"]=>
  string(3) "box"
}
string(6) "stream"
string(3) "any"
string(3) "box"

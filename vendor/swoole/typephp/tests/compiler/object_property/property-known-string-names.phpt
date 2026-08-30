--TEST--
property names must not be rewritten through pseudo-type string aliases
--FILE--
<?php

final class KnownStringProperties
{
    private mixed $mixed = 'mixed';
    private mixed $stream = 'initial-stream';
    private mixed $any = 'initial-any';
    private mixed $box = 'initial-box';
    private mixed $int = 42;
    private mixed $string = 'string';
    private mixed $handleStream = 'camel-case';

    public function update(): void
    {
        $this->stream = 'updated-stream';
        $this->any = 'updated-any';
        $this->box = 'updated-box';
    }

    public function values(): array
    {
        return [
            $this->mixed,
            $this->stream,
            $this->any,
            $this->box,
            $this->int,
            $this->string,
            $this->handleStream,
        ];
    }
}

function main(): void
{
    $object = new KnownStringProperties();
    var_dump($object->values());
    $object->update();
    var_dump($object->values());

    $reflection = new ReflectionClass(KnownStringProperties::class);
    $names = array_map(
        static fn (ReflectionProperty $property): string => $property->getName(),
        $reflection->getProperties(),
    );
    sort($names);
    var_dump($names);
}
?>
--EXPECT--
array(7) {
  [0]=>
  string(5) "mixed"
  [1]=>
  string(14) "initial-stream"
  [2]=>
  string(11) "initial-any"
  [3]=>
  string(11) "initial-box"
  [4]=>
  int(42)
  [5]=>
  string(6) "string"
  [6]=>
  string(10) "camel-case"
}
array(7) {
  [0]=>
  string(5) "mixed"
  [1]=>
  string(14) "updated-stream"
  [2]=>
  string(11) "updated-any"
  [3]=>
  string(11) "updated-box"
  [4]=>
  int(42)
  [5]=>
  string(6) "string"
  [6]=>
  string(10) "camel-case"
}
array(7) {
  [0]=>
  string(3) "any"
  [1]=>
  string(3) "box"
  [2]=>
  string(12) "handleStream"
  [3]=>
  string(3) "int"
  [4]=>
  string(5) "mixed"
  [5]=>
  string(6) "stream"
  [6]=>
  string(6) "string"
}

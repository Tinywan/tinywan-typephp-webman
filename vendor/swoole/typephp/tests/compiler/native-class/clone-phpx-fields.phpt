--TEST--
Native class: clone preserves PHPX field semantics and traces shared native children
--FILE--
<?php

#[Native]
class NativeCloneChild
{
    public string $name = 'child';
}

#[Native]
class NativeCloneFields
{
    public string $name = 'source';
    public array $values = [1];
    public object $object;
    public Stream $stream;
    public mixed $mixed = null;
    public NativeCloneChild $child;
}

function createClone(): NativeCloneFields
{
    $source = new NativeCloneFields();
    $source->object = (object) ['value' => 1];
    $source->stream = fopen('php://memory', 'w+');
    $source->mixed = ['mixed'];
    $source->child = new NativeCloneChild();

    $copy = clone $source;
    $copy->name = 'copy';
    $copy->values[] = 2;
    $copy->object->value = 2;
    fwrite($copy->stream, 'shared');
    $copy->mixed[] = 'copy';

    // Allocate enough garbage to exercise tracing of the cloned Native child.
    for ($i = 0; $i < 10000; $i++) {
        new NativeCloneChild();
    }

    var_dump($source->name, $source->values, $source->object->value, $source->mixed);
    rewind($source->stream);
    echo stream_get_contents($source->stream), "\n";
    echo $copy->child->name, "\n";
    return $copy;
}

function main(): void
{
    $copy = createClone();
    var_dump($copy->name, $copy->values, $copy->object->value, $copy->mixed);
}
?>
--EXPECT--
string(6) "source"
array(1) {
  [0]=>
  int(1)
}
int(2)
array(1) {
  [0]=>
  string(5) "mixed"
}
shared
child
string(4) "copy"
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
int(2)
array(2) {
  [0]=>
  string(5) "mixed"
  [1]=>
  string(4) "copy"
}

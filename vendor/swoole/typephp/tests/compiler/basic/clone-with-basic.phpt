--TEST--
PHP 8.5 clone-with supports one/two arguments and preserves evaluation order
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip requires PHP 8.5');
}
?>
--FILE--
<?php

class CloneWithRecord
{
    public function __construct(
        public string $name,
        public int $revision,
        public array $tags,
    ) {}

    public function __clone(): void
    {
        echo "__clone:", $this->name, "\n";
        $this->revision++;
    }
}

function clone_with_source(CloneWithRecord $source): CloneWithRecord
{
    echo "source\n";
    return $source;
}

function clone_with_updates(): array
{
    echo "updates\n";
    return ['name' => 'dynamic', 'revision' => 30];
}

function main(): void
{
    $source = new CloneWithRecord('original', 1, ['source']);

    $plain = clone($source);
    $literal = clone($source, [
        'name' => 'literal',
        'tags' => ['literal'],
    ]);
    $dynamic = \clone(clone_with_source($source), clone_with_updates());
    $named = clone(object: $source, withProperties: ['name' => 'named']);
    $unpacked = clone(...[
        'object' => $source,
        'withProperties' => ['name' => 'unpacked'],
    ]);

    var_dump($source->name, $source->revision, $source->tags);
    var_dump($plain->name, $plain->revision, $plain->tags);
    var_dump($literal->name, $literal->revision, $literal->tags);
    var_dump($dynamic->name, $dynamic->revision, $dynamic->tags);
    var_dump($named->name, $named->revision);
    var_dump($unpacked->name, $unpacked->revision);
}
?>
--EXPECT--
__clone:original
__clone:original
source
updates
__clone:original
__clone:original
__clone:original
string(8) "original"
int(1)
array(1) {
  [0]=>
  string(6) "source"
}
string(8) "original"
int(2)
array(1) {
  [0]=>
  string(6) "source"
}
string(7) "literal"
int(2)
array(1) {
  [0]=>
  string(7) "literal"
}
string(7) "dynamic"
int(30)
array(1) {
  [0]=>
  string(6) "source"
}
string(5) "named"
int(2)
string(8) "unpacked"
int(2)

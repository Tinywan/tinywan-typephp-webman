--TEST--
readonly properties may be reinitialized once while cloning
--FILE--
<?php

use native_types;

class ReadonlyCloneBase
{
    public readonly int $base;

    public function __construct()
    {
        $this->base = 1;
    }

    public function __clone(): void
    {
        $this->base = 5;
    }
}

class ReadonlyCloneValue extends ReadonlyCloneBase
{
    public readonly string $name;
    public readonly array $items;

    public function __construct()
    {
        parent::__construct();
        $this->name = 'original';
        $this->items = [1];
    }

    public function __clone(): void
    {
        parent::__clone();
        $this->name = 'cloned';
        $this->items = [10, 2];
        try {
            $this->name = 'again';
        } catch (Error $error) {
            echo $error->getMessage(), "\n";
        }
    }
}

function main(): void
{
    $original = new ReadonlyCloneValue();
    $copy = clone $original;
    var_dump($original->base, $original->name, $original->items);
    var_dump($copy->base, $copy->name, $copy->items);
}
?>
--EXPECT--
Cannot modify readonly property ReadonlyCloneValue::$name
int(1)
string(8) "original"
array(1) {
  [0]=>
  int(1)
}
int(5)
string(6) "cloned"
array(2) {
  [0]=>
  int(10)
  [1]=>
  int(2)
}

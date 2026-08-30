--TEST--
foreach plain objects preserves scope, live properties, references, and typed-property rules
--FILE--
<?php

class ForeachScopeParent
{
    public int $public = 1;
    protected int $protected = 2;
    private int $parentPrivate = 3;
}

class ForeachScopeChild extends ForeachScopeParent
{
    private int $childPrivate = 4;
    public int $typed = 5;
    public readonly int $readonly;
    public string $uninitialized;

    public function __construct()
    {
        $this->readonly = 6;
    }

    public function visibleProperties(): array
    {
        $seen = [];
        foreach ($this as $key => $value) {
            $seen[$key] = $value;
        }
        return $seen;
    }
}

class ForeachTypedOnly
{
    public int $number = 7;
}

function main(): void
{
    $object = (object) ['a' => 1, 'b' => 2];
    $seen = [];
    foreach ($object as $key => $value) {
        $seen[$key] = $value;
        if ($key === 'a') {
            unset($object->b);
            $object->c = 3;
        }
    }
    var_dump($seen, $object->a, $object->c);

    foreach ($object as &$value) {
        $value *= 10;
    }
    unset($value);
    var_dump($object->a, $object->c);

    $scoped = new ForeachScopeChild();
    var_dump($scoped->visibleProperties());

    try {
        foreach ($scoped as &$value) {
        }
    } catch (Error $error) {
        var_dump(str_contains($error->getMessage(), 'readonly property'));
    }

    $typed = new ForeachTypedOnly();
    try {
        foreach ($typed as &$value) {
            $value = 'invalid';
        }
    } catch (TypeError $error) {
        var_dump(str_contains($error->getMessage(), 'int'));
    }
    var_dump($typed->number);

}
?>
--EXPECT--
array(2) {
  ["a"]=>
  int(1)
  ["c"]=>
  int(3)
}
int(1)
int(3)
int(10)
int(30)
array(6) {
  ["public"]=>
  int(1)
  ["protected"]=>
  int(2)
  ["childPrivate"]=>
  int(4)
  ["typed"]=>
  int(5)
  ["readonly"]=>
  int(6)
  ["uninitialized"]=>
  string(0) ""
}
bool(true)
bool(true)
int(7)

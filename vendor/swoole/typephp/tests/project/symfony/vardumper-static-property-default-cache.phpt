--TEST--
Symfony pattern: static property default cache keyed by runtime class
--XFAIL--
Known AOT bug: coalesce assignment with a ternary right-hand expression can cache the condition result instead of the full ternary value.
--FILE--
<?php

class StubLike
{
    private static array $propertyDefaults = [];

    public string $name = 'default';
    public ?int $count;
    public mixed $extra = null;

    public function __construct(string $name, ?int $count, mixed $extra = null)
    {
        $this->name = $name;
        $this->count = $count;
        $this->extra = $extra;
    }

    public function __serialize(): array
    {
        static $noDefault = new stdClass();

        $data = [];
        foreach ($this as $k => $v) {
            $default = self::$propertyDefaults[$this::class][$k] ??= ($p = new ReflectionProperty($this, $k))->hasDefaultValue()
                ? $p->getDefaultValue()
                : ($p->hasType() ? $noDefault : null);

            if ($noDefault === $default || $default !== $v) {
                $data[$k] = $v;
            }
        }

        return $data;
    }
}

function main(): void
{
    var_dump((new StubLike('default', null))->__serialize());
    var_dump((new StubLike('changed', 3, ['tag']))->__serialize());
}
?>
--EXPECT--
array(1) {
  ["count"]=>
  NULL
}
array(3) {
  ["name"]=>
  string(7) "changed"
  ["count"]=>
  int(3)
  ["extra"]=>
  array(1) {
    [0]=>
    string(3) "tag"
  }
}

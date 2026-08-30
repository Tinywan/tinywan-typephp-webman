--TEST--
Native class: properties without explicit defaults use their type zero values
--FILE--
<?php

#[Native]
class NativeZeroValue
{
    public bool $enabled;
    public int $count;
    public float $ratio;
    public string $name;
    public array $items;
    public mixed $value;
    public Stream $stream;
    public stdClass $requiredObject;
    public ?stdClass $object;
    public ?NativeZeroValue $child;
}

function main(): void
{
    $value = new NativeZeroValue();
    var_dump(
        $value->enabled,
        $value->count,
        $value->ratio,
        $value->name,
        $value->items,
        $value->value,
        $value->stream,
        $value->requiredObject,
        $value->object,
        $value->child === null,
    );
}
?>
--EXPECT--
bool(false)
int(0)
float(0)
string(0) ""
array(0) {
}
NULL
NULL
NULL
NULL
bool(true)

--TEST--
Native class: property hooks preserve native pointer types and temporary roots
--FILE--
<?php

#[Native]
class NativeHookObjectValue
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

#[Native]
class NativeHookObjectPressure
{
    public int $value;
}

#[Native]
class NativeHookObjectOwner
{
    private ?NativeHookObjectValue $stored;

    public ?NativeHookObjectValue $value {
        get {
            return $this->stored;
        }
        set(?NativeHookObjectValue $value) {
            $this->stored = $value;
        }
    }
}

function makeNativeHookValueAfterPressure(string $name): NativeHookObjectValue
{
    for ($i = 0; $i < 300000; $i++) {
        $filler = new NativeHookObjectPressure();
    }
    return new NativeHookObjectValue($name);
}

function consumeNativeHookValues(
    NativeHookObjectValue $first,
    NativeHookObjectValue $second,
): void {
    echo $first->name, ':', $second->name, PHP_EOL;
}

function main(): void
{
    $owner = new NativeHookObjectOwner();
    $owner->value = new NativeHookObjectValue('first');
    consumeNativeHookValues(
        $owner->value,
        makeNativeHookValueAfterPressure('second'),
    );
    $owner->value = null;
    echo $owner->value === null ? "NULL\n" : "not-null\n";
}

?>
--EXPECT--
first:second
NULL

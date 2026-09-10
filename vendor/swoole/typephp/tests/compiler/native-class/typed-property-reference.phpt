--TEST--
Native class fixed properties bind directly to strong typed references
--FILE--
<?php

#[Native]
class NativeTypedReferenceValues
{
    public int $number = 1;
    public float $decimal = 1.5;
    public bool $enabled = false;
    public string $text = 'native';
    public array $items = [1];
    public ?NativeTypedReferenceValues $child;

    public function increment(int &$value): void
    {
        $value++;
    }

    public function mutateOwnNumber(): void
    {
        mutateNativeInt($this->number);
    }
}

function mutateNativeInt(int &$value): void
{
    $value += 10;
}

function mutateNativeFloat(float &$value): void
{
    $value += 0.25;
}

function mutateNativeBool(bool &$value): void
{
    $value = !$value;
}

function mutateNativeString(string &$value): void
{
    $value .= ':changed';
}

function mutateNativeArray(array &$value): void
{
    $value[] = 2;
}

function main(): void
{
    $object = new NativeTypedReferenceValues();
    mutateNativeInt($object->number);
    mutateNativeFloat($object->decimal);
    mutateNativeBool($object->enabled);
    mutateNativeString($object->text);
    mutateNativeArray($object->items);
    var_dump($object->number, $object->decimal, $object->enabled, $object->text, $object->items);

    $object->increment($object->number);
    $object->mutateOwnNumber();
    var_dump($object->number);

    $object->child = new NativeTypedReferenceValues();
    mutateNativeInt(value: $object->child->number);
    var_dump($object->child->number);
}

?>
--EXPECT--
int(11)
float(1.75)
bool(true)
string(14) "native:changed"
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
int(22)
int(11)

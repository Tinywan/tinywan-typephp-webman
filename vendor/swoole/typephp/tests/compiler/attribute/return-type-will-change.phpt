--TEST--
ReturnTypeWillChange attribute on internal interface methods
--FILE--
<?php

class ReturnTypeWillChangeBox implements ArrayAccess
{
    private array $items = [];

    #[\ReturnTypeWillChange]
    public function offsetExists(mixed $offset)
    {
        return isset($this->items[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet(mixed $offset)
    {
        return $this->items[$offset] ?? null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value)
    {
        if ($offset === null) {
            $this->items[] = $value;
            return;
        }
        $this->items[$offset] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset(mixed $offset)
    {
        unset($this->items[$offset]);
    }
}

function main(): void
{
    $box = new ReturnTypeWillChangeBox();
    $box['name'] = 'aot';
    var_dump(isset($box['name']));
    var_dump($box['name']);
    unset($box['name']);
    var_dump(isset($box['name']));

    $method = new ReflectionMethod(ReturnTypeWillChangeBox::class, 'offsetGet');
    $attrs = $method->getAttributes(ReturnTypeWillChange::class);
    var_dump($attrs[0]->getName());
}
?>
--EXPECT--
bool(true)
string(3) "aot"
bool(false)
string(20) "ReturnTypeWillChange"

--TEST--
dynamic call uses wrapper interface parameter type check
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

interface InterfaceDynamicCallCheckContract
{
    public function name(): string;
}

class InterfaceDynamicCallCheckImpl implements InterfaceDynamicCallCheckContract
{
    public function name(): string
    {
        return 'impl';
    }
}

class InterfaceDynamicCallCheckOther
{
}

function acceptInterfaceDynamicCallCheck(InterfaceDynamicCallCheckContract $object): string
{
    return $object->name();
}

function callDynamic($callback, $object): void
{
    try {
        var_dump($callback($object));
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
}

function main(): void
{
    $callback = 'acceptInterfaceDynamicCallCheck';
    callDynamic($callback, new InterfaceDynamicCallCheckImpl());
    callDynamic($callback, new InterfaceDynamicCallCheckOther());
}
?>
--EXPECT--
string(4) "impl"
The parameter `object` must be instance of class `InterfaceDynamicCallCheckContract`, object of `InterfaceDynamicCallCheckOther` given

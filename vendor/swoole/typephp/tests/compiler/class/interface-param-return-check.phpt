--TEST--
interface parameter and return type checks with dynamic object values
--FILE--
<?php

interface InterfaceCallCheckContract
{
    public function name(): string;
}

class InterfaceCallCheckImpl implements InterfaceCallCheckContract
{
    public function name(): string
    {
        return 'impl';
    }
}

class InterfaceCallCheckOther
{
    public function name(): string
    {
        return 'other';
    }
}

function useInterfaceCallCheck(InterfaceCallCheckContract $object): string
{
    return $object->name();
}

function returnInterfaceCallCheck($object): InterfaceCallCheckContract
{
    return $object->toAny();
}

function main(): void
{
    $impl = new InterfaceCallCheckImpl();
    $other = new InterfaceCallCheckOther();

    var_dump(useInterfaceCallCheck($impl->toAny()));
    try {
        var_dump(useInterfaceCallCheck($other->toAny()));
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }

    var_dump(returnInterfaceCallCheck(new InterfaceCallCheckImpl())->name());
    try {
        var_dump(returnInterfaceCallCheck(new InterfaceCallCheckOther())->name());
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
string(4) "impl"
The parameter `object` must be instance of class `InterfaceCallCheckContract`, object of `InterfaceCallCheckOther` given
string(4) "impl"
The parameter `object` must be instance of class `InterfaceCallCheckContract`, object of `InterfaceCallCheckOther` given

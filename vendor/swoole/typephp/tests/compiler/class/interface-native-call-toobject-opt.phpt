--TEST--
interface parameter native call avoids redundant object check when statically safe
--FILE--
<?php

interface InterfaceNativeCallOptContract
{
    public function name(): string;
}

class InterfaceNativeCallOptImpl implements InterfaceNativeCallOptContract
{
    public function name(): string
    {
        return 'impl';
    }
}

class InterfaceNativeCallOptOther
{
}

function acceptInterfaceNativeCallOpt(InterfaceNativeCallOptContract $object): string
{
    return $object->name();
}

function main(): void
{
    $impl = new InterfaceNativeCallOptImpl();
    var_dump(acceptInterfaceNativeCallOpt($impl));

    try {
        $other = new InterfaceNativeCallOptOther();
        var_dump(acceptInterfaceNativeCallOpt($other->toAny()));
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
string(4) "impl"
The parameter `object` must be instance of class `InterfaceNativeCallOptContract`, object of `InterfaceNativeCallOptOther` given

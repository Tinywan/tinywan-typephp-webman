--TEST--
interface declared object constrains assignment without native call typing
--FILE--
<?php

interface DeclaredObjectContract
{
    public function name(): string;
}

class DeclaredObjectImpl implements DeclaredObjectContract
{
    public function name(): string
    {
        return 'impl';
    }
}

class DeclaredObjectOther
{
    public function name(): string
    {
        return 'other';
    }
}

function nextDeclaredObject(DeclaredObjectContract $object): DeclaredObjectContract
{
    return $object;
}

function testDeclaredObject(DeclaredObjectContract $object): void
{
    var_dump($object->name());

    $object = new DeclaredObjectImpl();
    var_dump($object->name());

    $object = nextDeclaredObject($object);
    var_dump($object->name());

    try {
        $object = any(new DeclaredObjectOther());
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
    var_dump($object->name());
}

function main(): void
{
    testDeclaredObject(new DeclaredObjectImpl());
}
?>
--EXPECT--
string(4) "impl"
string(4) "impl"
string(4) "impl"
The parameter `object` must be instance of class `DeclaredObjectContract`, object of `DeclaredObjectOther` given
string(4) "impl"

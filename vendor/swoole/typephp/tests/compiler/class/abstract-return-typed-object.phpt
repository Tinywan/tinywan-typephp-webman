--TEST--
abstract class return type can be used as typed object
--FILE--
<?php

abstract class AbstractTypedObjectBase
{
    public function concreteName(): string
    {
        return 'base:' . $this->name();
    }

    abstract public function name(): string;
}

class AbstractTypedObjectImpl extends AbstractTypedObjectBase
{
    public function name(): string
    {
        return 'impl';
    }
}

function makeAbstractTypedObject(): AbstractTypedObjectBase
{
    return new AbstractTypedObjectImpl();
}

function main(): void
{
    $object = makeAbstractTypedObject();
    var_dump($object->concreteName());
    var_dump($object->name());
}
?>
--EXPECT--
string(9) "base:impl"
string(4) "impl"

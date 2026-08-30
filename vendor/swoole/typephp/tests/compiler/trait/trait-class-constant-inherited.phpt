--TEST--
Trait class constants remain bound to the composing class when inherited
--FILE--
<?php
trait ClassIdentityTrait
{
    public function magicClass(): string
    {
        return __CLASS__;
    }

    public function selfClass(): string
    {
        return self::class;
    }
}

class Base
{
    use ClassIdentityTrait;
}

class Child extends Base
{
}

function main(): void
{
    $object = new Child();
    var_dump($object->magicClass());
    var_dump($object->selfClass());
}
?>
--EXPECT--
string(4) "Base"
string(4) "Base"

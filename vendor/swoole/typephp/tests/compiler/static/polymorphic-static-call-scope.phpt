--TEST--
Polymorphic object static call preserves lexical scope for protected methods
--FILE--
<?php

class Base
{
    protected static function identify(): string
    {
        return static::class;
    }

    public static function who(): string
    {
        return static::class;
    }

    public static function exerciseStatic(Base $obj): string
    {
        return $obj::identify();
    }

    public function exerciseInstance(Base $obj): string
    {
        return $obj::identify();
    }
}

class Child extends Base
{
}

class SubChild extends Child
{
    public static function who(): string
    {
        return 'subchild:' . static::class;
    }
}

function makeChild(): Base
{
    return new Child();
}

function makeSubChild(): Base
{
    return new SubChild();
}

function globalCallPublic(Base $obj): string
{
    return $obj::who();
}

function main(): void
{
    $child = makeChild();
    var_dump(Base::exerciseStatic($child));

    $base = new Base();
    var_dump($base->exerciseInstance($child));

    $sub = makeSubChild();
    var_dump(Base::exerciseStatic($sub));
    var_dump($base->exerciseInstance($sub));

    var_dump(globalCallPublic($child));
    var_dump(globalCallPublic($sub));
}
?>
--EXPECT--
string(5) "Child"
string(5) "Child"
string(8) "SubChild"
string(8) "SubChild"
string(5) "Child"
string(17) "subchild:SubChild"

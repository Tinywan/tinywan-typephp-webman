--TEST--
typed object assignment from any uses runtime class check
--FILE--
<?php

class AssignAnyBase
{
    public function name(): string
    {
        return 'base';
    }
}

class AssignAnyChild extends AssignAnyBase
{
}

class AssignAnyBaseFactory
{
    public function child(): AssignAnyBase
    {
        return new AssignAnyChild();
    }

    public function base(): AssignAnyBase
    {
        return new AssignAnyBase();
    }
}

interface AssignAnyInterface
{
    public function next(): AssignAnyInterface;
}

class AssignAnyImpl implements AssignAnyInterface
{
    public function next(): AssignAnyInterface
    {
        return $this;
    }

    public function ok(): string
    {
        return 'impl';
    }
}

class AssignAnyOther implements AssignAnyInterface
{
    public function next(): AssignAnyInterface
    {
        return $this;
    }
}

function main(): void
{
    $base = new AssignAnyBase();
    $base = any(new AssignAnyChild());
    var_dump($base->name());

    $child = new AssignAnyChild();
    try {
        $child = any(new AssignAnyBase());
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
    var_dump($child->name());

    $child = new AssignAnyChild();
    $factory = new AssignAnyBaseFactory();
    $child = $factory->child();
    var_dump($child->name());
    try {
        $child = $factory->base();
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
    var_dump($child->name());

    $base = new AssignAnyBase();
    $child = new AssignAnyChild();
    $base = $child;
    var_dump($base->name());

    $child = new AssignAnyChild();
    $base = new AssignAnyBase();
    $base = $child;
    $child = $base;
    var_dump($child->name());

    $child = new AssignAnyChild();
    $base = new AssignAnyBase();
    try {
        $child = $base;
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
    var_dump($child->name());

    $impl = new AssignAnyImpl();
    $impl = $impl->next();
    var_dump($impl->ok());

    $impl = new AssignAnyImpl();
    $other = new AssignAnyOther();
    try {
        $impl = $other->next();
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
    var_dump($impl->ok());
}
?>
--EXPECT--
string(4) "base"
The parameter `object` must be instance of class `AssignAnyChild`, object of `AssignAnyBase` given
string(4) "base"
string(4) "base"
The parameter `object` must be instance of class `AssignAnyChild`, object of `AssignAnyBase` given
string(4) "base"
string(4) "base"
string(4) "base"
The parameter `object` must be instance of class `AssignAnyChild`, object of `AssignAnyBase` given
string(4) "base"
string(4) "impl"
The parameter `object` must be instance of class `AssignAnyImpl`, object of `AssignAnyOther` given
string(4) "impl"

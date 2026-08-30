--TEST--
readonly properties use PHP one-time initialization semantics
--FILE--
<?php

class ReadonlyBase
{
    public readonly int $fromMethod;
    public readonly int $fromChild;
    public readonly int $fromClosure;

    public function initializeMethod(): void
    {
        $this->fromMethod = 10;
    }

    public function initializeClosure(): void
    {
        $writer = function (): void {
            $this->fromClosure = 30;
        };
        $writer();
    }
}

class ReadonlyChild extends ReadonlyBase
{
    public function initializeChild(): void
    {
        $this->fromChild = 20;
    }
}

class MutableValue
{
    public int $number = 0;
}

class ReadonlyObjectHolder
{
    public readonly MutableValue $value;

    public function initialize(): void
    {
        $this->value = new MutableValue();
    }
}

function main(): void
{
    $value = new ReadonlyChild();
    $value->initializeMethod();
    $value->initializeChild();
    $value->initializeClosure();
    var_dump($value->fromMethod, $value->fromChild, $value->fromClosure);

    try {
        $value->initializeMethod();
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }

    $external = new ReadonlyChild();
    try {
        $external->fromMethod = 40;
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }

    $holder = new ReadonlyObjectHolder();
    $holder->initialize();
    $holder->value->number = 50;
    var_dump($holder->value->number);
}
?>
--EXPECT--
int(10)
int(20)
int(30)
Cannot modify readonly property ReadonlyBase::$fromMethod
Cannot modify protected(set) readonly property ReadonlyBase::$fromMethod from global scope
int(50)

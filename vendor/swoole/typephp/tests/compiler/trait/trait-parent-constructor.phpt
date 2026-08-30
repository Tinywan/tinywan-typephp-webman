--TEST--
Trait constructor calling parent::__construct of the composing class
--FILE--
<?php

declare(strict_types=1);

trait TestTrait
{
    public function __construct(int $value)
    {
        parent::__construct($value, true);
    }
}

class ParentClass
{
    public function __construct(int $value, bool $bool)
    {
        var_dump($value, $bool);
    }
}

class TestClass extends ParentClass
{
    use TestTrait;
}

function main()
{
    new TestClass(123);
}
?>
--EXPECT--
int(123)
bool(true)

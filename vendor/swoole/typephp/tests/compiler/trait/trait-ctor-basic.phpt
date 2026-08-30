--TEST--
Trait __construct is used by the composing class
--FILE--
<?php

declare(strict_types=1);

trait TestTrait
{
    public function __construct()
    {
        echo "trait ctor\n";
    }
}

class TestClass
{
    use TestTrait;
}

function main()
{
    new TestClass();
}
?>
--EXPECT--
trait ctor

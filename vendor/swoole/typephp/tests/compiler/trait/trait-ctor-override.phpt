--TEST--
Class __construct overrides the one provided by a trait
--FILE--
<?php


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

    public function __construct()
    {
        echo "class ctor\n";
    }
}

function main()
{
    new TestClass();
}
?>
--EXPECT--
class ctor

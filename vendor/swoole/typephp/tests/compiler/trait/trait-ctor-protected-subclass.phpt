--TEST--
Trait protected __construct is accessible from a subclass
--FILE--
<?php


trait TestTrait
{
    protected function __construct()
    {
        echo "base ctor\n";
    }
}

class BaseClass
{
    use TestTrait;
}

class SubClass extends BaseClass
{
    public function __construct()
    {
        new BaseClass();
        echo "sub ctor\n";
    }
}

function main()
{
    new SubClass();
}
?>
--EXPECT--
base ctor
sub ctor

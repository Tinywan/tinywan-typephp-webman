--TEST--
Trait __construct with arguments and $this property access
--FILE--
<?php

declare(strict_types=1);

trait TestTrait
{
    private int $value = 0;

    public function __construct(int $value)
    {
        $this->value = $value;
        echo "value=" . $this->value . "\n";
    }
}

class TestClass
{
    use TestTrait;
}

function main()
{
    new TestClass(42);
}
?>
--EXPECT--
value=42

--TEST--
Reference-returning functions can forward dynamic and chained calls
--FILE--
<?php
function &dynamic_source(): mixed
{
    static $value = 1;
    return $value;
}

function &dynamic_forward(string $function): mixed
{
    return $function();
}

class DynamicRefBox
{
    public int $value = 2;

    public function &getValue(): mixed
    {
        return $this->value;
    }
}

class DynamicRefFactory
{
    public function create(): DynamicRefBox
    {
        return new DynamicRefBox();
    }

    public function &forward(): mixed
    {
        return $this->create()->getValue();
    }
}

function main(): void
{
    $dynamic = &dynamic_forward('dynamic_source');
    $dynamic = 10;
    var_dump(dynamic_source());

    $factory = new DynamicRefFactory();
    $chained = &$factory->forward();
    var_dump($chained);
}
?>
--EXPECT--
int(10)
int(2)

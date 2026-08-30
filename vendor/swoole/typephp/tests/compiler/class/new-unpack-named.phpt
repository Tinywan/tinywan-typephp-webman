--TEST--
new object with unpack before named arguments
--FILE--
<?php
class PairValue
{
    public function __construct(public int $a, public int $b)
    {
    }

    public function show(): void
    {
        echo $this->a . ':' . $this->b . "\n";
    }
}

function main(): void
{
    $known = new PairValue(...[1], b: 2);
    $known->show();

    $class = PairValue::class;
    $dynamic = new $class(...[3], b: 4);
    $dynamic->show();
}
?>
--EXPECT--
1:2
3:4

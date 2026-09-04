--TEST--
Final int property addition uses a detached value accumulator
--FILE--
<?php
declare(strict_types=1);

final class AddChain
{
    public int $first = 1;
    public int $second = 2;
    public int $third = 3;
    public int $fourth = 4;
    public int $fifth = 5;

    public function sum(): int
    {
        return $this->first + $this->second + $this->third + $this->fourth + $this->fifth;
    }
}

function main(): void
{
    $value = new AddChain();
    $first =& $value->first;

    var_dump($value->sum());
    var_dump($value->first, $first);

    $value->first = PHP_INT_MAX;
    $value->second = 1;
    $value->third = 0;
    $value->fourth = 0;
    $value->fifth = 0;
    try {
        var_dump($value->sum());
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
    var_dump($value->first, $first);
}
?>
--EXPECTF--
int(15)
int(1)
int(1)
AddChain::sum(): Return value must be of type int, float returned
int(9223372036854775807)
int(9223372036854775807)

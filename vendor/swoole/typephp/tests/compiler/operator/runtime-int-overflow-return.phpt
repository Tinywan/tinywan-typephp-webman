--TEST--
Runtime integer overflow is checked at an int return boundary
--FILE--
<?php
declare(strict_types=1);

final class OverflowProperties
{
    public int $left = 0;
    public int $right = 0;

    public function sum(): int
    {
        return $this->left + $this->right;
    }
}

function addInts(int $left, int $right): int
{
    return $left + $right;
}

function subtractInts(int $left, int $right): int
{
    return $left - $right;
}

function multiplyInts(int $left, int $right): int
{
    return $left * $right;
}

function main(): void
{
    foreach ([
        static fn (): int => addInts(PHP_INT_MAX, 1),
        static fn (): int => subtractInts(PHP_INT_MIN, 1),
        static fn (): int => multiplyInts(PHP_INT_MAX, 2),
        static function (): int {
            $value = new OverflowProperties();
            $value->left = PHP_INT_MAX;
            $value->right = 1;
            return $value->sum();
        },
    ] as $callback) {
        try {
            var_dump($callback());
        } catch (TypeError $error) {
            echo $error->getMessage(), "\n";
        }
    }
}
?>
--EXPECTF--
addInts(): Return value must be of type int, float returned
subtractInts(): Return value must be of type int, float returned
multiplyInts(): Return value must be of type int, float returned
OverflowProperties::sum(): Return value must be of type int, float returned

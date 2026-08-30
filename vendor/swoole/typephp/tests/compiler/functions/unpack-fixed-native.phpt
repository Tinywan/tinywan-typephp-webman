--TEST--
Argument unpacking can fill fixed native parameters
--FILE--
<?php

function fixed_args(int $a, int $b): void
{
    echo $a, ',', $b, PHP_EOL;
}

function fixed_and_variadic(int $a, int ...$rest): void
{
    echo $a, ':', implode(',', $rest), PHP_EOL;
}

class NativeUnpackTarget
{
    public function method(int $a, int $b): void
    {
        echo 'm=', $a, ',', $b, PHP_EOL;
    }

    public static function staticMethod(int $a, int $b): void
    {
        echo 's=', $a, ',', $b, PHP_EOL;
    }
}

function main(): void
{
    fixed_args(...[1, 2]);
    fixed_and_variadic(...[3, 4, 5]);
    fixed_and_variadic(6, ...[7, 8]);
    $target = new NativeUnpackTarget();
    $target->method(...[9, 10]);
    NativeUnpackTarget::staticMethod(...[11, 12]);
}

?>
--EXPECT--
1,2
3:4,5
6:7,8
m=9,10
s=11,12

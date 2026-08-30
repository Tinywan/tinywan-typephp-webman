--TEST--
Array element compound arithmetic promotes overflowing integers to float
--FILE--
<?php
declare(strict_types=1);

function main(): void
{
    $maximum = PHP_INT_MAX;
    $large = intdiv(PHP_INT_MAX, 2) + 1;
    $values = ['add' => $maximum, 'multiply' => $large];

    $values['add'] += 1;
    $values['multiply'] *= 2;

    var_dump($values['add'], $values['multiply']);
    echo gettype($values['add']), ',', gettype($values['multiply']), "\n";
}
?>
--EXPECT--
float(9.223372036854776E+18)
float(9.223372036854776E+18)
double,double

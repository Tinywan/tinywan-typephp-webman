--TEST--
Typed by-reference variadics validate, widen float arguments and write through unions and objects
--FILE--
<?php
declare(strict_types=1);

class Counter
{
    public function __construct(public int $value)
    {
    }
}

function scale(float &...$values): void
{
    foreach ($values as &$value) {
        $value *= 1.5;
    }
    unset($value);
}

function normalize(int|string &...$values): void
{
    foreach ($values as &$value) {
        $value = is_int($value) ? $value + 1 : strtoupper($value);
    }
    unset($value);
}

function bump_objects(Counter &...$values): void
{
    foreach ($values as $value) {
        $value->value++;
    }
}

function require_ints(int &...$values): void
{
}

function main(): void
{
    $integer = 2;
    $float = 2.5;
    scale($integer, $float);
    var_dump($integer, $float);

    $values = [4, 6.0];
    scale(...$values);
    var_dump($values);

    $number = 10;
    $text = 'hello';
    normalize($number, $text);
    var_dump($number, $text);

    $first = new Counter(1);
    $second = new Counter(5);
    bump_objects($first, $second);
    var_dump($first->value, $second->value);

    $invalid = any('not-an-int');
    try {
        require_ints($invalid);
    } catch (TypeError $error) {
        echo get_class($error), ': ', $error->getMessage(), PHP_EOL;
    }

    $invalidUnpack = ['still-not-an-int'];
    try {
        require_ints(...$invalidUnpack);
    } catch (TypeError $error) {
        echo "unpack rejected\n";
    }
    var_dump(ReflectionReference::fromArrayElement($invalidUnpack, 0));
}
?>
--EXPECTF--
float(3)
float(3.75)
array(2) {
  [0]=>
  float(6)
  [1]=>
  float(9)
}
int(11)
string(5) "HELLO"
int(2)
int(6)
TypeError: require_ints(): Argument #1 ($values) must be of type int, string given
unpack rejected
NULL

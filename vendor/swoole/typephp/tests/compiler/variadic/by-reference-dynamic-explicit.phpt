--TEST--
Dynamic calls require explicit refval for by-reference arguments
--FILE--
<?php

function dynamic_increment(&...$values): void
{
    foreach ($values as &$value) {
        $value++;
    }
    unset($value);
}

class DynamicReferenceMutator
{
    public function suffix(string $suffix, &...$values): void
    {
        foreach ($values as &$value) {
            $value .= $suffix;
        }
        unset($value);
    }
}

function main(): void
{
    $function = 'dynamic_increment';
    $number = 40;
    $function(refval($number));
    var_dump($number);

    $mutator = new DynamicReferenceMutator();
    $method = [$mutator, 'suffix'];
    $first = 'one';
    $second = 'two';
    $method('!', refval($first), refval($second));
    var_dump($first, $second);

    $closure = static function (&$value): void {
        $value .= '?';
    };
    $closure(refval($second));
    var_dump($second);
}
?>
--EXPECT--
int(41)
string(4) "one!"
string(4) "two!"
string(5) "two!?"

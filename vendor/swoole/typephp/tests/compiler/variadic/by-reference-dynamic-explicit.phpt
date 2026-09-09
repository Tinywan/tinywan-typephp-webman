--TEST--
Dynamic calls require explicit std::ref for by-reference arguments
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
    $number = std::any(40);
    $function(std::ref($number));
    var_dump($number);

    $mutator = new DynamicReferenceMutator();
    $method = [$mutator, 'suffix'];
    $first = std::any('one');
    $second = std::any('two');
    $method('!', std::ref($first), std::ref($second));
    var_dump($first, $second);

    $closure = static function (&$value): void {
        $value .= '?';
    };
    $closure(std::ref($second));
    var_dump($second);
}
?>
--EXPECT--
int(41)
string(4) "one!"
string(4) "two!"
string(5) "two!?"

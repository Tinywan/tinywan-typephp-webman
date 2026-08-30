--TEST--
Property with nullable type
--FILE--
<?php
class Foo
{
    public function __construct(public string|Stringable $value)
    {
    }
}

function main() {
    $foo = new Foo('Continue');
    var_dump($foo->value);
}
?>
--EXPECT--
string(8) "Continue"
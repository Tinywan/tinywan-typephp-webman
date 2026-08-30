--TEST--
static variable captured by reference is not redeclared as a local variable
--FILE--
<?php
function getValue(): int
{
    static $value = null;
    if ($value === null) {
        $value = 0;
        $increment = static function () use (&$value): void {
            $value++;
        };
        $increment();
    }
    return $value;
}

function main(): void
{
    var_dump(getValue());
    var_dump(getValue());
}
?>
--EXPECT--
int(1)
int(1)

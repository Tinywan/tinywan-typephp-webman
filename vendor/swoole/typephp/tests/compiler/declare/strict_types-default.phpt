--TEST--
TypePHP enables strict types without a declare directive
--FILE--
<?php
function checkStrictCaller(string $label): void
{
    $callable = 'strlen';
    try {
        $callable(123);
        echo $label, "=accepted\n";
    } catch (TypeError $error) {
        echo $label, "=TypeError\n";
    }
}

class StrictMethodCaller
{
    public function check(): void
    {
        checkStrictCaller('method');
    }
}

function main(): void
{
    checkStrictCaller('main');

    $function = 'checkStrictCaller';
    $function('function');

    $method = [new StrictMethodCaller(), 'check'];
    $method();
}
?>
--EXPECT--
main=TypeError
function=TypeError
method=TypeError

--TEST--
Function, method, static and generated helper call arguments evaluate left-to-right
--FILE--
<?php
function traced(string $label, string $value): string
{
    echo $label, "\n";
    return $value;
}

function combine(string $left, string $right): string
{
    return $left . $right;
}

function tracedCombine(string $label, string $left, string $right): string
{
    echo $label, "\n";
    return $left . $right;
}

class CallOrder
{
    public function method(string $left, string $right): string
    {
        return $left . $right;
    }

    public static function staticMethod(string $left, string $right): string
    {
        return $left . $right;
    }
}

function main(): void
{
    var_dump(combine(traced('function-left', 'a'), traced('function-right', 'b')));

    $function = 'combine';
    var_dump($function(traced('dynamic-function-left', 'i'), traced('dynamic-function-right', 'j')));
    var_dump($function(
        tracedCombine('nested-left-done', traced('nested-a', 'm'), traced('nested-b', 'n')),
        tracedCombine('nested-right-done', traced('nested-c', 'o'), traced('nested-d', 'p')),
    ));

    $object = new CallOrder();
    var_dump($object->method(traced('method-left', 'c'), traced('method-right', 'd')));

    $method = 'method';
    var_dump($object->$method(traced('dynamic-method-left', 'k'), traced('dynamic-method-right', 'l')));

    var_dump(CallOrder::staticMethod(traced('static-left', 'e'), traced('static-right', 'f')));
    var_dump(traced('concat-left', 'g') . traced('concat-right', 'h'));
}
?>
--EXPECT--
function-left
function-right
string(2) "ab"
dynamic-function-left
dynamic-function-right
string(2) "ij"
nested-a
nested-b
nested-left-done
nested-c
nested-d
nested-right-done
string(4) "mnop"
method-left
method-right
string(2) "cd"
dynamic-method-left
dynamic-method-right
string(2) "kl"
static-left
static-right
string(2) "ef"
concat-left
concat-right
string(2) "gh"

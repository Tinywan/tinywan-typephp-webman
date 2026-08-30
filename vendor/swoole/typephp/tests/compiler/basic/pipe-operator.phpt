--TEST--
PHP 8.5 pipe operator
--FILE--
<?php

function mark(string $value): string
{
    echo "left:$value\n";
    return $value;
}

function suffix(string $value): string
{
    return $value . '!';
}

class PipeFormatter
{
    public static function wrap(string $value): string
    {
        return '[' . $value . ']';
    }

    public function suffix(string $value): string
    {
        return $value . '!';
    }
}

function main(): void
{
    $callable = suffix(...);
    $result = mark(' hello ')
        |> trim(...)
        |> PipeFormatter::wrap(...)
        |> $callable
        |> (fn(string $value): string => strtoupper($value));

    $formatter = new PipeFormatter();
    $methodResult = mark('method') |> $formatter->suffix(...);

    var_dump($result);
    var_dump($methodResult);
}
?>
--EXPECT--
left: hello 
left:method
string(8) "[HELLO]!"
string(7) "method!"

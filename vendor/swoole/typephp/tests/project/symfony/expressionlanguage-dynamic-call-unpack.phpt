--TEST--
Symfony ExpressionLanguage pattern: dynamic callable with unpacked evaluated arguments
--FILE--
<?php

final class ExpressionTarget
{
    public function join(string $prefix, string ...$parts): string
    {
        return $prefix.':'.implode(',', $parts);
    }
}

final class ArgumentsNode
{
    public function __construct(private array $values)
    {
    }

    public function evaluate(array $functions, array $values): array
    {
        return $this->values + $values + $functions;
    }
}

function call_expression_method(object $obj, string $method, ArgumentsNode $arguments): string
{
    if (!is_callable($toCall = [$obj, $method])) {
        return sprintf('Unable to call method "%s" of object "%s".', $method, get_debug_type($obj));
    }

    return $toCall(...array_values($arguments->evaluate(['ignored' => 'x'], ['tail' => 'c'])));
}

function main(): void
{
    var_dump(call_expression_method(new ExpressionTarget(), 'join', new ArgumentsNode(['a', 'b'])));
    var_dump(call_expression_method(new ExpressionTarget(), 'missing', new ArgumentsNode([])));
}
?>
--EXPECT--
string(7) "a:b,c,x"
string(61) "Unable to call method "missing" of object "ExpressionTarget"."

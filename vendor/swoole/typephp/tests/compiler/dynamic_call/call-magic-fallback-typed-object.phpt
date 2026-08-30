--TEST--
missing method on typed object dispatches to __call
--FILE--
<?php
use native_types;

class DynamicHandler
{
    public function __call(string $name, array $args): string
    {
        return $name . ':' . implode(',', $args);
    }
}

function invoke(DynamicHandler $handler): string
{
    $alias = $handler;
    return $alias->missing('a', 'b');
}

function main(): int
{
    $r = invoke(new DynamicHandler());
    echo "result: $r\n";
    return $r === 'missing:a,b' ? 0 : 1;
}
?>
--EXPECT--
result: missing:a,b

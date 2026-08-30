--TEST--
Match expression evaluates conditions and bodies lazily
--FILE--
<?php
function marker(string $name): string
{
    echo "cond:$name\n";
    return $name;
}

function makeArgs(string $name): array
{
    echo "args:$name\n";
    return [$name];
}

function makeValue(string $name): string
{
    echo "value:$name\n";
    return $name;
}

function build(string $id, string $value): string
{
    echo "body:$id:$value\n";
    return $id . ':' . $value;
}

function test_match(string $value): string
{
    return match ($value) {
        marker(name: 'a') => build(...makeArgs('A'), value: makeValue('A')),
        marker(name: 'b'), marker(name: 'skip') => build(...makeArgs('B'), value: makeValue('B')),
        marker(name: 'c') => build(...makeArgs('C'), value: makeValue('C')),
        default => build(...makeArgs('D'), value: makeValue('D')),
    };
}

function main(): void
{
    var_dump(test_match('b'));
}
?>
--EXPECT--
cond:a
cond:b
args:B
value:B
body:B:B
string(3) "B:B"

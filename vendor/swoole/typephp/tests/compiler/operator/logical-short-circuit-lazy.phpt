--TEST--
Logical && and || evaluate right side lazily
--FILE--
<?php
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

function check(string $id, string $value): bool
{
    echo "check:$id:$value\n";
    return true;
}

function main(): void
{
    var_dump(false && check(...makeArgs('and-skip'), value: makeValue('and-skip')));
    var_dump(true && check(...makeArgs('and-run'), value: makeValue('and-run')));
    var_dump(true || check(...makeArgs('or-skip'), value: makeValue('or-skip')));
    var_dump(false || check(...makeArgs('or-run'), value: makeValue('or-run')));
}
?>
--EXPECT--
bool(false)
args:and-run
value:and-run
check:and-run:and-run
bool(true)
bool(true)
args:or-run
value:or-run
check:or-run:or-run
bool(true)

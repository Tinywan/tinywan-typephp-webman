--TEST--
Ternary expression evaluates only selected branch
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

function build(string $id, string $value): string
{
    echo "body:$id:$value\n";
    return $id . ':' . $value;
}

function condition(string $id, string $value): bool
{
    echo "cond:$id:$value\n";
    return true;
}

function choose(bool $flag): string
{
    return $flag
        ? build(...makeArgs('T'), value: makeValue('T'))
        : build(...makeArgs('F'), value: makeValue('F'));
}

function main(): void
{
    var_dump(condition(...makeArgs('C'), value: makeValue('C')) ? build('T', 'T') : build('F', 'F'));
    var_dump(choose(true));
    var_dump(choose(false));
}
?>
--EXPECT--
args:C
value:C
cond:C:C
body:T:T
string(3) "T:T"
args:T
value:T
body:T:T
string(3) "T:T"
args:F
value:F
body:F:F
string(3) "F:F"

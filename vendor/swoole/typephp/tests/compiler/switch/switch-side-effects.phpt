--TEST--
Switch condition and case expressions evaluate side effects in order
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

function subject(string $id, string $value): string
{
    echo "subject:$id:$value\n";
    return $value;
}

function marker(string $id, string $value): string
{
    echo "case:$id:$value\n";
    return $value;
}

function main(): void
{
    switch (subject(...makeArgs('subject'), value: makeValue('b'))) {
        case marker(...makeArgs('a'), value: makeValue('a')):
            echo "A\n";
            break;
        case marker(...makeArgs('b'), value: makeValue('b')):
        case marker(...makeArgs('skip'), value: makeValue('skip')):
            echo "B\n";
            break;
        default:
            echo "default\n";
            break;
    }
}
?>
--EXPECT--
args:subject
value:b
subject:subject:b
args:a
value:a
case:a:a
args:b
value:b
case:b:b
B

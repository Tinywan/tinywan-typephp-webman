--TEST--
If and while conditions evaluate side effects at the correct time
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

function probe(string $id, string $value): bool
{
    echo "probe:$id:$value\n";
    return $id !== 'elseif-false';
}

function loopProbe(int $i): bool
{
    echo "loop:$i\n";
    return $i < 3;
}

function main(): void
{
    if (false && probe(...makeArgs('if-skip'), value: makeValue('if-skip'))) {
        echo "if\n";
    } elseif (probe(...makeArgs('elseif-false'), value: makeValue('elseif-false'))) {
        echo "elseif false\n";
    } elseif (probe(...makeArgs('elseif-true'), value: makeValue('elseif-true'))) {
        echo "elseif true\n";
    } else {
        echo "else\n";
    }

    $i = 0;
    while (loopProbe($i)) {
        echo "body:$i\n";
        $i++;
    }
}
?>
--EXPECT--
args:elseif-false
value:elseif-false
probe:elseif-false:elseif-false
args:elseif-true
value:elseif-true
probe:elseif-true:elseif-true
elseif true
loop:0
body:0
loop:1
body:1
loop:2
body:2
loop:3

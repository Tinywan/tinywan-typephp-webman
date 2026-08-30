--TEST--
array destructuring from function return values
--FILE--
<?php

function make_pair(string $name): array
{
    echo "make:$name\n";
    return [$name, strtoupper($name)];
}

function main(): void
{
    [$source, $upper] = make_pair('alpha');
    var_dump($source);
    var_dump($upper);

    [$left, [$middle, $right]] = ['left', ['middle', 'right']];
    var_dump($left . ':' . $middle . ':' . $right);
}
?>
--EXPECT--
make:alpha
string(5) "alpha"
string(5) "ALPHA"
string(17) "left:middle:right"

--TEST--
Ordinary typed scalars use PHP arithmetic; std::int()/std::float() stay native
--FILE--
<?php
function phpDiv(int $a, int $b): mixed { return $a / $b; }
function phpMod(int $a, int $b): mixed { return $a % $b; }

function main(): void
{
    // Ordinary typed parameters follow PHP semantics.
    var_dump(phpDiv(7, 2));
    var_dump(phpMod(-7, 2));

    // Explicit native scalars opt into C++ semantics.
    $i = std::int(10);
    var_dump($i / 4);
    $f = std::float(10.0);
    var_dump($f / 4);
}
?>
--EXPECT--
float(3.5)
int(-1)
int(2)
float(2.5)

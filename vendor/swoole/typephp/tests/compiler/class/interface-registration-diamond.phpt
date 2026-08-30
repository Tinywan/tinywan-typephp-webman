--TEST--
class entry registration preserves all dependencies in a reverse-declared interface diamond
--FILE--
<?php

interface DiamondLeaf extends DiamondLeft, DiamondRight
{
}

interface DiamondRight extends DiamondRoot
{
}

interface DiamondLeft extends DiamondRoot
{
}

interface DiamondRoot
{
}

class DiamondImplementation implements DiamondLeaf
{
}

function main(): void
{
    $value = new DiamondImplementation();
    var_dump($value instanceof DiamondLeaf);
    var_dump($value instanceof DiamondLeft);
    var_dump($value instanceof DiamondRight);
    var_dump($value instanceof DiamondRoot);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)

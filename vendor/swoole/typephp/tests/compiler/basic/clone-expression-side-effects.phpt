--TEST--
clone operand expression is evaluated once and __clone runs
--FILE--
<?php

class CloneSideEffect
{
    public int $value = 1;

    public function __clone()
    {
        echo "__clone\n";
        $this->value++;
    }
}

function make_clone_source(): CloneSideEffect
{
    echo "make\n";
    return new CloneSideEffect();
}

function main(): void
{
    $copy = clone make_clone_source();
    var_dump($copy->value);
}
?>
--EXPECT--
make
__clone
int(2)

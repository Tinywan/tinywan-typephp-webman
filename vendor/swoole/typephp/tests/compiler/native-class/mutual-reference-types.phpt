--TEST--
Native class: mutually referencing property types use pointer fields and forward declarations
--FILE--
<?php

function roundTripMutual(?NativeMutualRight $value): ?NativeMutualRight
{
    return $value;
}

#[Native]
class NativeMutualLeft
{
    public string $name = 'left';
    public ?NativeMutualRight $right;
}

#[Native]
class NativeMutualRight
{
    public string $name = 'right';
    public ?NativeMutualLeft $left;
}

function main(): void
{
    $left = new NativeMutualLeft();
    $right = new NativeMutualRight();
    $left->right = $right;
    $right->left = $left;

    echo roundTripMutual($left->right)->name, ':', $right->left->name, "\n";
}

?>
--EXPECT--
right:left

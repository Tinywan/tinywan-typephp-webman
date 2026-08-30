--TEST--
Nullsafe chain should stop at middle null and skip later arguments
--FILE--
<?php

class NullsafeChainLeaf
{
    public function value(string $arg): string
    {
        echo "leaf:$arg\n";
        return $arg;
    }
}

class NullsafeChainMiddle
{
    public function __construct(private ?NullsafeChainLeaf $leaf)
    {
    }

    public function leaf(string $arg): ?NullsafeChainLeaf
    {
        echo "middle:$arg\n";
        return $this->leaf;
    }
}

class NullsafeChainRoot
{
    public function __construct(private ?NullsafeChainMiddle $middle)
    {
    }

    public function middle(string $arg): ?NullsafeChainMiddle
    {
        echo "root:$arg\n";
        return $this->middle;
    }
}

function make_arg(string $name): string
{
    echo "arg:$name\n";
    return $name;
}

function main(): void
{
    $root = new NullsafeChainRoot(new NullsafeChainMiddle(null));
    var_dump($root?->middle(make_arg('root'))?->leaf(make_arg('middle'))?->value(make_arg('leaf')));

    $root = new NullsafeChainRoot(new NullsafeChainMiddle(new NullsafeChainLeaf()));
    var_dump($root?->middle(make_arg('root2'))?->leaf(make_arg('middle2'))?->value(make_arg('leaf2')));
}
?>
--EXPECT--
arg:root
root:root
arg:middle
middle:middle
NULL
arg:root2
root:root2
arg:middle2
middle:middle2
arg:leaf2
leaf:leaf2
string(5) "leaf2"

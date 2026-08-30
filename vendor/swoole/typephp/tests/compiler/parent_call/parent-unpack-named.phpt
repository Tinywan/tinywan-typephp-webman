--TEST--
parent::method() with unpack before named arguments
--FILE--
<?php
class ParentPair
{
    public function combine(int $a, int $b): string
    {
        return $a . ':' . $b;
    }
}

class ChildPair extends ParentPair
{
    public function combine(int $a, int $b): string
    {
        return 'child';
    }

    public function callParent(): string
    {
        return parent::combine(...[1], b: 2);
    }
}

function main(): void
{
    $child = new ChildPair();
    echo $child->combine(9, 9) . "\n";
    echo $child->callParent() . "\n";
}
?>
--EXPECT--
child
1:2

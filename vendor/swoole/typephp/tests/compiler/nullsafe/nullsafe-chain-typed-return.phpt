--TEST--
Nullsafe chain preserves typed method returns and member names
--FILE--
<?php
declare(strict_types=1);

final class NullsafeTypedReturnLeaf
{
    public string $value = 'forward';
}

final class NullsafeTypedReturnNode
{
    public NullsafeTypedReturnLeaf $leaf;

    public function __construct()
    {
        $this->leaf = new NullsafeTypedReturnLeaf();
    }

    public function self(): NullsafeTypedReturnNode
    {
        return $this;
    }
}

function main(): void
{
    $target = new NullsafeTypedReturnNode();
    $weak = WeakReference::create($target);
    echo $target->self()->leaf->value, "\n";
    echo $weak->get()?->self()?->leaf->value, "\n";
}
?>
--EXPECT--
forward
forward

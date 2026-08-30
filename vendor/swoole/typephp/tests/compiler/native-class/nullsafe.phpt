--TEST--
Native class: nullsafe chains remain in the native pointer model
--FILE--
<?php

#[Native]
class NativeNullsafeNode
{
    public ?NativeNullsafeNode $next;
    public int $number;

    public function __construct(int $number)
    {
        $this->number = $number;
    }

    public function value(int $offset = 0): int
    {
        return $this->number + $offset;
    }

    public function child(): ?NativeNullsafeNode
    {
        return $this->next;
    }
}

function offsetValue(): int
{
    echo "offset\n";
    return 2;
}

function main(): void
{
    $node = new NativeNullsafeNode(40);
    $node->next = new NativeNullsafeNode(10);
    var_dump($node?->value(offsetValue()));
    var_dump($node?->next?->value());
    var_dump($node?->child()?->number);

    $node = null;
    var_dump($node?->value(offsetValue()));
    var_dump($node?->next?->value());
    $child = $node?->child();
    var_dump(isset($child));
}
?>
--EXPECT--
offset
int(42)
int(10)
int(10)
NULL
NULL
bool(false)

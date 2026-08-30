--TEST--
protected overridden method remains virtual through parent wrapper
--FILE--
<?php
use native_types;

class Base
{
    protected function token(): string
    {
        return 'base';
    }

    public function expose(): string
    {
        return $this->token();
    }
}

class Child extends Base
{
    protected function token(): string
    {
        return 'child';
    }
}

function main(): int
{
    $base = new Base();
    $child = new Child();
    $a = $base->expose();
    $b = $child->expose();
    echo "base: $a\n";
    echo "child: $b\n";
    return $a === 'base' && $b === 'child' ? 0 : 1;
}
?>
--EXPECT--
base: base
child: child

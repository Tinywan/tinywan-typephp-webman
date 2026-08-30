--TEST--
call overridden method through parent parameter type
--FILE--
<?php
class Base
{
    public function run(): string
    {
        return 'base';
    }
}

class Impl extends Base
{
    public function run(): string
    {
        return 'impl';
    }
}

function run(Base $obj): string
{
    $alias = $obj;
    return $alias->run();
}

function main(): int
{
    $r = run(new Impl());
    echo "result: $r\n";
    return $r === 'impl' ? 0 : 1;
}
?>
--EXPECT--
result: impl

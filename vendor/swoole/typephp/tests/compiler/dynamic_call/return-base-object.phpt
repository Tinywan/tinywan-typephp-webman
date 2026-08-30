--TEST--
call overridden method through parent parameter type
--FILE--
<?php
class Base
{
    static function create() {
        return new Impl;
    }
}

class Impl extends Base
{
    public function run(): string
    {
        return 'impl';
    }
}

function get_impl(): Base
{
    $obj = Base::create();
    return $obj;
}

function main(): int
{
    $impl = get_impl();
    echo "result: " . $impl->run() . "\n";
}
?>
--EXPECT--
result: impl

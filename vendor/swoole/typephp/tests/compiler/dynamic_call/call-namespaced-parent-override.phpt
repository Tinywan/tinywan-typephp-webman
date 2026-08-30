--TEST--
call overridden method through namespaced parent type
--FILE--
<?php
namespace Demo {
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
}

namespace {
    use Demo\Impl;

    function main(): int
    {
        $r = Demo\run(new Impl());
        echo "result: $r\n";
        return $r === 'impl' ? 0 : 1;
    }
}
?>
--EXPECT--
result: impl

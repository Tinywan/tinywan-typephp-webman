--TEST--
trait full class name
--FILE--
<?php
namespace App2 {
    trait THello1
    {
        public const array DATA = [
            TraitsTest::NAME => 'Hello 1',
        ];
        public function hello()
        {
            var_dump(self::DATA);
            var_dump(self::DATA[TraitsTest::NAME]);
        }
    }

    class TraitsTest
    {
        public const NAME = 'test_trait';
        use THello1;
    }
}

namespace {
    function main()
    {
        $o = new App2\TraitsTest;
        $o->hello();
    }
}
?>
--EXPECT--
array(1) {
  ["test_trait"]=>
  string(7) "Hello 1"
}
string(7) "Hello 1"
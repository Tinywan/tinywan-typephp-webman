--TEST--
parent class constants resolve across namespaces
--FILE--
<?php

namespace Library {
    class Base
    {
        public const TOKEN = 'base';
    }
}

namespace Application {
    class Sibling
    {
    }

    class Child extends \Library\Base
    {
        public const PARENT_NAME = parent::class;
        public const SIBLING_NAME = Sibling::class;

        public static function dumpParent(): void
        {
            var_dump(parent::class, parent::TOKEN);
        }
    }
}

namespace {
    function main(): void
    {
        var_dump(\Application\Child::PARENT_NAME);
        var_dump(\Application\Child::SIBLING_NAME);
        \Application\Child::dumpParent();
    }
}
?>
--EXPECT--
string(12) "Library\Base"
string(19) "Application\Sibling"
string(12) "Library\Base"
string(4) "base"

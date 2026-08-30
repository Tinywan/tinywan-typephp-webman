--TEST--
Class constants referenced through self, parent, explicit names and runtime static
--FILE--
<?php

declare(strict_types=1);

namespace StubConstRef {
    class Base
    {
        const INHERITED = 1;
        const OVERRIDDEN = 2;
    }

    class Child extends Base
    {
        const LOCAL = 10;
        const OVERRIDDEN = 20;
        const SELF_LOCAL = self::LOCAL;
        const SELF_INHERITED = self::INHERITED;
        const PARENT_VALUE = parent::OVERRIDDEN;

        public int $selfLocal = self::LOCAL;
        public int $selfInherited = self::INHERITED;
        public int $parentValue = parent::OVERRIDDEN;
        public int $explicitValue = \StubConstRef\Base::INHERITED;

        public function defaults(
            int $self = self::LOCAL,
            int $parent = parent::OVERRIDDEN
        ): array {
            return [$self, $parent];
        }

        public function runtimeStatic(): int
        {
            return static::OVERRIDDEN;
        }
    }

    class GrandChild extends Child
    {
        const OVERRIDDEN = 30;
    }
}

namespace {
    function main()
    {
        $test = new StubConstRef\Child;
        var_dump(
            StubConstRef\Child::SELF_LOCAL,
            StubConstRef\Child::SELF_INHERITED,
            StubConstRef\Child::PARENT_VALUE
        );
        var_dump(
            $test->selfLocal,
            $test->selfInherited,
            $test->parentValue,
            $test->explicitValue
        );
        var_dump($test->defaults());
        var_dump((new StubConstRef\GrandChild)->runtimeStatic());
    }
}
?>
--EXPECT--
int(10)
int(1)
int(2)
int(10)
int(1)
int(2)
int(1)
array(2) {
  [0]=>
  int(10)
  [1]=>
  int(2)
}
int(30)

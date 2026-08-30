<?php

namespace NativePropSource {
    use native_types;

    class Target
    {
        public static int $count = 1;
        public int $value = 2;
        protected int $protectedValue = 3;
    }
}

namespace NativePropSource\Target {
    use native_types;
    use NativePropSource\Target;

    class Child extends Target
    {
        public function readThis(): int
        {
            return $this->value;
        }

        public function readInheritedProtected(Target $target): int
        {
            return $target->protectedValue;
        }

        public static function readSelf(): int
        {
            return self::$count;
        }

        public static function readStatic(): int
        {
            return static::$count;
        }

        public static function writeStatic(int $value): int
        {
            static::$count = $value;
            return static::$count;
        }

        public static function readParent(): int
        {
            return parent::$count;
        }
    }

    function readObject(): int
    {
        $target = new Target();
        return $target->value;
    }

    function readStaticByUse(): int
    {
        return Target::$count;
    }
}

namespace {
    function main(): void
    {
        $child = new \NativePropSource\Target\Child();
        var_dump($child->readThis());
        var_dump(\NativePropSource\Target\readObject());
        var_dump(\NativePropSource\Target\readStaticByUse());
        var_dump(\NativePropSource\Target\Child::readSelf());
        var_dump(\NativePropSource\Target\Child::readStatic());
        var_dump(\NativePropSource\Target\Child::writeStatic(4));
        var_dump(\NativePropSource\Target\Child::readParent());
    }
}

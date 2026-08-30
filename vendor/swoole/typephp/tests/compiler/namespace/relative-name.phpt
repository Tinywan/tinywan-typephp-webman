--TEST--
namespace\name resolves relative functions, constants, classes, members, and declarations
--FILE--
<?php
namespace {
    const GLOBAL_VALUE = 'global constant';

    function globalHelper(): string
    {
        return 'global function';
    }

    class GlobalTarget
    {
        public const VALUE = 'global class';
    }
}

namespace RelativeNames {
    const VALUE = 'namespaced constant';

    #[\Attribute(\Attribute::TARGET_CLASS)]
    class Marker
    {
        public function __construct(public string $value)
        {
        }
    }

    function helper(): string
    {
        return 'namespaced function';
    }

    interface Contract
    {
        public function target(): namespace\Target;
    }

    trait Feature
    {
        public function feature(): string
        {
            return namespace\helper();
        }
    }

    class Base
    {
        public function base(): string
        {
            return 'anonymous base';
        }
    }

    class Target
    {
        public const VALUE = 'class constant';
        public static string $value = 'static property';

        public static function method(): string
        {
            return 'static method';
        }
    }

    class Failure extends \Exception
    {
    }

    #[namespace\Marker(namespace\VALUE)]
    class Child extends namespace\Base implements namespace\Contract
    {
        use namespace\Feature;

        public function target(): namespace\Target
        {
            return new namespace\Target();
        }

        public function accepts(namespace\Target $target): bool
        {
            return $target instanceof namespace\Target;
        }
    }

    function run(): void
    {
        var_dump(namespace\VALUE);
        var_dump(namespace\helper());
        $callable = namespace\helper(...);
        var_dump($callable());
        var_dump(namespace\Target::VALUE);
        var_dump(namespace\Target::$value);
        var_dump(namespace\Target::method());

        $child = new namespace\Child();
        $target = $child->target();
        var_dump($child->accepts($target));
        var_dump($child->feature());
        $attribute = (new \ReflectionClass(namespace\Child::class))
            ->getAttributes(namespace\Marker::class)[0];
        var_dump($attribute->getArguments()[0]);

        $anonymous = new class extends namespace\Base implements namespace\Contract {
            use namespace\Feature;

            public function target(): namespace\Target
            {
                return new namespace\Target();
            }
        };
        var_dump($anonymous->base());
        var_dump($anonymous->feature());
        var_dump($anonymous->target() instanceof namespace\Target);

        try {
            throw new namespace\Failure('caught');
        } catch (namespace\Failure $exception) {
            var_dump($exception->getMessage());
        }
    }
}

namespace {
    function main(): void
    {
        var_dump(namespace\GLOBAL_VALUE);
        var_dump(namespace\globalHelper());
        var_dump(namespace\GlobalTarget::VALUE);
        RelativeNames\run();
    }
}
?>
--EXPECT--
string(15) "global constant"
string(15) "global function"
string(12) "global class"
string(19) "namespaced constant"
string(19) "namespaced function"
string(19) "namespaced function"
string(14) "class constant"
string(15) "static property"
string(13) "static method"
bool(true)
string(19) "namespaced function"
string(19) "namespaced constant"
string(14) "anonymous base"
string(19) "namespaced function"
bool(true)
string(6) "caught"

--TEST--
Trait self static calls resolve to the consuming class across namespaces
--FILE--
<?php

namespace TraitSelfCall\Template {
    trait Dispatch
    {
        public function namedCall(): string
        {
            return self::privateHelper();
        }

        public function dynamicCall(): string
        {
            $method = 'helper';
            return self::$method();
        }

        public function selfMembers(): array
        {
            return [self::class, self::LABEL, self::$label];
        }

        private static function privateHelper(): string
        {
            return 'trait-helper';
        }

        public static function helper(): string
        {
            return 'trait-helper';
        }
    }
}

namespace TraitSelfCall\Consumer {
    use TraitSelfCall\Template\Dispatch;

    final class Example
    {
        use Dispatch;

        private const LABEL = 'consumer-constant';
        private static string $label = 'consumer-property';
    }
}

namespace {
    function main(): void
    {
        $object = new TraitSelfCall\Consumer\Example();
        var_dump($object->namedCall());
        var_dump($object->dynamicCall());
        var_dump($object->selfMembers());
    }
}

?>
--EXPECT--
string(12) "trait-helper"
string(12) "trait-helper"
array(3) {
  [0]=>
  string(30) "TraitSelfCall\Consumer\Example"
  [1]=>
  string(17) "consumer-constant"
  [2]=>
  string(17) "consumer-property"
}

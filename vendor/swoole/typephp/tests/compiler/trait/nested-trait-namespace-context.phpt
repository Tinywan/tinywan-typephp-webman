--TEST--
Nested trait uses resolve unqualified names in the declaring namespace
--FILE--
<?php
namespace Issue38\SameNamespace {
    trait Greeting {
        public function hello(): string {
            return 'hi';
        }
    }

    trait GreetingWrapper {
        use Greeting;
    }

    class Example {
        use GreetingWrapper;
    }
}

namespace {
    function main(): void {
        echo (new Issue38\SameNamespace\Example())->hello(), "\n";
    }
}
?>
--EXPECT--
hi

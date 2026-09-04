--TEST--
Nested trait uses retain import aliases through multiple composition levels
--FILE--
<?php
namespace Issue38\Library {
    trait Greeting {
        public function hello(): string {
            return 'imported';
        }
    }
}

namespace Issue38\Template {
    use Issue38\Library\Greeting as ImportedGreeting;

    trait InnerWrapper {
        use ImportedGreeting {
            hello as protected importedHello;
        }

        public function wrapped(): string {
            return '[' . $this->importedHello() . ']';
        }
    }

    trait OuterWrapper {
        use InnerWrapper;
    }
}

namespace Issue38\Consumer {
    use Issue38\Template\OuterWrapper as ImportedWrapper;

    class Example {
        use ImportedWrapper;
    }
}

namespace {
    function main(): void {
        $example = new Issue38\Consumer\Example();
        echo $example->hello(), "\n";
        echo $example->wrapped(), "\n";
    }
}
?>
--EXPECT--
imported
[imported]

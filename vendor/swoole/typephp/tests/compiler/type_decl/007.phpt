--TEST--
Type Declarations
--FILE--
<?php
namespace TestApp\Console {
    class Command {
        public $name;
        public function __construct(string $name)
        {
            $this->name = $name;
        }
    }
}

namespace TestApp\Command {
    use TestApp\Console\Command;
    class Test {
        public function callCommand(string $class): Command
        {
            $array = ['object' => new Command($class)];
            return $array['object']->toObject(Command::class);
        }
    }
}
namespace {
    function main() {
        $o = new TestApp\Command\Test;
        $o2 = $o->callCommand('TestApp\Console\Command');
        var_dump($o2->name);
    }
}
?>
--EXPECT--
string(23) "TestApp\Console\Command"
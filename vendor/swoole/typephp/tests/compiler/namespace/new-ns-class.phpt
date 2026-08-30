--TEST--
Namespace with constants defined via const keyword
--FILE--
<?php
namespace Foo\App {
    class Printer {
        public function print() {
            echo "Hello World!\n";
        }
    }
}

namespace Bar\App {
    use Foo\App;
    function test(){
        $o = new App\Printer;
        $o->print();
    }
}

namespace {
    function main() {
        Bar\App\test();
    }
}
?>
--EXPECT--
Hello World!
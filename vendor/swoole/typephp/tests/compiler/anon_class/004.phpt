--TEST--
Anonymous class inside namespace
--FILE--
<?php
namespace Foo\App {
    class Node {
        public string $name;
    }

    abstract class VisitorAbstract {
        abstract public function test(Node $node): string;
    }
}

namespace Bar\App {
    use Foo\App\Node;
    use Foo\App\VisitorAbstract;

    function test() {
        $node = new Node();
        $node->name = "World";
        $obj = new class() extends VisitorAbstract {
            public function test(Node $node): string {
                return 'Hello ' . $node->name . " !";
            }
        };
        var_dump($obj->test($node));
    }
}

namespace {
    function main() {
        Bar\App\test();
        echo "done\n";
    }
}
?>
--EXPECT--
string(13) "Hello World !"
done

--TEST--
Cross-trait call with untyped nullable parameter (regression test for arginfo/zpp mismatch)
--FILE--
<?php
namespace App\Types {
    class Node {
        public string $name;
        public function __construct(string $name = '') {
            $this->name = $name;
        }
    }
}

namespace App\Test{
    use App\Types\Node;

    trait HelperTrait {
        // Note: no type hint on $node — ?ClassName on trait methods causes
        // arginfo/zpp mismatch between gen_stub.php's ZEND_ARG_OBJ_INFO and
        // the AOT compiler's TYPE_VAR-based codegen.
        protected function process(string $expr, string $type, Node $node): string {
            if ($node instanceof Node) {
                return 'node:' . $node->name;
            }
            if ($type !== '') {
                return 'expr:' . $expr . ',type:' . $type;
            }
            return 'expr:' . $expr;
        }
    }

    trait CallerTrait {
        public function runProcess(string $expr, string $type, Node $node): string {
            return $this->process($expr, $type, $node);
        }
    }

    class MyProcessor {
        use HelperTrait;
        use CallerTrait;
    }
}

namespace  {
    function main() {
        $p = new App\Test\MyProcessor();
        $node = new App\Types\Node('hello');

        var_dump($p->runProcess('data', 'float', $node));
        var_dump($p->runProcess('str', 'str', new App\Types\Node('world')));
    }
}
?>
--EXPECT--
string(10) "node:hello"
string(10) "node:world"

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

namespace {
    use App\Types\Node;

    trait HelperTrait {
        // Note: no type hint on $node — ?ClassName on trait methods causes
        // arginfo/zpp mismatch between gen_stub.php's ZEND_ARG_OBJ_INFO and
        // the AOT compiler's TYPE_VAR-based codegen.
        protected function process(string $expr, string $type = '', ?Node $node = null): string {
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
        public function runProcess(string $expr, string $type = '', ?Node $node = null): string {
            return $this->process($expr, $type, $node);
        }
    }

    class MyProcessor {
        use HelperTrait;
        use CallerTrait;
    }

    function main() {
        $p = new MyProcessor();
        $node = new Node('hello');

        var_dump($p->runProcess('data', 'float', $node));
        var_dump($p->runProcess('data', 'int'));
        var_dump($p->runProcess('data'));
        var_dump($p->runProcess('data', 'string', null));
        var_dump($p->runProcess('str', 'str', new Node('world')));
    }
}
?>
--EXPECT--
string(10) "node:hello"
string(18) "expr:data,type:int"
string(9) "expr:data"
string(21) "expr:data,type:string"
string(10) "node:world"

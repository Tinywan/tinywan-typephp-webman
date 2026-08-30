--TEST--
type hits
--FILE--
<?php

class Foo {
    public function __construct(
        private array $stmts
    ) {
    }

    private function collectLabels(array $stmts): void
    {
        var_dump($stmts);
    }

    public function buildCfg(): void
    {
        $this->collectLabels($this->stmts);
    }
}

function main()
{
    $obj = new Foo(['Hello World']);
    $obj->buildCfg();
}
?>
--EXPECT--
array(1) {
  [0]=>
  string(11) "Hello World"
}
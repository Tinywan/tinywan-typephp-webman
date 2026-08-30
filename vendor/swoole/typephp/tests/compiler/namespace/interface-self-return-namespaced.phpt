--TEST--
interface method `self` return type resolves to the interface's fully-qualified name inside a named namespace
--FILE--
<?php

namespace App {
    interface Chainable
    {
        public function chain(): self;

        public function maybe(bool $present): ?self;

        public function combine(self $other): self;
    }

    // comment inside a named namespace block (Stmt_Nop)
    class Widget implements Chainable
    {
        public array $log = [];

        public function chain(): self
        {
            $this->log[] = 'chain';
            return $this;
        }

        public function maybe(bool $present): ?self
        {
            return $present ? $this : null;
        }

        public function combine(Chainable $other): self
        {
            return $this;
        }
    }
}

namespace {
    function main()
    {
        $w = new \App\Widget();
        var_dump($w->chain()->chain() instanceof \App\Chainable);
        var_dump(count($w->log));
        var_dump($w->maybe(true) instanceof \App\Chainable);
        var_dump($w->maybe(false));
        var_dump($w->combine(new \App\Widget()) === $w);
    }
}
?>
--EXPECT--
bool(true)
int(2)
bool(true)
NULL
bool(true)

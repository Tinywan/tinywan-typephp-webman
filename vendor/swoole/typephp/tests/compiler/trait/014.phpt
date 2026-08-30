--TEST--
Trait duplicate abstract method from multiple traits
--FILE--
<?php
trait A {
    abstract protected function db();
    public function t() { echo $this->db(); }
}

trait B {
    abstract protected function db();
}

class C {
    use A, B;
    protected function db() { return "OK\n"; }
}

function main() {
    $b = new C();
    $b->t();
}

?>
--EXPECT--
OK

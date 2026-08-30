--TEST--
Clone keyword for object cloning
--FILE--
<?php

class Counter {
    public int $value;

    public function __construct(int $v) {
        $this->value = $v;
    }

    public function increment(): void {
        $this->value++;
    }
}

function main() {
    $original = new Counter(10);
    $original->increment();

    $cloned = clone $original;
    $cloned->increment();
    $cloned->increment();

    var_dump($original->value);
    var_dump($cloned->value);
    var_dump($original->value !== $cloned->value);
}

?>
--EXPECT--
int(11)
int(13)
bool(true)

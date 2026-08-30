--TEST--
First-class callable placeholder is not treated as a call argument by property optimizer
--FILE--
<?php

use native_types;

class FirstClassCallableTarget
{
    public int $counter = 0;

    public function tick(): void
    {
        $this->counter++;
    }

    public function invoke(callable $callback): void
    {
        $callback();
    }
}

function main(): void
{
    $target = new FirstClassCallableTarget();
    $target->invoke($target->tick(...));
    var_dump($target->counter);
}

?>
--EXPECT--
int(1)

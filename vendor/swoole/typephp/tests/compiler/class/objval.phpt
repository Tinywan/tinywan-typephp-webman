--TEST--
objval
--FILE--
<?php

class TestEvent
{
    public int $x = 0;
    public int $y = 0;
    public string $action = '';

    public function __construct(int $x, int $y, string $action)
    {
        $this->x = $x;
        $this->y = $y;
        $this->action = $action;
    }

    public function getX(): int {
        return $this->x;
    }
}

function wrapObjval($ev): TestEvent
{
    return objval($ev, TestEvent::class);
}

function main() {
    $ev = new TestEvent(42, 0, 'base');
    $ev2 = wrapObjval($ev);
    var_dump($ev2->x);
    var_dump($ev2->getX());

    echo "done\n";
}

?>
--EXPECT--
int(42)
int(42)
done

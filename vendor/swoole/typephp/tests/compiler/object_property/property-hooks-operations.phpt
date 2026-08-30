--TEST--
PHP 8.4 property hooks support implicit value, compound writes and dynamic reads
--FILE--
<?php

class Counter
{
    public int $value {
        get => $this->value * 2;
        set => max(0, $value);
    }
}

function readDynamically(mixed $counter): mixed
{
    return $counter->value;
}

function writeDynamically(mixed $counter, mixed $value): void
{
    $counter->value = $value;
}

function main(): void
{
    $counter = new Counter();
    $counter->value = 3;
    $counter->value += 2;
    var_dump($counter->value);
    var_dump($counter->value++);
    var_dump(readDynamically($counter));
    writeDynamically($counter, -10);
    var_dump(readDynamically($counter));
}
?>
--EXPECT--
int(16)
int(16)
int(34)
int(0)

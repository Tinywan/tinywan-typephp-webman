--TEST--
Nullable typed property receiver preserves by-reference arguments when non-null
--FILE--
<?php

final class TypedPropertyNullableTarget
{
    public function record(array &$events): void
    {
        $events[] = 'recorded';
    }
}

final class TypedPropertyNullableHolder
{
    public ?TypedPropertyNullableTarget $target;

    public function __construct()
    {
        $this->target = new TypedPropertyNullableTarget();
    }

    public function run(array &$events): void
    {
        $this->target->record($events);
    }
}

function main(): void
{
    $events = [];
    $holder = new TypedPropertyNullableHolder();
    $holder->run($events);
    echo json_encode($events, JSON_THROW_ON_ERROR), "\n";
}

?>
--EXPECT--
["recorded"]

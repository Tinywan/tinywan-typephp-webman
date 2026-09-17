--TEST--
Typed property reference arguments follow the runtime protected override or __call target
--FILE--
<?php

class TypedPropertyProtectedBase
{
    protected function record(array &$events): void
    {
    }

    public function __call(string $name, array $arguments): void
    {
        $arguments[0][] = 'magic';
    }
}

final class TypedPropertyProtectedChild extends TypedPropertyProtectedBase
{
    public function record(array &$events): void
    {
        $events[] = 'child';
    }
}

final class TypedPropertyProtectedHolder
{
    public TypedPropertyProtectedBase $target;

    public function run(array &$events): void
    {
        $this->target->record($events);
    }
}

function main(): void
{
    $holder = new TypedPropertyProtectedHolder();

    $magicEvents = [];
    $holder->target = new TypedPropertyProtectedBase();
    $holder->run($magicEvents);
    echo json_encode($magicEvents, JSON_THROW_ON_ERROR), "\n";

    $childEvents = [];
    $holder->target = new TypedPropertyProtectedChild();
    $holder->run($childEvents);
    echo json_encode($childEvents, JSON_THROW_ON_ERROR), "\n";
}

?>
--EXPECT--
[]
["child"]

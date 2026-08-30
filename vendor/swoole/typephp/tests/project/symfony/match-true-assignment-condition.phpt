--TEST--
Symfony HttpKernel pattern: match true with assignment inside condition
--FILE--
<?php

class Metadata
{
    public function __construct(private string $value)
    {
    }

    public function evaluate(): string
    {
        return 'metadata:'.$this->value;
    }
}

class EventWithMetadata
{
    public ?Metadata $controllerMetadata = null;
}

function evaluate(EventWithMetadata $event): string
{
    return match (true) {
        ($m = $event->controllerMetadata ?? null) instanceof Metadata => $m->evaluate(),
        default => 'none',
    };
}

function main(): void
{
    $event = new EventWithMetadata();
    var_dump(evaluate($event));

    $event->controllerMetadata = new Metadata('ok');
    var_dump(evaluate($event));
}
?>
--EXPECT--
string(4) "none"
string(11) "metadata:ok"

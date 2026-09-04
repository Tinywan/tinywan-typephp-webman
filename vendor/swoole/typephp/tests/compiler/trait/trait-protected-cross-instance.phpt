--TEST--
Trait-composed protected methods retain class scope across instances
--FILE--
<?php

trait CrossInstanceEvents
{
    protected function fire(string $event): string
    {
        return 'base:' . $event;
    }

    protected function marker(): string
    {
        return 'marker';
    }
}

class CrossInstanceBase
{
    use CrossInstanceEvents;

    public function touchSelf(): string
    {
        $other = new self();
        return $other->marker() . ':' . $other->fire('self');
    }

    public function touchStatic(): string
    {
        $other = new static();
        return $other->marker() . ':' . $other->fire('static');
    }
}

class CrossInstanceChild extends CrossInstanceBase
{
    protected function fire(string $event): string
    {
        return 'child:' . $event;
    }
}

function main(): void
{
    $base = new CrossInstanceBase();
    echo $base->touchSelf(), "\n";
    echo $base->touchStatic(), "\n";

    $child = new CrossInstanceChild();
    echo $child->touchStatic(), "\n";
}
?>
--EXPECT--
marker:base:self
marker:base:static
marker:child:static

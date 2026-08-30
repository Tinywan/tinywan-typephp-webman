--TEST--
Symfony pattern: lazy listener closure replaces itself with first-class callable
--FILE--
<?php

final class LazyListener
{
    public function __invoke(string $event): string
    {
        echo "handle:$event\n";
        return strtoupper($event);
    }
}

function main(): void
{
    $listener = [static function (): LazyListener {
        echo "factory\n";
        return new LazyListener();
    }];

    $optimized = [];
    $optimized[0] = null;
    $closure = &$optimized[0];
    $closure = static function (...$args) use (&$listener, &$closure) {
        if ($listener[0] instanceof Closure) {
            $listener[0] = $listener[0]();
            $listener[1] ??= '__invoke';
        }

        return ($closure = $listener(...))(...$args);
    };

    var_dump($optimized[0]('first'));
    var_dump($optimized[0]('second'));
    var_dump($listener[1]);
}
?>
--EXPECT--
factory
handle:first
string(5) "FIRST"
handle:second
string(6) "SECOND"
string(8) "__invoke"

--TEST--
Symfony pattern: count() dispatches to Countable object
--FILE--
<?php

final class CountableRoutes implements Countable
{
    public function __construct(private array $routes)
    {
    }

    public function count(): int
    {
        return count($this->routes);
    }
}

function main(): void
{
    $routes = new CountableRoutes(['home', 'about', 'contact']);
    var_dump(count($routes));
}
?>
--EXPECT--
int(3)

--TEST--
Symfony pattern: route collection deep clone with ArrayIterator
--FILE--
<?php

final class MiniRoute
{
    public function __construct(public string $path)
    {
    }
}

final class MiniAlias
{
    public function __construct(public string $target)
    {
    }
}

final class MiniRouteCollection implements IteratorAggregate
{
    private array $routes = [];
    private array $aliases = [];

    public function add(string $name, MiniRoute $route): void
    {
        $this->routes[$name] = $route;
    }

    public function addAlias(string $name, MiniAlias $alias): void
    {
        $this->aliases[$name] = $alias;
    }

    public function __clone()
    {
        foreach ($this->routes as $name => $route) {
            $this->routes[$name] = clone $route;
        }

        foreach ($this->aliases as $name => $alias) {
            $this->aliases[$name] = clone $alias;
        }
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->routes);
    }

    public function routeCount(): int
    {
        return count($this->routes);
    }

    public function aliasTarget(string $name): string
    {
        return $this->aliases[$name]->target;
    }
}

function main(): void
{
    $collection = new MiniRouteCollection();
    $collection->add('home', new MiniRoute('/'));
    $collection->add('about', new MiniRoute('/about'));
    $collection->addAlias('root', new MiniAlias('home'));

    $copy = clone $collection;

    foreach (iterator_to_array($copy->getIterator()) as $name => $route) {
        $route->path = strtoupper($route->path);
        var_dump($name.':'.$route->path);
    }

    var_dump($collection->routeCount());
    var_dump($collection->aliasTarget('root'));
}
?>
--EXPECT--
string(6) "home:/"
string(12) "about:/ABOUT"
int(2)
string(4) "home"

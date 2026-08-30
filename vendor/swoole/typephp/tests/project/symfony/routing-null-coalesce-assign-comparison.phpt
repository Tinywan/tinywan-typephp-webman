--TEST--
Symfony Routing pattern: null comparison with coalesce assign route lookup
--FILE--
<?php

final class Route
{
    public function __construct(public string $name)
    {
    }
}

final class RouteCollectionLookup
{
    public function __construct(private array $routes)
    {
    }

    public function get(string $name): ?Route
    {
        return $this->routes[$name] ?? null;
    }
}

function resolve_route(RouteCollectionLookup $routes, string $name, ?Route $route = null): string
{
    if (null === $route ??= $routes->get($name)) {
        return 'missing';
    }

    return $route->name;
}

function main(): void
{
    $routes = new RouteCollectionLookup(['home' => new Route('home')]);
    var_dump(resolve_route($routes, 'home'));
    var_dump(resolve_route($routes, 'missing'));
    var_dump(resolve_route($routes, 'missing', new Route('explicit')));
}
?>
--EXPECT--
string(4) "home"
string(7) "missing"
string(8) "explicit"

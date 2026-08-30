--TEST--
Symfony Routing pattern: null comparison with route coalesce assignment
--FILE--
<?php

class RouteStore
{
    public function __construct(private array $routes)
    {
    }

    public function get(string $name): ?string
    {
        echo "lookup:$name\n";
        return $this->routes[$name] ?? null;
    }
}

function generate(RouteStore $routes, string $name): string
{
    $route = null;

    if (null === $route ??= $routes->get($name)) {
        return 'missing';
    }

    return 'route:'.$route;
}

function main(): void
{
    $routes = new RouteStore(['home' => '/']);

    var_dump(generate($routes, 'home'));
    var_dump(generate($routes, 'missing'));
}
?>
--EXPECT--
lookup:home
string(7) "route:/"
lookup:missing
string(7) "missing"

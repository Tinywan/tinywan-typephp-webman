--TEST--
Symfony pattern: uksort with priority fallback comparator
--FILE--
<?php

class SymfonyLikeRouteCollection
{
    private array $routes = [];
    private array $priorities = [];

    public function add(string $name, string $path, int $priority = 0): void
    {
        $this->routes[$name] = $path;
        $this->priorities[$name] = $priority;
    }

    public function all(): array
    {
        $priorities = $this->priorities;
        $keysOrder = array_flip(array_keys($this->routes));

        uksort($this->routes, static fn ($n1, $n2) => (($priorities[$n2] ?? 0) <=> ($priorities[$n1] ?? 0)) ?: ($keysOrder[$n1] <=> $keysOrder[$n2]));

        return $this->routes;
    }
}

function main(): void
{
    $collection = new SymfonyLikeRouteCollection();
    $collection->add('fallback', '/fallback');
    $collection->add('admin', '/admin', 20);
    $collection->add('api', '/api', 20);
    $collection->add('home', '/', 10);

    var_dump(array_keys($collection->all()));
}
?>
--EXPECT--
array(4) {
  [0]=>
  string(5) "admin"
  [1]=>
  string(3) "api"
  [2]=>
  string(4) "home"
  [3]=>
  string(8) "fallback"
}

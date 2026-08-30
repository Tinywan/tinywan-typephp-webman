--TEST--
Symfony ServiceLocator style match count with closure unpack
--FILE--
<?php
class SymfonyServiceLocatorBase
{
    public function __construct(array $serviceMap)
    {
    }

    public function get(string $id): mixed
    {
        return 'parent:' . $id;
    }
}

class SymfonyServiceLocatorCase extends SymfonyServiceLocatorBase
{
    public function __construct(
        private Closure $factory,
        private array $serviceMap,
        private ?array $serviceTypes = null,
    ) {
        parent::__construct($serviceMap);
    }

    public function get(string $id): mixed
    {
        return match (count($this->serviceMap[$id] ?? [])) {
            0 => parent::get($id),
            1 => $this->serviceMap[$id][0],
            default => ($this->factory)(...$this->serviceMap[$id]),
        };
    }

    public function getProvidedServices(): array
    {
        return $this->serviceTypes ??= array_map(static fn () => '?', $this->serviceMap);
    }
}

function main(): void
{
    $locator = new SymfonyServiceLocatorCase(
        static fn (string $class, string $method): string => $class . '::' . $method,
        [
            'missing' => [],
            'single' => ['ready'],
            'factory' => ['App\Service', 'build'],
        ]
    );

    var_dump($locator->get('missing'));
    var_dump($locator->get('single'));
    var_dump($locator->get('factory'));
    var_dump($locator->getProvidedServices());
    var_dump($locator->getProvidedServices());
}
?>
--EXPECT--
string(14) "parent:missing"
string(5) "ready"
string(18) "App\Service::build"
array(3) {
  ["missing"]=>
  string(1) "?"
  ["single"]=>
  string(1) "?"
  ["factory"]=>
  string(1) "?"
}
array(3) {
  ["missing"]=>
  string(1) "?"
  ["single"]=>
  string(1) "?"
  ["factory"]=>
  string(1) "?"
}

--TEST--
Symfony pattern: container fallback caches static first-class callable
--FILE--
<?php

final class MiniContainer
{
    public array $services = [];
    public array $aliases = [];
    public array $factories = [];
    public static mixed $make = null;

    public function __construct()
    {
        $this->factories['custom'] = static fn (self $container): object => (object) ['id' => 'custom'];
    }

    public function get(string $id): object
    {
        return $this->services[$id]
            ?? $this->services[$id = $this->aliases[$id] ?? $id]
            ?? ('service_container' === $id ? $this : ($this->factories[$id] ?? self::$make ??= self::make(...))($this, $id));
    }

    public static function make(self $container, string $id): object
    {
        $service = (object) ['id' => $id];
        $container->services[$id] = $service;

        return $service;
    }
}

function main(): void
{
    $container = new MiniContainer();
    $container->aliases['logger'] = 'monolog';

    var_dump($container->get('logger')->id);
    var_dump($container->get('monolog')->id);
    var_dump($container->get('custom')->id);
    var_dump(MiniContainer::$make instanceof Closure);
}
?>
--EXPECT--
string(7) "monolog"
string(7) "monolog"
string(6) "custom"
bool(true)

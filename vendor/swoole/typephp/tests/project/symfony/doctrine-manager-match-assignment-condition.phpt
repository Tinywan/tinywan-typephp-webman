--TEST--
Symfony Doctrine pattern: match true with assignment condition and dynamic method call
--FILE--
<?php

final class ResetContainer
{
    private array $methodMap = [
        'cache' => 'resetCache',
    ];

    public function reset(string $name): string
    {
        $method = null;

        return match (true) {
            !$method = $this->methodMap[$name] ?? null => 'missing',
            default => $this->{$method}($name),
        };
    }

    private function resetCache(string $name): string
    {
        return 'reset:'.$name;
    }
}

function main(): void
{
    $container = new ResetContainer();
    var_dump($container->reset('cache'));
    var_dump($container->reset('logger'));
}
?>
--EXPECT--
string(11) "reset:cache"
string(7) "missing"

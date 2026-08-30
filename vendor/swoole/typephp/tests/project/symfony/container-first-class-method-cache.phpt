--TEST--
Symfony pattern: container caches first-class method callable with ??=
--FILE--
<?php

class SymfonyLikeContainer
{
    public array $services = [];

    public function getService(string $id): string
    {
        return 'service:'.$id;
    }

    public function locator(): array
    {
        return [
            $this->getService ??= $this->getService(...),
            ['foo', 'bar'],
        ];
    }
}

function main(): void
{
    $container = new SymfonyLikeContainer();
    [$factory, $ids] = $container->locator();

    foreach ($ids as $id) {
        var_dump($factory($id));
    }

    var_dump($container->getService instanceof Closure);
}
?>
--EXPECT--
string(11) "service:foo"
string(11) "service:bar"
bool(true)

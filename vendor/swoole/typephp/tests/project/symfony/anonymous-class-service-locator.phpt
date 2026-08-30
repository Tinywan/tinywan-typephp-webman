--TEST--
Symfony pattern: anonymous class implements interface with static closure services
--FILE--
<?php

interface SymfonyLikeContainer
{
    public function get(string $id): mixed;
    public function has(string $id): bool;
}

function main(): void
{
    $container = new class([
        'foo' => static fn (): string => 'FOO',
        'bar' => static fn (): string => 'BAR',
    ]) implements SymfonyLikeContainer {
        public function __construct(private array $factories)
        {
        }

        public function get(string $id): mixed
        {
            return ($this->factories[$id])();
        }

        public function has(string $id): bool
        {
            return isset($this->factories[$id]);
        }
    };

    var_dump($container->has('foo'));
    var_dump($container->get('foo'));
    var_dump($container->has('missing'));
}
?>
--EXPECT--
bool(true)
string(3) "FOO"
bool(false)

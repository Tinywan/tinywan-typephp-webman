--TEST--
Symfony pattern: conditional first-class callable stored in property
--FILE--
<?php

class DeferredCallback
{
    private mixed $callback = null;

    public function set(?callable $callback): void
    {
        $this->callback = null !== $callback ? $callback(...) : null;
    }

    public function run(string $value): ?string
    {
        if (null === $callback = $this->callback) {
            return null;
        }

        return $callback($value);
    }
}

function main(): void
{
    $callback = new DeferredCallback();
    $callback->set(static fn (string $value): string => strtoupper($value));
    var_dump($callback->run('symfony'));

    $callback->set(null);
    var_dump($callback->run('symfony'));
}
?>
--EXPECT--
string(7) "SYMFONY"
NULL

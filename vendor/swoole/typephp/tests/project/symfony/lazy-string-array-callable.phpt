--TEST--
Symfony pattern: lazy string resolves array callable with ??= method name
--FILE--
<?php

final class Formatter
{
    public function __invoke(string $value, string $prefix = ''): string
    {
        return $prefix.strtoupper($value);
    }
}

final class LazyString
{
    public mixed $value;

    public static function fromCallable(array|callable $callback, array $arguments): self
    {
        $lazyString = new self();
        $lazyString->value = static function () use (&$callback, &$arguments): string {
            static $value;

            if (null !== $arguments) {
                if (!is_callable($callback)) {
                    $callback[0] = $callback[0]();
                    $callback[1] ??= '__invoke';
                }
                $value = $callback(...$arguments);
                $callback = 'callable';
                $arguments = null;
            }

            return $value;
        };

        return $lazyString;
    }

    public function __toString(): string
    {
        return ($this->value)();
    }
}

function main(): void
{
    $lazy = LazyString::fromCallable([static fn () => new Formatter()], ['symfony', 'app:']);

    var_dump((string) $lazy);
    var_dump((string) $lazy);
}
?>
--EXPECT--
string(11) "app:SYMFONY"
string(11) "app:SYMFONY"

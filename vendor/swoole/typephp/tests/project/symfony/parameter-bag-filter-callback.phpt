--TEST--
Symfony pattern: parameter bag filter callback with coalesce throw
--FILE--
<?php

final class MiniParameterBag
{
    public function __construct(private array $parameters)
    {
    }

    public function filter(string $key, mixed $default, int $filter, array|int $options = []): mixed
    {
        $value = $this->parameters[$key] ?? $default;

        if (is_int($options)) {
            $options = ['flags' => $options];
        }

        if ((FILTER_CALLBACK & $filter) && !(($options['options'] ?? null) instanceof Closure)) {
            throw new InvalidArgumentException('callback filter requires a Closure');
        }

        $options['flags'] ??= 0;

        return filter_var($value, $filter, $options);
    }

    public function getInt(string $key): int
    {
        return $this->filter($key, null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
            ?? throw new UnexpectedValueException('invalid int: '.$key);
    }
}

function main(): void
{
    $bag = new MiniParameterBag([
        'port' => '9501',
        'name' => 'Symfony',
    ]);

    var_dump($bag->getInt('port'));
    var_dump($bag->filter('name', '', FILTER_CALLBACK, [
        'options' => static fn (string $value): string => strtolower($value),
    ]));

    try {
        $bag->getInt('missing');
    } catch (UnexpectedValueException $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
int(9501)
string(7) "symfony"
string(20) "invalid int: missing"

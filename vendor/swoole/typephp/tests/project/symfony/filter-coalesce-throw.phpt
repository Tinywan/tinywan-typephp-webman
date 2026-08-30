--TEST--
Symfony pattern: filter result with coalesce throw expression
--FILE--
<?php

class SymfonyLikeParameterBag
{
    public function __construct(private array $parameters)
    {
    }

    public function filter(string $key, mixed $default, int $filter, array $options): mixed
    {
        return filter_var($this->parameters[$key] ?? $default, $filter, $options);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return $this->filter($key, $default, FILTER_VALIDATE_INT, ['flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE])
            ?? throw new UnexpectedValueException(sprintf('Parameter value "%s" cannot be converted to "int".', $key));
    }
}

function main(): void
{
    $bag = new SymfonyLikeParameterBag(['limit' => '42', 'bad' => 'nope']);
    var_dump($bag->getInt('limit'));

    try {
        $bag->getInt('bad');
    } catch (Throwable $e) {
        var_dump($e::class);
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
int(42)
string(24) "UnexpectedValueException"
string(51) "Parameter value "bad" cannot be converted to "int"."

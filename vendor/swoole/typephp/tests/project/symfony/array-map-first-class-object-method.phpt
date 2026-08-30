--TEST--
Symfony pattern: array_map with first-class object method callable
--FILE--
<?php

class SymfonyLikeParameter
{
    public function __construct(private string $name, private int $position)
    {
    }

    public function toArray(): array
    {
        return [$this->name, $this->position];
    }
}

class SymfonyLikeParameterNormalizer
{
    public function normalize(SymfonyLikeParameter $parameter): string
    {
        [$name, $position] = $parameter->toArray();

        return $position.':'.$name;
    }
}

class SymfonyLikeDescriptor
{
    public function __construct(private SymfonyLikeParameterNormalizer $normalizer)
    {
    }

    public function describe(array $parameters): array
    {
        return array_map($this->normalizer->normalize(...), $parameters);
    }
}

function main(): void
{
    $descriptor = new SymfonyLikeDescriptor(new SymfonyLikeParameterNormalizer());

    var_dump($descriptor->describe([
        new SymfonyLikeParameter('request', 0),
        new SymfonyLikeParameter('format', 1),
    ]));
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(9) "0:request"
  [1]=>
  string(8) "1:format"
}

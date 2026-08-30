--TEST--
Symfony pattern: dynamic method forwarding with unpacked arguments
--FILE--
<?php

class SymfonyLikeInnerSerializer
{
    public function format(string $value, string $prefix = '', string $suffix = ''): string
    {
        return $prefix.strtoupper($value).$suffix;
    }
}

class SymfonyLikeTraceableSerializer
{
    public function __construct(private SymfonyLikeInnerSerializer $serializer)
    {
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->serializer->{$method}(...$arguments);
    }
}

function main(): void
{
    $serializer = new SymfonyLikeTraceableSerializer(new SymfonyLikeInnerSerializer());
    var_dump($serializer->format('symfony', '[', ']'));
}
?>
--EXPECT--
string(9) "[SYMFONY]"

--TEST--
Symfony Contracts pattern: ReflectionNamedType getName or ReflectionType string fallback
--FILE--
<?php

final class ServiceDescriptor
{
    public function named(): ?string
    {
        return null;
    }

    public function union(): int|string|null
    {
        return 1;
    }
}

function describe_return_type(string $method): string
{
    $returnType = (new ReflectionMethod(ServiceDescriptor::class, $method))->getReturnType();
    $type = $returnType instanceof ReflectionNamedType ? $returnType->getName() : (string) $returnType;
    $nullable = $returnType !== null && $returnType->allowsNull();

    return ($nullable ? '?' : '').$type;
}

function main(): void
{
    var_dump(describe_return_type('named'));
    var_dump(describe_return_type('union'));
}
?>
--EXPECT--
string(7) "?string"
string(16) "?string|int|null"

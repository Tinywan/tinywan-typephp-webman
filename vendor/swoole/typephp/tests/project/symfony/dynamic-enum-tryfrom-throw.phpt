--TEST--
Symfony pattern: dynamic BackedEnum tryFrom with coalesce throw
--FILE--
<?php

enum Priority: int
{
    case Low = 1;
    case High = 10;
}

final class Argument
{
    public function __construct(
        public string $name,
        public string $typeName,
        public array $suggestedValues,
    ) {
    }
}

function resolveEnum(Argument $argument, mixed $value): ?BackedEnum
{
    if (null === $value) {
        return null;
    }

    if ($value instanceof $argument->typeName) {
        return $value;
    }

    if (!is_string($value) && !is_int($value)) {
        throw new InvalidArgumentException(get_debug_type($value));
    }

    return $argument->typeName::tryFrom($value)
        ?? throw new InvalidArgumentException(sprintf('Invalid "%s" value "%s".', $argument->name, $value));
}

function main(): void
{
    $argument = new Argument('priority', Priority::class, [1, 10]);

    var_dump(resolveEnum($argument, 10));
    var_dump(resolveEnum($argument, Priority::Low));

    try {
        resolveEnum($argument, 2);
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
enum(Priority::High)
enum(Priority::Low)
Invalid "priority" value "2".

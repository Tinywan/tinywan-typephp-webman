--TEST--
Symfony Validator pattern: array_map(trim(...)) filtered by static arrow callback
--FILE--
<?php
class SymfonyWordCounter
{
    public static function countWords(array $words): int
    {
        return count(array_filter(array_map(trim(...), $words), static fn ($word) => '' !== $word));
    }
}

function main(): void
{
    var_dump(SymfonyWordCounter::countWords([' one ', '', " \t ", 'two', ' three']));
    var_dump(SymfonyWordCounter::countWords([]));
}
?>
--EXPECT--
int(3)
int(0)

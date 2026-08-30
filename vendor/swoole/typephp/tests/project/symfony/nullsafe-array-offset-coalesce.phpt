--TEST--
Symfony pattern: nullsafe method result array offset with coalesce
--FILE--
<?php

class SymfonyLikeStamp
{
    public function __construct(private array $attributes)
    {
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}

function contentType(?SymfonyLikeStamp $stamp): ?string
{
    return $stamp?->getAttributes()['content_type'] ?? null;
}

function main(): void
{
    var_dump(contentType(null));
    var_dump(contentType(new SymfonyLikeStamp([])));
    var_dump(contentType(new SymfonyLikeStamp(['content_type' => 'application/json'])));
}
?>
--EXPECT--
NULL
NULL
string(16) "application/json"

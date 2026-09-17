<?php

namespace TypePhp\Entity;

final readonly class TypedArrayPropertyWritePlan
{
    public function __construct(
        public bool $append,
        public ?string $key,
        public string $value,
    ) {
    }
}

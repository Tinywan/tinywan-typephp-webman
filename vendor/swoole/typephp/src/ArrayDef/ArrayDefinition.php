<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\ArrayDef;

final readonly class ArrayDefinition
{
    public function __construct(
        public ?string $keyType,
        public string $valueType,
    ) {
    }

    public function isList(): bool
    {
        return $this->keyType === null;
    }
}

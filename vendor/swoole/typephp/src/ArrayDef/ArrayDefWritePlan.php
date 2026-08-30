<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\ArrayDef;

final readonly class ArrayDefWritePlan
{
    public function __construct(
        public bool $append,
        public ?string $key,
        public string $value,
    ) {
    }
}

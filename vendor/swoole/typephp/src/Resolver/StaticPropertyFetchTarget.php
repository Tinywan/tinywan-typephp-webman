<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Resolver;

final readonly class StaticPropertyFetchTarget
{
    public function __construct(
        public string $property,
        public ?string $class,
        public ?string $dynamicExpression,
    ) {
    }

    public function isDynamic(): bool
    {
        return $this->dynamicExpression !== null;
    }
}

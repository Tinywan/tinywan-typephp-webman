<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Resolver;

final readonly class StaticPropertyFetchResolution
{
    public function __construct(
        public ?string $class,
        public string $expression,
        public bool $nativeProperty,
    ) {
    }
}

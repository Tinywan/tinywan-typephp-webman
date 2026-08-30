<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Resolver;

use TypePhp\Entity\ClassDef;
use TypePhp\Entity\PropertyDef;

final readonly class PropertyAccessResult
{
    public function __construct(
        public string $requestedClass,
        public string $declaringClass,
        public string $property,
        public ClassDef $classDef,
        public PropertyDef $propertyDef,
    ) {
    }
}

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

final readonly class NativePropertyAccess
{
    public function __construct(
        public string $offset,
        public PropertyAccessResult $resolution,
    ) {
    }

    public function getClassDef(): ClassDef
    {
        return $this->resolution->classDef;
    }

    public function getPropertyDef(): PropertyDef
    {
        return $this->resolution->propertyDef;
    }
}

<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Resolver;

use PhpParser\NodeAbstract;

final readonly class PropertyWriteTarget
{
    public function __construct(
        public NodeAbstract $node,
        public string $label,
        private ?string $objectExpr = null,
        private ?string $propertyExpr = null,
    ) {
    }

    public function isDynamicObjectProperty(): bool
    {
        return $this->objectExpr !== null && $this->propertyExpr !== null;
    }

    public function getDynamicObjectExpr(): string
    {
        if (!$this->isDynamicObjectProperty()) {
            throw new \LogicException('Property write target is not a dynamic object property');
        }

        return $this->objectExpr;
    }

    public function getDynamicPropertyExpr(): string
    {
        if (!$this->isDynamicObjectProperty()) {
            throw new \LogicException('Property write target is not a dynamic object property');
        }

        return $this->propertyExpr;
    }
}

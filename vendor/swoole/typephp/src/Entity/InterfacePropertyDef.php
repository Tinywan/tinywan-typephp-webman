<?php
/**
 * This file is part of TypePHP.
 *
 * Describes an abstract property contract declared by an interface.
 */

namespace TypePhp\Entity;

use PhpParser\Node\Stmt\Property;

final class InterfacePropertyDef
{
    public string $class = '';
    public array $typeCheck = [];
    public string $typeStr = '';

    public function __construct(
        public readonly string $name,
        public readonly int $flags,
        public readonly string $type,
        public readonly bool $nullable,
        public readonly bool $readable,
        public readonly bool $writable,
        public readonly Property $node,
    ) {
    }
}

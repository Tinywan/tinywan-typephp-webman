<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Resolver;

use TypePhp\Entity\ClassDef;
use PhpParser\NodeAbstract;

interface PropertyAccessContext
{
    public function getClassDef(string $name): ?ClassDef;

    public function getParentClass(string $class): string;

    public function fatalError(NodeAbstract $node, string $msg): never;
}

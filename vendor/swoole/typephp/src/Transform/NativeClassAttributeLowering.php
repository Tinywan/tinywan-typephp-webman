<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class NativeClassAttributeLowering extends NodeVisitorAbstract
{
    public const string ATTRIBUTE = 'typephpNativeClass';

    public function enterNode(Node $node): null
    {
        self::lower($node);
        return null;
    }

    public static function lower(Node $node): void
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return;
        }
        if (CompileTimeAttribute::consume($node, 'Native')) {
            $node->setAttribute(self::ATTRIBUTE, true);
        }
    }

    public static function isNative(Node $node): bool
    {
        return $node instanceof Node\Stmt\Class_
            && $node->getAttribute(self::ATTRIBUTE, false) === true;
    }
}

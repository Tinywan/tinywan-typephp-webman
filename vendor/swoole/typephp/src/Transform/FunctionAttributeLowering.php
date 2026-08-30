<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use TypePhp\Exception\SyntaxError;

final class FunctionAttributeLowering
{
    public const MUST_USE_ATTRIBUTE = 'typephpMustUse';
    public const IMMUTABLE_ATTRIBUTE = 'typephpImmutable';
    public const OVERRIDE_ATTRIBUTE = 'typephpOverride';
    public const HOT_ATTRIBUTE = 'typephpHot';
    public const COLD_ATTRIBUTE = 'typephpCold';

    public static function lower(Node $node): void
    {
        foreach (CompileTimeAttributeRegistry::namesForPhase(CompileTimeAttributeRegistry::PHASE_ENTER) as $name) {
            if (!CompileTimeAttribute::has($node, $name)) {
                continue;
            }
            if ($name === 'Immutable' && $node instanceof Node\Param) {
                // Parameter metadata is consumed while building ArgInfo.
                continue;
            }
            if ($name === 'Immutable' && $node instanceof Node\PropertyHook) {
                // Property hooks are later lowered to generated ClassMethod
                // nodes. Carry the effect bit through the source attributes.
                CompileTimeAttribute::consume($node, $name);
                $node->setAttribute(self::IMMUTABLE_ATTRIBUTE, true);
                continue;
            }
            if ($name === 'Override'
                && ($node instanceof Stmt\Property || ($node instanceof Node\Param && $node->isPromoted()))
            ) {
                // Property override validation needs the fully linked parent
                // class, so preserve only an internal marker and consume the
                // compile-time attribute before stub generation.
                CompileTimeAttribute::consume($node, $name);
                $node->setAttribute(self::OVERRIDE_ATTRIBUTE, true);
                continue;
            }
            if (!$node instanceof Stmt\Function_ && !$node instanceof Stmt\ClassMethod) {
                $target = $name === 'Override' ? 'methods or properties' : 'functions or methods';
                throw new SyntaxError($name . ' can only be applied to ' . $target);
            }
            CompileTimeAttribute::consume($node, $name);
            $node->setAttribute('typephp' . $name, true);
        }
    }
}

<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use TypePhp\Exception\SyntaxError;
use TypePhp\Diagnostics\CompileTimeAttributeDiagnostic;

final class GetterLowering
{
    public static function validateTarget(Node $node): void
    {
        if (!CompileTimeAttribute::has($node, 'Getter')) {
            return;
        }

        if ($node instanceof Stmt\Property) {
            if ($node->isStatic()) {
                throw new SyntaxError('Getter can only be applied to instance properties');
            }
            return;
        }

        if ($node instanceof Param && $node->isPromoted()) {
            return;
        }

        throw new SyntaxError('Getter can only be applied to instance properties');
    }

    /** @return list<Stmt\ClassMethod> */
    public static function lowerProperty(Stmt\Property $property): array
    {
        if (CompileTimeAttribute::has($property, 'Getter') && $property->hooks !== []) {
            throw new SyntaxError('Getter cannot be applied to properties with hooks');
        }
        if (!CompileTimeAttribute::consume($property, 'Getter')) {
            return [];
        }

        $methods = [];
        foreach ($property->props as $prop) {
            $methods[] = self::createGetter(
                $prop->name->toString(),
                $property->type,
                $property,
            );
        }
        return $methods;
    }

    public static function lowerPromotedProperty(Param $param): ?Stmt\ClassMethod
    {
        if (CompileTimeAttribute::has($param, 'Getter') && $param->hooks !== []) {
            throw new SyntaxError('Getter cannot be applied to properties with hooks');
        }
        if (!$param->isPromoted() || !is_string($param->var->name) || !CompileTimeAttribute::consume($param, 'Getter')) {
            return null;
        }

        return self::createGetter($param->var->name, $param->type, $param);
    }

    private static function createGetter(string $property, ?Node $type, Node $target): Stmt\ClassMethod
    {
        $method = new Stmt\ClassMethod('get' . ucfirst($property), [
            'flags' => Modifiers::PUBLIC,
            'returnType' => $type === null ? null : clone $type,
            'stmts' => [new Stmt\Return_(new Expr\PropertyFetch(
                new Expr\Variable('this'),
                $property,
            ))],
        ], $target->getAttributes());
        CompileTimeAttributeDiagnostic::markGenerated($method, 'Getter', $target);
        return $method;
    }

}

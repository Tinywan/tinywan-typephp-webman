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

final class PropertyMethodLowering
{
    private const ATTRIBUTES = ['Setter', 'With'];

    public static function validateTarget(Node $node): void
    {
        foreach (self::ATTRIBUTES as $attribute) {
            if (!CompileTimeAttribute::has($node, $attribute)) {
                continue;
            }
            if ($node instanceof Stmt\Property && !$node->isStatic()) {
                continue;
            }
            if ($node instanceof Param && $node->isPromoted()) {
                continue;
            }
            throw new SyntaxError($attribute . ' can only be applied to instance properties');
        }
    }

    /** @return list<Stmt\ClassMethod> */
    public static function lowerProperty(Stmt\Property $property, bool $classReadonly = false): array
    {
        self::validatePropertySemantics($property, $property->hooks !== [], $property->isReadonly() || $classReadonly);
        $setter = CompileTimeAttribute::consume($property, 'Setter');
        $with = CompileTimeAttribute::consume($property, 'With');
        if (!$setter && !$with) {
            return [];
        }

        $methods = [];
        foreach ($property->props as $prop) {
            $name = $prop->name->toString();
            if ($setter) {
                $methods[] = self::createSetter($name, $property->type, $property);
            }
            if ($with) {
                $methods[] = self::createWith($name, $property->type, $property);
            }
        }
        return $methods;
    }

    /** @return list<Stmt\ClassMethod> */
    public static function lowerPromotedProperty(Param $param, bool $classReadonly = false): array
    {
        if (!$param->isPromoted() || !is_string($param->var->name)) {
            return [];
        }
        self::validatePropertySemantics($param, $param->hooks !== [], $param->isReadonly() || $classReadonly);
        $setter = CompileTimeAttribute::consume($param, 'Setter');
        $with = CompileTimeAttribute::consume($param, 'With');
        if (!$setter && !$with) {
            return [];
        }

        $methods = [];
        if ($setter) {
            $methods[] = self::createSetter($param->var->name, $param->type, $param);
        }
        if ($with) {
            $methods[] = self::createWith($param->var->name, $param->type, $param);
        }
        return $methods;
    }

    private static function validatePropertySemantics(Node $node, bool $hasHooks, bool $readonly): void
    {
        foreach (self::ATTRIBUTES as $attribute) {
            if (!CompileTimeAttribute::has($node, $attribute)) {
                continue;
            }
            if ($hasHooks) {
                throw new SyntaxError($attribute . ' cannot be applied to properties with hooks');
            }
            if ($readonly) {
                throw new SyntaxError($attribute . ' cannot be applied to readonly properties');
            }
        }
    }

    private static function createSetter(string $property, ?Node $type, Node $target): Stmt\ClassMethod
    {
        $method = new Stmt\ClassMethod('set' . ucfirst($property), [
            'flags' => Modifiers::PUBLIC,
            'params' => [new Param(new Expr\Variable($property), type: $type === null ? null : clone $type)],
            'returnType' => new Node\Identifier('void'),
            'stmts' => [new Stmt\Expression(new Expr\Assign(
                new Expr\PropertyFetch(new Expr\Variable('this'), $property),
                new Expr\Variable($property),
            ))],
        ], $target->getAttributes());
        CompileTimeAttributeDiagnostic::markGenerated($method, 'Setter', $target);
        return $method;
    }

    private static function createWith(string $property, ?Node $type, Node $target): Stmt\ClassMethod
    {
        $method = new Stmt\ClassMethod('with' . ucfirst($property), [
            'flags' => Modifiers::PUBLIC,
            'params' => [new Param(new Expr\Variable($property), type: $type === null ? null : clone $type)],
            'returnType' => new Node\Name('static'),
            'stmts' => [
                new Stmt\Expression(new Expr\Assign(
                    new Expr\Variable('clone'),
                    new Expr\Clone_(new Expr\Variable('this')),
                )),
                new Stmt\Expression(new Expr\Assign(
                    new Expr\PropertyFetch(new Expr\Variable('clone'), $property),
                    new Expr\Variable($property),
                )),
                new Stmt\Return_(new Expr\Variable('clone')),
            ],
        ], $target->getAttributes());
        CompileTimeAttributeDiagnostic::markGenerated($method, 'With', $target);
        return $method;
    }
}

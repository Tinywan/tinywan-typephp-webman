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
use TypePhp\Exception\CompileTimeAttributeError;
use TypePhp\Diagnostics\CompileTimeAttributeDiagnostic;

final class ConstructorLowering
{
    public const GENERATED_ATTRIBUTE = 'typephpConstructorGenerated';

    public static function validateTarget(Node $node): void
    {
        if (!CompileTimeAttribute::has($node, 'Constructor')) {
            return;
        }
        if (!$node instanceof Stmt\Property || $node->isStatic()) {
            throw new SyntaxError('Constructor can only be applied to instance properties');
        }
    }

    public static function lowerClassLike(Stmt\Class_|Stmt\Trait_|Stmt\Enum_ $class): void
    {
        $declaredConstructor = null;
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Stmt\ClassMethod && $stmt->name->toLowerString() === '__construct') {
                $declaredConstructor = $stmt;
                break;
            }
        }

        $properties = [];
        $target = null;
        foreach ($class->stmts as $stmt) {
            if (!$stmt instanceof Stmt\Property || !CompileTimeAttribute::has($stmt, 'Constructor')) {
                continue;
            }
            if (!$class instanceof Stmt\Class_) {
                throw new SyntaxError('Constructor properties can only be declared in classes');
            }
            $attribute = CompileTimeAttribute::find($stmt, 'Constructor');
            if ($declaredConstructor !== null) {
                $className = $class->name?->toString() ?? 'anonymous class';
                throw new CompileTimeAttributeError(
                    "Constructor cannot generate {$className}::__construct(): method is already declared",
                    $stmt,
                    'Constructor',
                    $attribute ?? $stmt,
                    null,
                    $declaredConstructor,
                );
            }
            CompileTimeAttribute::consume($stmt, 'Constructor');
            $target ??= $stmt;
            foreach ($stmt->props as $property) {
                $properties[] = [
                    $property->name->toString(),
                    $stmt->type,
                    $property->default,
                    $stmt->getAttributes(),
                    $stmt,
                    $attribute,
                ];
            }
        }
        if ($properties === []) {
            return;
        }
        $params = [];
        $stmts = [];
        $optionalSeen = false;
        $optionalTarget = null;
        $optionalAttribute = null;
        foreach ($properties as [$name, $type, $default, $attributes, $propertyTarget, $attributeSource]) {
            if ($default !== null) {
                $optionalSeen = true;
                $optionalTarget ??= $propertyTarget;
                $optionalAttribute ??= $attributeSource;
            } elseif ($optionalSeen) {
                throw new CompileTimeAttributeError(
                    'Constructor required properties cannot follow properties with default values',
                    $propertyTarget,
                    'Constructor',
                    $attributeSource,
                    'Constructor',
                    $optionalAttribute ?? $optionalTarget,
                );
            }
            $params[] = new Param(
                new Expr\Variable($name),
                default: $default === null ? null : clone $default,
                type: $type === null ? null : clone $type,
                attributes: $attributes,
            );
            $stmts[] = new Stmt\Expression(new Expr\Assign(
                new Expr\PropertyFetch(new Expr\Variable('this'), $name),
                new Expr\Variable($name),
            ));
        }
        $constructor = new Stmt\ClassMethod('__construct', [
            'flags' => Modifiers::PUBLIC,
            'params' => $params,
            'stmts' => $stmts,
        ]);
        $constructor->setAttribute(self::GENERATED_ATTRIBUTE, true);
        CompileTimeAttributeDiagnostic::markGenerated($constructor, 'Constructor', $target ?? $class);
        $class->stmts[] = $constructor;
    }
}

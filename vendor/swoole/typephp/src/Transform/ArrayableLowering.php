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
use PhpParser\Node\Stmt;
use TypePhp\Diagnostics\CompileTimeAttributeDiagnostic;

final class ArrayableLowering
{
    public const GENERATED_ATTRIBUTE = 'typephpArrayableGenerated';
    public const FIELDS_ATTRIBUTE = 'typephpArrayableFields';

    public static function lowerClass(Stmt\Class_ $class): void
    {
        $attribute = CompileTimeAttribute::find($class, 'Arrayable');
        if ($attribute === null) {
            return;
        }
        $fields = ClassFieldSelection::parse($attribute, 'Arrayable');
        CompileTimeAttribute::remove($class, 'Arrayable');
        self::appendGeneratedMethod(
            $class,
            $fields ?? ClassFieldSelection::ownPublicProperties($class),
            $fields,
        );
    }

    /**
     * @param list<string> $properties
     * @param list<string>|null $fields
     */
    public static function rebuildGeneratedMethod(Stmt\Class_ $class, array $properties, ?array $fields): void
    {
        self::removeGeneratedMethod($class);
        self::appendGeneratedMethod($class, array_values(array_unique($properties)), $fields);
    }

    public static function removeGeneratedMethod(Stmt\Class_ $class): void
    {
        foreach ($class->stmts as $index => $stmt) {
            if ($stmt instanceof Stmt\ClassMethod && $stmt->getAttribute(self::GENERATED_ATTRIBUTE)) {
                unset($class->stmts[$index]);
            }
        }
        $class->stmts = array_values($class->stmts);
    }

    /**
     * @param list<string> $properties
     * @param list<string>|null $fields
     */
    private static function appendGeneratedMethod(Stmt\Class_ $class, array $properties, ?array $fields): void
    {
        $items = [];
        foreach ($properties as $property) {
            $items[] = new Expr\ArrayItem(
                new Expr\PropertyFetch(new Expr\Variable('this'), $property),
                new Node\Scalar\String_($property),
            );
        }
        $method = new Stmt\ClassMethod('toArray', [
            'flags' => Modifiers::PUBLIC,
            'returnType' => new Node\Identifier('array'),
            'stmts' => [new Stmt\Return_(new Expr\Array_($items))],
        ]);
        $method->setAttribute(self::GENERATED_ATTRIBUTE, true);
        $method->setAttribute(self::FIELDS_ATTRIBUTE, $fields);
        CompileTimeAttributeDiagnostic::markGenerated($method, 'Arrayable', $class);
        $class->stmts[] = $method;
    }
}

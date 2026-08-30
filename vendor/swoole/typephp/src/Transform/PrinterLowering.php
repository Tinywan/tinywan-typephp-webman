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

final class PrinterLowering
{
    public const GENERATED_ATTRIBUTE = 'typephpPrinterGenerated';
    public const FIELDS_ATTRIBUTE = 'typephpPrinterFields';

    public static function lowerClass(Stmt\Class_ $class): void
    {
        $attribute = CompileTimeAttribute::find($class, 'Printer');
        if ($attribute === null) {
            return;
        }
        $fields = ClassFieldSelection::parse($attribute, 'Printer');
        CompileTimeAttribute::remove($class, 'Printer');
        self::appendGeneratedMethod(
            $class,
            $fields ?? ClassFieldSelection::ownPublicProperties($class),
            $fields,
            self::ownStringProperties($class),
        );
    }

    /**
     * @param list<string> $properties
     * @param list<string>|null $fields
     */
    public static function rebuildGeneratedMethod(
        Stmt\Class_ $class,
        array $properties,
        ?array $fields,
        array $stringProperties = [],
    ): void
    {
        self::removeGeneratedMethod($class);
        self::appendGeneratedMethod(
            $class,
            array_values(array_unique($properties)),
            $fields,
            $stringProperties,
        );
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
     * @param list<string> $stringProperties
     */
    private static function appendGeneratedMethod(
        Stmt\Class_ $class,
        array $properties,
        ?array $fields,
        array $stringProperties,
    ): void
    {
        $expression = new Node\Scalar\String_($class->name->toString() . '(');
        foreach ($properties as $index => $property) {
            $prefix = ($index === 0 ? '' : ', ') . $property . '=';
            $value = new Expr\PropertyFetch(new Expr\Variable('this'), $property);
            if (!in_array($property, $stringProperties, true)) {
                $value = new Expr\MethodCall($value, new Node\Identifier('toString'));
            }
            $expression = new Expr\BinaryOp\Concat(
                new Expr\BinaryOp\Concat($expression, new Node\Scalar\String_($prefix)),
                $value,
            );
        }
        $expression = new Expr\BinaryOp\Concat($expression, new Node\Scalar\String_(')'));
        $method = new Stmt\ClassMethod('__toString', [
            'flags' => Modifiers::PUBLIC,
            'returnType' => new Node\Identifier('string'),
            'stmts' => [new Stmt\Return_($expression)],
        ]);
        $method->setAttribute(self::GENERATED_ATTRIBUTE, true);
        $method->setAttribute(self::FIELDS_ATTRIBUTE, $fields);
        CompileTimeAttributeDiagnostic::markGenerated($method, 'Printer', $class);
        $class->stmts[] = $method;
    }

    /** @return list<string> */
    private static function ownStringProperties(Stmt\Class_ $class): array
    {
        $properties = [];
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Stmt\Property && self::isStringType($stmt->type)) {
                foreach ($stmt->props as $property) {
                    $properties[] = $property->name->toString();
                }
            }
            if ($stmt instanceof Stmt\ClassMethod && $stmt->name->toLowerString() === '__construct') {
                foreach ($stmt->params as $param) {
                    if ($param->isPromoted() && is_string($param->var->name) && self::isStringType($param->type)) {
                        $properties[] = $param->var->name;
                    }
                }
            }
        }
        return $properties;
    }

    private static function isStringType(?Node $type): bool
    {
        return $type instanceof Node\Identifier && strtolower($type->name) === 'string';
    }
}

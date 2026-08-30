<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\ArrayDef;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeAbstract;
use TypePhp\Entity\PropertyDef;
use TypePhp\Transform\CompileTimeAttribute;
use TypePhp\Type;

/**
 * Compile-time-only ArrayDef declaration parsing and direct-write checking.
 *
 * This deliberately does not attempt to guard array values that escape into
 * ZendVM calls. ArrayDef is a static contract for syntax the compiler owns.
 */
trait ArrayDefSupportTrait
{
    protected function parseArrayDefinition(Node\Stmt\Property|Node\Param $property): ?ArrayDefinition
    {
        $attribute = CompileTimeAttribute::find($property, 'ArrayDef');
        if ($attribute === null) {
            return null;
        }

        if (!$property->type instanceof Node\Identifier
            || strtolower($property->type->toString()) !== 'array'
        ) {
            $this->fatalError($property, 'ArrayDef can only be applied to properties declared as array');
        }

        $count = count($attribute->args);
        if ($count < 1 || $count > 2) {
            $this->fatalError($attribute, 'ArrayDef expects one or two type arguments');
        }

        $types = [];
        foreach ($attribute->args as $arg) {
            if ($arg->name !== null || !$arg->value instanceof Expr\ClassConstFetch) {
                $this->fatalError($arg, 'ArrayDef arguments must be Type::* or ClassName::class constants');
            }
            $class = $arg->value->class;
            $constant = $arg->value->name;
            if (!$class instanceof Node\Name || !$constant instanceof Node\Identifier) {
                $this->fatalError($arg, 'ArrayDef arguments must be Type::* or ClassName::class constants');
            }
            $resolved = $class->getAttribute('resolvedName') ?? $class;
            $resolvedClass = ltrim($resolved->toString(), '\\');
            if (strcasecmp($constant->toString(), 'class') === 0) {
                if ($this->isNativeObjectClass($resolvedClass)) {
                    $this->fatalError($arg, 'Native class types cannot be used in ArrayDef');
                }
                $types[] = $resolvedClass;
                continue;
            }
            if (strcasecmp($resolvedClass, 'Type') !== 0) {
                $this->fatalError($arg, 'ArrayDef arguments must be Type::* or ClassName::class constants');
            }
            $types[] = $this->resolveArrayDefType($constant->toString(), $arg);
        }

        if ($count === 1) {
            return new ArrayDefinition(null, $types[0]);
        }
        if (!in_array($types[0], [Type::INT, Type::STR], true)) {
            $this->fatalError($attribute, 'ArrayDef map keys must use Type::Int or Type::String');
        }
        return new ArrayDefinition($types[0], $types[1]);
    }

    private function resolveArrayDefType(string $name, NodeAbstract $errorNode): string
    {
        $type = match (strtolower($name)) {
            'int' => Type::INT,
            'float' => Type::FLOAT,
            'bool' => Type::BOOL,
            'string' => Type::STR,
            'array' => Type::ARRAY,
            'object' => Type::OBJECT,
            'any' => Type::VAR,
            'stream' => Type::STREAM,
            'bigint' => Type::BIGINT,
            'bigfloat' => Type::BIGFLOAT,
            'decimal' => Type::DECIMAL,
            default => null,
        };
        if ($type === null) {
            $this->fatalError($errorNode, "Unsupported ArrayDef type Type::{$name}");
        }
        return $type;
    }

    protected function prepareArrayDefDirectWrite(
        Expr\ArrayDimFetch $left,
        Expr $right,
        string $value,
    ): ?ArrayDefWritePlan
    {
        $def = $this->getNativePropertyDef($left->var);
        $arrayDef = $def?->arrayDef;
        if ($arrayDef === null) {
            return null;
        }

        $value = $this->validateArrayDefWriteValue($left->var, $right, $value, $arrayDef->valueType, 'value');
        if ($left->dim === null) {
            if (!$arrayDef->isList()) {
                $this->fatalError($left, 'ArrayDef map properties do not support append writes');
            }
            return new ArrayDefWritePlan(true, null, $value);
        }

        $expectedKey = $arrayDef->keyType ?? Type::INT;
        $key = $this->parseExprAsValue($left->dim);
        $key = $this->validateArrayDefWriteValue($left->var, $left->dim, $key, $expectedKey, 'key');
        if ($arrayDef->isList()) {
            $array = $this->parseWritableIdentifier($left->var);
            // PHP's append index does not shrink after unset(). Element count
            // is therefore not a valid list-write boundary for sparse arrays.
            $key = 'php::safeArrayIndex(' . $key . ', ' . $array . ')';
        }

        return new ArrayDefWritePlan(false, $key, $value);
    }

    private function validateArrayDefWriteValue(
        NodeAbstract $property,
        Expr $expr,
        string $code,
        string $expected,
        string $part,
    ): string
    {
        $actual = $this->detectTypeOfExpr($expr);
        $stdContainerValue = $this->isVarExpr($expr)
            && $this->isStdContainer($this->parseIdentifier($expr));
        if ($part === 'value' && ($stdContainerValue || $this->isStdContainerType($actual))) {
            $this->fatalError($expr, 'Std Container values cannot be stored in ArrayDef properties');
        }
        if ($expected === Type::VAR) {
            return $code;
        }

        if ($this->isArrayDefClassType($expected)) {
            $actualClass = $this->detectClassOfExpr($expr);
            if ($actualClass !== '') {
                if (!$this->isObjectClassStaticallyAssignableTo($actualClass, $expected)) {
                    $this->fatalError(
                        $expr,
                        'ArrayDef property ' . $this->getObjectPropertyTypeCheckDisplayName($property)
                        . ' expects ' . $part . ' of type ' . $expected
                        . ', ' . $actualClass . ' given',
                    );
                }
                return $code;
            }

            if ($actual !== Type::VAR && $actual !== Type::OBJECT) {
                $this->fatalError(
                    $expr,
                    'ArrayDef property ' . $this->getObjectPropertyTypeCheckDisplayName($property)
                    . ' expects ' . $part . ' of type ' . $expected
                    . ', ' . $this->arrayDefTypeName($actual) . ' given',
                );
            }
            return 'php::toObjectExact('
                . $code . ', ' . $this->getClassEntryPtr($expected) . ', '
                . $this->genCharPtr(
                    $this->getObjectPropertyTypeCheckDisplayName($property) . ' ArrayDef ' . $part,
                    true,
                )
                . ')';
        }

        if ($actual !== Type::VAR) {
            if ($actual !== $expected) {
                $this->fatalError(
                    $expr,
                    'ArrayDef property ' . $this->getObjectPropertyTypeCheckDisplayName($property)
                    . ' expects ' . $part . ' of type ' . $this->arrayDefTypeName($expected)
                    . ', ' . $this->arrayDefTypeName($actual) . ' given',
                );
            }
            return $code;
        }

        $display = $this->genCharPtr(
            $this->getObjectPropertyTypeCheckDisplayName($property) . ' ArrayDef ' . $part,
            true,
        );
        $helper = match ($expected) {
            Type::INT => 'php::toIntExact',
            Type::FLOAT => 'php::toFloatExact',
            Type::BOOL => 'php::toBoolExact',
            Type::STR => 'php::toStringExact',
            Type::ARRAY => 'php::toArrayExact',
            Type::OBJECT => 'php::toObjectExact',
            Type::STREAM => 'php::toStreamExact',
            Type::BIGINT => 'php::toBoxExact<php::BigInt>',
            Type::BIGFLOAT => 'php::toBoxExact<php::BigFloat>',
            Type::DECIMAL => 'php::toBoxExact<php::Decimal>',
            default => null,
        };
        if ($helper === null) {
            return $code;
        }
        $boxType = in_array($expected, [Type::BIGINT, Type::BIGFLOAT, Type::DECIMAL], true)
            ? ', ' . $this->genCharPtr($this->arrayDefTypeName($expected), true)
            : '';
        return $helper . '(' . $code . ', ' . $display . $boxType . ')';
    }

    private function arrayDefTypeName(string $type): string
    {
        return match ($type) {
            Type::INT => 'int',
            Type::FLOAT => 'float',
            Type::BOOL => 'bool',
            Type::STR => 'string',
            Type::ARRAY => 'array',
            Type::OBJECT => 'object',
            Type::STREAM => 'stream',
            Type::BIGINT => 'BigInt',
            Type::BIGFLOAT => 'BigFloat',
            Type::DECIMAL => 'Decimal',
            Type::VAR => 'any',
            default => $type,
        };
    }

    private function isArrayDefClassType(string $type): bool
    {
        return !in_array($type, [
            Type::INT,
            Type::FLOAT,
            Type::BOOL,
            Type::STR,
            Type::ARRAY,
            Type::OBJECT,
            Type::VAR,
            Type::STREAM,
            Type::BIGINT,
            Type::BIGFLOAT,
            Type::DECIMAL,
        ], true);
    }

}

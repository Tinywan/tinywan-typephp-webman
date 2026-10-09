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
use PhpParser\NodeAbstract;
use TypePhp\Exception\SyntaxError;

/**
 * Preserve PHP's scalar type system while selecting a narrower C++ field for
 * explicitly sized Native Class properties.
 */
final class NativePropertyTypeLowering
{
    public const string STORAGE_TYPE_ATTRIBUTE = 'typephpNativePropertyStorageType';

    /** @var array<string, array{php: string, cpp: string}> */
    private const array TYPES = [
        'int8' => ['php' => 'int', 'cpp' => 'int8_t'],
        'int16' => ['php' => 'int', 'cpp' => 'int16_t'],
        'int32' => ['php' => 'int', 'cpp' => 'int32_t'],
        'uint8' => ['php' => 'int', 'cpp' => 'uint8_t'],
        'uint16' => ['php' => 'int', 'cpp' => 'uint16_t'],
        'uint32' => ['php' => 'int', 'cpp' => 'uint32_t'],
        'float32' => ['php' => 'float', 'cpp' => 'float'],
    ];

    private const array UNSUPPORTED_TYPES = [
        'uint64' => 'it cannot be represented by php::Int',
    ];

    public static function lowerClass(Stmt\Class_ $class): void
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Stmt\Property) {
                $stmt->type = self::lowerDeclaration($stmt, $stmt->type);
                continue;
            }
            if (!$stmt instanceof Stmt\ClassMethod || $stmt->name->toLowerString() !== '__construct') {
                continue;
            }
            foreach ($stmt->params as $param) {
                if ($param->isPromoted()) {
                    $param->type = self::lowerDeclaration($param, $param->type);
                }
            }
        }
    }

    public static function getStorageType(Node $declaration): string
    {
        $type = $declaration->getAttribute(self::STORAGE_TYPE_ATTRIBUTE, '');
        return is_string($type) ? $type : '';
    }

    private static function lowerDeclaration(
        Node $declaration,
        Node\ComplexType|Node\Identifier|Node\Name|null $type,
    ): Node\ComplexType|Node\Identifier|Node\Name|null {
        $unsupportedType = self::findType($type, self::UNSUPPORTED_TYPES);
        if ($unsupportedType !== null) {
            throw new SyntaxError(
                "Native property type `{$unsupportedType}` is not supported because "
                    . self::UNSUPPORTED_TYPES[$unsupportedType],
            );
        }
        $fixedType = self::findFixedType($type);
        if ($fixedType === null) {
            return $type;
        }
        if (!$type instanceof Node\Name && !$type instanceof Node\Identifier) {
            throw new SyntaxError(
                "Native property type `{$fixedType}` must be declared directly and cannot be nullable or composite",
            );
        }

        $mapping = self::TYPES[$fixedType];
        $declaration->setAttribute(self::STORAGE_TYPE_ATTRIBUTE, $mapping['cpp']);
        return new Node\Identifier($mapping['php'], $type->getAttributes());
    }

    private static function findFixedType(?NodeAbstract $type): ?string
    {
        return self::findType($type, self::TYPES);
    }

    /** @param array<string, mixed> $types */
    private static function findType(?NodeAbstract $type, array $types): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof Node\NullableType) {
            return self::findType($type->type, $types);
        }
        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            foreach ($type->types as $member) {
                $matchedType = self::findType($member, $types);
                if ($matchedType !== null) {
                    return $matchedType;
                }
            }
            return null;
        }
        if (!$type instanceof Node\Name && !$type instanceof Node\Identifier) {
            return null;
        }

        $name = strtolower($type->toString());
        return isset($types[$name]) ? $name : null;
    }
}

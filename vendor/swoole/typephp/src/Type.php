<?php

namespace TypePhp;

final class Type
{
    public const string VAR = 'php::Var';
    public const string ANY = 'php::Var';
    public const string BOOL = 'php::Bool';
    public const string INT = 'php::Int';
    public const string FLOAT = 'php::Float';
    public const string OBJECT = 'php::Object';
    public const string ARRAY = 'php::Array';
    public const string RESOURCE = 'php::Resource';
    public const string STREAM = 'php::Stream';
    public const string BIGINT = 'php::BigInt';
    public const string DECIMAL = 'php::Decimal';
    public const string BIGFLOAT = 'php::BigFloat';
    public const string BOX = 'php::Box';
    public const string STD_ARRAY = 'php::StdArray';
    public const string STD_VECTOR = 'php::StdVector';
    public const string STD_MAP = 'php::StdMap';
    public const string STD_ORDERED_MAP = 'php::StdOrderedMap';
    public const string ARGS = 'php::Args';
    public const string STR = 'php::Str';
    public const string REF = 'php::Ref';
    public const string INT_REF = 'php::Int &';
    public const string STR_REF = 'php::Str &';
    public const string FLOAT_REF = 'php::Float &';
    public const string BOOL_REF = 'php::Bool &';
    public const string ARRAY_REF = 'php::Array &';
    public const string VOID = 'void';

    public static function isTypedRefType(string $type): bool
    {
        return in_array($type, [
            self::INT_REF,
            self::STR_REF,
            self::FLOAT_REF,
            self::BOOL_REF,
            self::ARRAY_REF,
        ], true);
    }

    public static function isAnyRefType(string $type): bool
    {
        return $type === self::REF || self::isTypedRefType($type);
    }

    public static function getReferenceType(string $type): ?string
    {
        return match ($type) {
            self::INT => self::INT_REF,
            self::STR => self::STR_REF,
            self::FLOAT => self::FLOAT_REF,
            self::BOOL => self::BOOL_REF,
            self::ARRAY => self::ARRAY_REF,
            default => null,
        };
    }

    public static function getReferencedType(string $type): string
    {
        return match ($type) {
            self::INT_REF => self::INT,
            self::STR_REF => self::STR,
            self::FLOAT_REF => self::FLOAT,
            self::BOOL_REF => self::BOOL,
            self::ARRAY_REF => self::ARRAY,
            default => $type,
        };
    }

    /**
     * Return the C++ expression for the initial state of a fixed value type.
     *
     * Fixed TypePHP storage never becomes UNDEF: unset() releases the current
     * value and restores this state instead. Objects are handled separately
     * because null is their valid empty state rather than a value-type default.
     */
    public static function getDefaultValueExpression(string $type): ?string
    {
        return match ($type) {
            self::INT => '0',
            self::FLOAT => '0.0',
            self::BOOL => 'false',
            self::STR => self::STR . '()',
            self::ARRAY => self::ARRAY . '{}',
            default => null,
        };
    }
}

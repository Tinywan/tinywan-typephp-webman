<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Resolver;

use TypePhp\Type;

use TypePhp\Entity\PropertyDef;

final class PropertyAssignTypeInfo
{
    public function getFixedDefaultValue(PropertyDef $def): ?string
    {
        $typeDefault = Type::getDefaultValueExpression($def->type);
        if ($typeDefault === null) {
            return null;
        }
        return $def->default ?? $typeDefault;
    }

    public function isFixed(PropertyDef $def): bool
    {
        return in_array($def->type, [
            Type::INT,
            Type::FLOAT,
            Type::BOOL,
            Type::STR,
            Type::ARRAY,
        ], true) && !$def->nullable;
    }

    public function getRuntimeTypeCheck(PropertyDef $def): array
    {
        if (!empty($def->typeCheck)) {
            return $def->typeCheck;
        }
        $check = [];
        if ($def->nullable) {
            $check[] = ['kind' => 'isNull'];
        }
        $scalarCheck = match ($def->type) {
            Type::INT => [['kind' => 'isInt']],
            Type::FLOAT => [['kind' => 'isFloat'], ['kind' => 'isInt']],
            Type::BOOL => [['kind' => 'isBool']],
            Type::STR => [['kind' => 'isString']],
            Type::ARRAY => [['kind' => 'isArray']],
            default => null,
        };
        if ($scalarCheck !== null) {
            return array_merge($check, $scalarCheck);
        }
        if ($def->type !== Type::OBJECT || $def->class === '') {
            return [];
        }

        $check[] = ['kind' => 'instanceof', 'class' => $def->class];
        return $check;
    }

    public function getTypeString(PropertyDef $def): string
    {
        if ($def->typeStr !== '') {
            return $def->typeStr;
        }
        if ($def->class !== '') {
            return ($def->nullable ? '?' : '') . $def->class;
        }
        return match ($def->type) {
            Type::INT => 'int',
            Type::FLOAT => 'float',
            Type::BOOL => 'bool',
            Type::STR => 'string',
            Type::ARRAY => 'array',
            Type::OBJECT => 'object',
            default => $def->type,
        };
    }
}

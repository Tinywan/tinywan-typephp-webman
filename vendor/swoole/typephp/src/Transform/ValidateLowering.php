<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\UnionType;
use TypePhp\Exception\SyntaxError;

final class ValidateLowering
{
    public static function createCheck(Param $param, Node\Attribute $attribute): Stmt\If_
    {
        $parameter = is_string($param->var->name) ? $param->var->name : '';
        [$filter, $options, $message] = self::parseArguments($parameter, $attribute);
        self::assertParameterTypeCompatible($param, $filter, $options);
        $call = new Expr\FuncCall(new Node\Name\FullyQualified('filter_var'), [
            new Node\Arg(new Expr\Variable($parameter)),
            new Node\Arg(new Node\Scalar\Int_($filter)),
            new Node\Arg(self::withNullOnFailure($options)),
        ]);

        return new Stmt\If_(new Expr\BinaryOp\Identical($call, new Expr\ConstFetch(new Node\Name('null'))), [
            'stmts' => [new Stmt\Expression(new Expr\Throw_(new Expr\New_(
                new Node\Name\FullyQualified('ValueError'),
                [new Node\Arg($message)],
            )))],
        ]);
    }

    /** @return array{int, Expr, Expr} */
    private static function parseArguments(string $parameter, Node\Attribute $attribute): array
    {
        $values = [];
        $positions = ['filter', 'options', 'message'];
        foreach ($attribute->args as $index => $arg) {
            $name = $arg->name?->toString() ?? ($positions[$index] ?? null);
            if ($name === null || !in_array($name, $positions, true)) {
                throw new SyntaxError('Validate has an unknown argument');
            }
            if (isset($values[$name])) {
                throw new SyntaxError('Validate argument $' . $name . ' is specified more than once');
            }
            $values[$name] = $arg->value;
        }
        if (!isset($values['filter'])) {
            throw new SyntaxError('Validate requires the $filter argument');
        }

        $filter = self::resolveFilter($values['filter']);
        if (!in_array($filter, self::validationFilters(), true)) {
            throw new SyntaxError('Validate only accepts FILTER_VALIDATE_* filters');
        }
        $options = isset($values['options']) ? clone $values['options'] : new Node\Scalar\Int_(0);
        if (!$options instanceof Node\Scalar\Int_ && !$options instanceof Expr\Array_
            && !$options instanceof Expr\ConstFetch
            && !$options instanceof Expr\BinaryOp\BitwiseOr) {
            throw new SyntaxError('Validate $options must be an integer flag or an array literal');
        }
        $defaultMessage = new Node\Scalar\String_('Parameter $' . $parameter . ' is invalid');
        $message = isset($values['message']) ? clone $values['message'] : $defaultMessage;
        if ($message instanceof Expr\ConstFetch && $message->name->toLowerString() === 'null') {
            $message = $defaultMessage;
        }
        if (!$message instanceof Node\Scalar\String_ && !$message instanceof Expr\ConstFetch
            && !$message instanceof Expr\ClassConstFetch) {
            throw new SyntaxError('Validate $message must be a string or null');
        }

        return [$filter, $options, $message];
    }

    private static function assertParameterTypeCompatible(Param $param, int $filter, Expr $options): void
    {
        if ($param->type === null || self::typeMayPassFilter($param->type, $filter, self::resolveFlags($options))) {
            return;
        }
        $name = is_string($param->var->name) ? $param->var->name : '';
        throw new SyntaxError(
            'Validate filter ' . self::filterName($filter) . ' is incompatible with parameter `$' .
            $name . '` declared as `' . $param->type->toString() . '`',
        );
    }

    private static function typeMayPassFilter(Node $type, int $filter, ?int $flags): bool
    {
        if ($type instanceof NullableType) {
            return self::typeMayPassFilter($type->type, $filter, $flags);
        }
        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            foreach ($type->types as $member) {
                if (self::typeMayPassFilter($member, $filter, $flags)) {
                    return true;
                }
            }
            return false;
        }
        if (!$type instanceof Node\Identifier) {
            // Named object types may implement __toString(); without full class
            // resolution they are not provably incompatible with filter_var().
            return true;
        }

        $name = strtolower($type->name);
        if ($name === 'array') {
            return $flags === null
                || (bool) ($flags & (FILTER_REQUIRE_ARRAY | FILTER_FORCE_ARRAY));
        }
        if (!in_array($name, ['int', 'float', 'bool', 'true', 'false', 'null'], true)) {
            return true;
        }
        return !in_array($filter, self::stringShapeFilters(), true);
    }

    /** @return list<int> */
    private static function stringShapeFilters(): array
    {
        return array_values(array_filter([
            defined('FILTER_VALIDATE_EMAIL') ? FILTER_VALIDATE_EMAIL : null,
            defined('FILTER_VALIDATE_URL') ? FILTER_VALIDATE_URL : null,
            defined('FILTER_VALIDATE_IP') ? FILTER_VALIDATE_IP : null,
            defined('FILTER_VALIDATE_MAC') ? FILTER_VALIDATE_MAC : null,
        ], static fn ($value): bool => is_int($value)));
    }

    private static function resolveFlags(Expr $options): ?int
    {
        if ($options instanceof Node\Scalar\Int_) {
            return $options->value;
        }
        if ($options instanceof Expr\ConstFetch) {
            $name = ltrim($options->name->toString(), '\\');
            return defined($name) && is_int(constant($name)) ? constant($name) : null;
        }
        if ($options instanceof Expr\BinaryOp\BitwiseOr) {
            $left = self::resolveFlags($options->left);
            $right = self::resolveFlags($options->right);
            return $left === null || $right === null ? null : $left | $right;
        }
        if (!$options instanceof Expr\Array_) {
            return null;
        }
        foreach ($options->items as $item) {
            if ($item?->key instanceof Node\Scalar\String_ && $item->key->value === 'flags') {
                return self::resolveFlags($item->value);
            }
        }
        return 0;
    }

    private static function filterName(int $filter): string
    {
        foreach (get_defined_constants(true)['filter'] ?? [] as $name => $value) {
            if ($value === $filter && str_starts_with($name, 'FILTER_VALIDATE_')) {
                return $name;
            }
        }
        return (string) $filter;
    }

    private static function resolveFilter(Expr $expr): int
    {
        if ($expr instanceof Node\Scalar\Int_) {
            return $expr->value;
        }
        if ($expr instanceof Expr\ConstFetch) {
            $name = ltrim($expr->name->toString(), '\\');
            if (defined($name) && is_int(constant($name))) {
                return constant($name);
            }
        }
        throw new SyntaxError('Validate $filter must be a FILTER_VALIDATE_* constant');
    }

    /** @return list<int> */
    private static function validationFilters(): array
    {
        $filters = [];
        foreach (get_defined_constants(true)['filter'] ?? [] as $name => $value) {
            if (str_starts_with($name, 'FILTER_VALIDATE_') && is_int($value)) {
                $filters[] = $value;
            }
        }
        return array_values(array_unique($filters));
    }

    private static function withNullOnFailure(Expr $options): Expr
    {
        $flag = new Node\Scalar\Int_(FILTER_NULL_ON_FAILURE);
        if (!$options instanceof Expr\Array_) {
            return new Expr\BinaryOp\BitwiseOr($options, $flag);
        }
        $flagsItem = null;
        foreach ($options->items as $item) {
            if ($item?->unpack) {
                throw new SyntaxError('Validate $options does not support array unpacking');
            }
            if ($item !== null && $item->key instanceof Node\Scalar\String_ && $item->key->value === 'flags') {
                $flagsItem = $item;
            }
        }
        if ($flagsItem !== null) {
            $flagsItem->value = new Expr\BinaryOp\BitwiseOr($flagsItem->value, $flag);
            return $options;
        }
        $options->items[] = new Expr\ArrayItem($flag, new Node\Scalar\String_('flags'));
        return $options;
    }
}

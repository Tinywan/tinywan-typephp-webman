<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use PhpParser\Node\Expr;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\UnionType;
use TypePhp\Exception\CompileTimeAttributeError;
use TypePhp\Exception\SyntaxError;

final class ParameterValidationLowering
{
    public static function lowerFunction(
        Stmt\Function_|Stmt\ClassMethod|Expr\Closure $function,
        ?callable $warning = null,
    ): void
    {
        $checks = [];
        foreach ($function->params as $param) {
            if (!is_string($param->var->name)) {
                continue;
            }
            $name = $param->var->name;
            $notNull = CompileTimeAttribute::find($param, 'NotNull');
            if ($notNull !== null) {
                if ($notNull->args !== []) {
                    throw new CompileTimeAttributeError(
                        'NotNull does not accept arguments',
                        $param,
                        'NotNull',
                        $notNull,
                    );
                }
                if ($warning !== null && self::isExplicitlyNullable($param)) {
                    $warning($param, 'NotNull is applied to nullable parameter `$' . $name . '`');
                }
                $checks[] = NotNullLowering::createCheck($name);
            }
            $notEmpty = CompileTimeAttribute::find($param, 'NotEmpty');
            if ($notEmpty !== null) {
                if ($notEmpty->args !== []) {
                    throw new CompileTimeAttributeError(
                        'NotEmpty does not accept arguments',
                        $param,
                        'NotEmpty',
                        $notEmpty,
                    );
                }
                $checks[] = NotEmptyLowering::createCheck($name);
            }
            $validate = CompileTimeAttribute::find($param, 'Validate');
            if ($validate !== null) {
                try {
                    $checks[] = ValidateLowering::createCheck($param, $validate);
                } catch (SyntaxError $error) {
                    throw new CompileTimeAttributeError(
                        $error->getMessage(),
                        $param,
                        'Validate',
                        $validate,
                        previous: $error,
                    );
                }
            }
            CompileTimeAttribute::remove($param, 'NotNull');
            CompileTimeAttribute::remove($param, 'NotEmpty');
            CompileTimeAttribute::remove($param, 'Validate');
        }
        if ($checks !== []) {
            if ($function->stmts === null) {
                throw new SyntaxError('Parameter validation requires a concrete function or method');
            }
            $function->stmts = [...$checks, ...$function->stmts];
        }
    }

    private static function isExplicitlyNullable(Param $param): bool
    {
        if ($param->type instanceof NullableType) {
            return true;
        }
        if (!$param->type instanceof UnionType) {
            return false;
        }
        foreach ($param->type->types as $type) {
            if (strcasecmp($type->toString(), 'null') === 0) {
                return true;
            }
        }
        return false;
    }

    public static function rejectArrowFunction(Expr\ArrowFunction $function): void
    {
        foreach ($function->params as $param) {
            foreach (['NotNull', 'NotEmpty', 'Validate'] as $name) {
                $attribute = CompileTimeAttribute::find($param, $name);
                if ($attribute !== null) {
                    throw new CompileTimeAttributeError(
                        $name . ' is not supported on arrow function parameters',
                        $param,
                        $name,
                        $attribute,
                    );
                }
            }
        }
    }
}

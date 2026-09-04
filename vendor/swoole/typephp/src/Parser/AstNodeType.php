<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Parser;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\VariadicPlaceholder;

trait AstNodeType
{
    /** @phpstan-assert-if-true Expr\ArrayDimFetch $expr */
    protected function isArrayDimFetch(Node $expr): bool
    {
        return $expr instanceof Expr\ArrayDimFetch;
    }

    /** @phpstan-assert-if-true Expr\Variable $expr */
    protected function isVarExpr(Node $expr): bool
    {
        return $expr instanceof Expr\Variable;
    }

    /** @phpstan-assert-if-true Node\Identifier $expr */
    protected function isIdExpr(Node $expr): bool
    {
        return $expr instanceof Node\Identifier;
    }

    /** @phpstan-assert-if-true Expr\PropertyFetch $expr */
    protected function isPropertyFetch(Node $expr): bool
    {
        return $expr instanceof Expr\PropertyFetch;
    }

    /** @phpstan-assert-if-true Expr\StaticPropertyFetch $expr */
    protected function isStaticPropertyFetch(Node $expr): bool
    {
        return $expr instanceof Expr\StaticPropertyFetch;
    }

    /** @phpstan-assert-if-true Expr\ClassConstFetch $expr */
    protected function isClassConstFetch(Node $expr): bool
    {
        return $expr instanceof Expr\ClassConstFetch;
    }

    /** @phpstan-assert-if-true Expr\New_ $expr */
    protected function isNewExpr(Node $expr): bool
    {
        return $expr instanceof Expr\New_;
    }

    /** @phpstan-assert-if-true Node\Name $expr */
    protected function isNameExpr(Node $expr): bool
    {
        return $expr instanceof Node\Name;
    }

    /** @phpstan-assert-if-true Node\Name\FullyQualified $expr */
    protected function isFullNameExpr(Node $expr): bool
    {
        return $expr instanceof Node\Name\FullyQualified;
    }

    /** @phpstan-assert-if-true Node\Identifier $expr */
    protected function isNamedMethod(Node $expr): bool
    {
        return $this->isIdExpr($expr);
    }

    /** @phpstan-assert-if-true Node\Scalar\String_ $expr */
    protected function isScalarString(Node $expr): bool
    {
        return $expr instanceof Node\Scalar\String_;
    }

    /** @phpstan-assert-if-true Expr\FuncCall $expr */
    protected function isFuncCallExpr(Node $expr): bool
    {
        return $expr instanceof Expr\FuncCall;
    }

    /** @phpstan-assert-if-true Expr\FuncCall $expr */
    protected function isRefvalCall(Node $expr): bool
    {
        return $this->isFuncCallExpr($expr) and $this->isNameExpr($expr->name) and $expr->name->toString() === 'refval';
    }

    /** @phpstan-assert-if-true Expr\MethodCall $expr */
    protected function isMethodCall(Node $expr): bool
    {
        return $expr instanceof Expr\MethodCall;
    }

    /** @phpstan-assert-if-true Expr\StaticCall $expr */
    protected function isStaticCall(Node $expr): bool
    {
        return $expr instanceof Expr\StaticCall;
    }

    /** @phpstan-assert-if-true Node\Scalar $expr */
    protected function isScalar(Node $expr): bool
    {
        return $expr instanceof Node\Scalar;
    }

    /** @phpstan-assert-if-true Node\Scalar\Int_ $expr */
    protected function isScalarInt(Node $expr): bool
    {
        return $expr instanceof Node\Scalar\Int_;
    }

    /** @phpstan-assert-if-true Expr\ConstFetch $expr */
    protected function isScalarBool(Node $expr): bool
    {
        return $expr instanceof Expr\ConstFetch and in_array(strtolower($expr->name->toString()), ['true', 'false']);
    }

    /** @phpstan-assert-if-true Expr\Match_ $expr */
    protected function isMatchExpr(Node $expr): bool
    {
        return $expr instanceof Expr\Match_;
    }

    /** @phpstan-assert-if-true Expr\Assign $expr */
    protected function isAssignExpr(Node $expr): bool
    {
        return $expr instanceof Expr\Assign;
    }

    /** @phpstan-assert-if-true Expr\FuncCall|Expr\MethodCall|Expr\StaticCall $expr */
    protected function isCallExpr(Node $expr): bool
    {
        return $expr instanceof Expr\FuncCall
            or $expr instanceof Expr\MethodCall
            or $expr instanceof Expr\StaticCall;
    }

    /** @phpstan-assert-if-true VariadicPlaceholder $expr */
    protected function isPlaceholderExpr(Node $expr): bool
    {
        return $expr instanceof VariadicPlaceholder;
    }

    /** @phpstan-assert-if-true Node\Stmt\Return_ $expr */
    protected function isReturnExpr(Node $expr): bool
    {
        return $expr instanceof Node\Stmt\Return_;
    }

    /** @phpstan-assert-if-true Node\Stmt\Break_ $expr */
    protected function isBreakExpr(Node $expr): bool
    {
        return $expr instanceof Node\Stmt\Break_;
    }

    /** @phpstan-assert-if-true Node\Stmt\Continue_ $expr */
    protected function isContinueExpr(Node $expr): bool
    {
        return $expr instanceof Node\Stmt\Continue_;
    }

    protected function isThrowExpr(Node $expr): bool
    {
        if ($expr instanceof Node\Stmt\Expression) {
            $expr = $expr->expr;
        }
        return $expr instanceof Expr\Throw_;
    }

    protected function isExitExpr(Node $expr): bool
    {
        if ($expr instanceof Node\Stmt\Expression) {
            $expr = $expr->expr;
        }
        return $expr instanceof Expr\Exit_;
    }

    /** @phpstan-assert-if-true Expr\Array_ $expr */
    protected function isEmptyArray(Node $expr): bool
    {
        return $expr instanceof Expr\Array_ && count($expr->items) === 0;
    }

    /** @phpstan-assert-if-true Expr\ConstFetch $expr */
    protected function isNull(Node $expr): bool
    {
        return $expr instanceof Expr\ConstFetch && strcasecmp($expr->name->toString(), 'null') === 0;
    }
}

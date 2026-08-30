<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use TypePhp\Exception\SyntaxError;

/**
 * Implements PHP's reusable constant-expression whitelist and validation.
 *
 * The allowDynamic context flag matches zend_const_expr_to_zval(): it controls
 * `new` and object casts, while the node whitelist remains shared by attributes,
 * constants, defaults and other constant-expression contexts.
 */
final class ConstantExpressionValidator
{
    private readonly bool $php85;

    public function __construct(string $phpVersion)
    {
        $this->php85 = version_compare($phpVersion, '8.5', '>=');
    }

    public function validate(Expr $expression, bool $allowDynamic = false): void
    {
        $this->validateExpression($expression, $allowDynamic);
    }

    /** @param list<Node\Arg|Node\VariadicPlaceholder> $arguments */
    public function validateArguments(
        array $arguments,
        bool $allowDynamic = false,
        bool $attributeArgumentList = false,
    ): void
    {
        $usesNamedArguments = false;
        $namedArguments = [];
        foreach ($arguments as $argument) {
            if (!$argument instanceof Node\Arg) {
                $this->invalidOperation();
            }
            if ($argument->unpack) {
                throw new SyntaxError($attributeArgumentList
                    ? 'Cannot use unpacking in attribute argument list'
                    : 'Argument unpacking in constant expressions is not supported');
            }
            if ($argument->byRef) {
                $this->invalidOperation();
            }
            if ($argument->name !== null) {
                $usesNamedArguments = true;
                if ($attributeArgumentList) {
                    $name = $argument->name->toString();
                    if (isset($namedArguments[$name])) {
                        throw new SyntaxError("Duplicate named parameter \${$name}");
                    }
                    $namedArguments[$name] = true;
                }
            } elseif ($usesNamedArguments) {
                throw new SyntaxError('Cannot use positional argument after named argument');
            }
            $this->validateExpression($argument->value, $allowDynamic);
        }
    }

    /** Equivalent to php-src's zend_is_allowed_in_const_expr(). */
    public function isAllowedInConstantExpression(Expr $expression): bool
    {
        return $expression instanceof Node\Scalar\Int_
            || $expression instanceof Node\Scalar\Float_
            || $expression instanceof Node\Scalar\String_
            || $expression instanceof Node\Scalar\MagicConst
            || $expression instanceof Expr\ConstFetch
            || ($expression instanceof Expr\BinaryOp
                && !$expression instanceof Expr\BinaryOp\Pipe)
            || $expression instanceof Expr\UnaryPlus
            || $expression instanceof Expr\UnaryMinus
            || $expression instanceof Expr\BooleanNot
            || $expression instanceof Expr\BitwiseNot
            || $expression instanceof Expr\Ternary
            || $expression instanceof Expr\ArrayDimFetch
            || $expression instanceof Expr\Array_
            || $expression instanceof Expr\ClassConstFetch
            || $expression instanceof Expr\PropertyFetch
            || $expression instanceof Expr\NullsafePropertyFetch
            || $expression instanceof Expr\New_
            || ($this->php85 && (($expression instanceof Expr\Cast
                    && !$expression instanceof Expr\Cast\Void_)
                || $expression instanceof Expr\Closure
                || $expression instanceof Expr\FuncCall
                || $expression instanceof Expr\StaticCall));
    }

    private function validateExpression(Expr $expression, bool $allowDynamic): void
    {
        if (!$this->isAllowedInConstantExpression($expression)) {
            $this->invalidOperation();
        }

        if ($expression instanceof Node\Scalar
            || $expression instanceof Expr\ConstFetch) {
            return;
        }

        if ($expression instanceof Expr\BinaryOp\BooleanAnd
            || $expression instanceof Expr\BinaryOp\LogicalAnd
            || $expression instanceof Expr\BinaryOp\BooleanOr
            || $expression instanceof Expr\BinaryOp\LogicalOr) {
            $this->validateExpression($expression->left, $allowDynamic);
            [$known, $left] = $this->tryEvaluate($expression->left);
            $isAnd = $expression instanceof Expr\BinaryOp\BooleanAnd
                || $expression instanceof Expr\BinaryOp\LogicalAnd;
            if (!$known || ($isAnd ? (bool) $left : !(bool) $left)) {
                $this->validateExpression($expression->right, $allowDynamic);
            }
            return;
        }

        if ($expression instanceof Expr\BinaryOp\Coalesce) {
            $this->validateExpression($expression->left, $allowDynamic);
            [$known, $left] = $this->tryEvaluate($expression->left);
            if (!$known || $left === null) {
                $this->validateExpression($expression->right, $allowDynamic);
            }
            return;
        }

        if ($expression instanceof Expr\BinaryOp) {
            $this->validateExpression($expression->left, $allowDynamic);
            $this->validateExpression($expression->right, $allowDynamic);
            return;
        }

        if ($expression instanceof Expr\UnaryPlus
            || $expression instanceof Expr\UnaryMinus
            || $expression instanceof Expr\BooleanNot
            || $expression instanceof Expr\BitwiseNot) {
            $this->validateExpression($expression->expr, $allowDynamic);
            return;
        }

        if ($expression instanceof Expr\Ternary) {
            $this->validateExpression($expression->cond, $allowDynamic);
            [$known, $condition] = $this->tryEvaluate($expression->cond);
            if ($known) {
                if ((bool) $condition) {
                    if ($expression->if !== null) {
                        $this->validateExpression($expression->if, $allowDynamic);
                    }
                } else {
                    $this->validateExpression($expression->else, $allowDynamic);
                }
                return;
            }
            if ($expression->if !== null) {
                $this->validateExpression($expression->if, $allowDynamic);
            }
            $this->validateExpression($expression->else, $allowDynamic);
            return;
        }

        if ($expression instanceof Expr\ArrayDimFetch) {
            $this->validateExpression($expression->var, $allowDynamic);
            if ($expression->dim === null) {
                throw new SyntaxError('Cannot use [] for reading');
            }
            $this->validateExpression($expression->dim, $allowDynamic);
            return;
        }

        if ($expression instanceof Expr\Array_) {
            foreach ($expression->items as $item) {
                if ($item === null || $item->byRef) {
                    $this->invalidOperation();
                }
                if ($item->key !== null) {
                    $this->validateExpression($item->key, $allowDynamic);
                }
                $this->validateExpression($item->value, $allowDynamic);
            }
            return;
        }

        if ($expression instanceof Expr\ClassConstFetch) {
            $this->validateClassConstantFetch($expression, $allowDynamic);
            return;
        }

        if ($expression instanceof Expr\PropertyFetch
            || $expression instanceof Expr\NullsafePropertyFetch) {
            $this->validateExpression($expression->var, $allowDynamic);
            if ($expression->name instanceof Expr) {
                $this->validateExpression($expression->name, $allowDynamic);
            }
            return;
        }

        if ($expression instanceof Expr\New_) {
            if (!$allowDynamic) {
                throw new SyntaxError('New expressions are not supported in this context');
            }
            $this->validateNew($expression, $allowDynamic);
            return;
        }

        if ($this->php85 && $expression instanceof Expr\Cast) {
            if ($expression instanceof Expr\Cast\Object_ && !$allowDynamic) {
                throw new SyntaxError('Object casts are not supported in this context');
            }
            $this->validateExpression($expression->expr, $allowDynamic);
            return;
        }

        if ($this->php85 && $expression instanceof Expr\Closure) {
            if (!$expression->static) {
                throw new SyntaxError('Closures in constant expressions must be static');
            }
            if ($expression->uses !== []) {
                throw new SyntaxError('Cannot use(...) variables in constant expression');
            }
            // PHP compiles the closure body normally instead of treating its
            // statements as children of the surrounding constant expression.
            return;
        }

        if ($this->php85 && ($expression instanceof Expr\FuncCall
                || $expression instanceof Expr\StaticCall)) {
            $this->validateFirstClassCallable($expression);
            return;
        }

        $this->invalidOperation();
    }

    private function validateClassConstantFetch(
        Expr\ClassConstFetch $expression,
        bool $allowDynamic,
    ): void
    {
        if (!$expression->class instanceof Node\Name) {
            if ($expression->name instanceof Node\Identifier
                && strtolower($expression->name->toString()) === 'class') {
                throw new SyntaxError('(expression)::class cannot be used in constant expressions');
            }
            throw new SyntaxError('Dynamic class names are not allowed in compile-time class constant references');
        }

        $class = strtolower($expression->class->toString());
        if ($class === 'static') {
            if ($expression->name instanceof Node\Identifier
                && strtolower($expression->name->toString()) === 'class') {
                throw new SyntaxError('static::class cannot be used for compile-time class name resolution');
            }
            throw new SyntaxError('"static::" is not allowed in compile-time constants');
        }

        if ($expression->name instanceof Expr) {
            $this->validateExpression($expression->name, $allowDynamic);
        }
    }

    private function validateNew(Expr\New_ $expression, bool $allowDynamic): void
    {
        if ($expression->class instanceof Node\Stmt\Class_) {
            throw new SyntaxError('Cannot use anonymous class in constant expression');
        }
        if (!$expression->class instanceof Node\Name) {
            throw new SyntaxError('Cannot use dynamic class name in constant expression');
        }
        if (strtolower($expression->class->toString()) === 'static') {
            throw new SyntaxError('"static" is not allowed in compile-time constants');
        }

        if ($expression->isFirstClassCallable()) {
            throw new SyntaxError('Cannot create Closure for new expression');
        }
        $this->validateArguments($expression->args, $allowDynamic);
    }

    /** @param Expr\FuncCall|Expr\StaticCall $expression */
    private function validateFirstClassCallable(Expr $expression): void
    {
        if (!$expression->isFirstClassCallable()) {
            $this->invalidOperation();
        }

        if ($expression instanceof Expr\FuncCall) {
            if (!$expression->name instanceof Node\Name) {
                throw new SyntaxError('Cannot use dynamic function name in constant expression');
            }
            return;
        }

        if ($expression->class instanceof Node\Stmt\Class_) {
            throw new SyntaxError('Cannot use anonymous class in constant expression');
        }
        if (!$expression->class instanceof Node\Name) {
            throw new SyntaxError('Cannot use dynamic class name in constant expression');
        }
        if (strtolower($expression->class->toString()) === 'static') {
            throw new SyntaxError('"static" is not allowed in compile-time constants');
        }
        if (!$expression->name instanceof Node\Identifier) {
            throw new SyntaxError('Cannot use dynamic method name in constant expression');
        }
    }

    private function invalidOperation(): never
    {
        throw new SyntaxError('Constant expression contains invalid operations');
    }

    /** @return array{bool, mixed} */
    private function tryEvaluate(Expr $expression): array
    {
        try {
            $value = (new ConstExprEvaluator(
                static function (): never {
                    throw new \LogicException('Expression is not statically known');
                },
            ))->evaluateDirectly($expression);
            return [true, $value];
        } catch (\Throwable) {
            return [false, null];
        }
    }
}

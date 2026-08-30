<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Entity;

use TypePhp\Entity\ArrayInitPlan;
use PhpParser\Node\Expr;
use PhpParser\NodeAbstract;

class ArgInfo
{
    public string $name;
    public string $phpName = '';
    public string $type;
    public string $default = '';
    public ?ArrayInitPlan $arrayInitPlan = null;
    /** Original declaration AST; lowered to $default only in the convert phase. */
    public ?Expr $defaultExpr = null;
    public ?Expr $defaultValue = null;
    public string $class = '';

    /**
     * Late-bound type keyword: 'self', 'static' or 'parent'.
     * Empty for ordinary class-name parameter types. When set, the effective
     * class depends on the consuming context (e.g. a trait method's `self`
     * parameter resolves to the class that uses the trait).
     */
    public string $typeKeyword = '';

    /**
     * Object type declared in the PHP signature, including interfaces.
     * Unlike $class, this is only an assignment/type-check constraint and must
     * not be used for typed-object native-call dispatch.
     */
    public string $declaredClass = '';
    public bool $byRef = false;
    public bool $variadic = false;
    public bool $nullable = false;
    public bool $undeclared = false;
    public bool $explicitMixed = false;
    public bool $property = false;
    /** This parameter binding and any referenced object are read-only in the callee. */
    public bool $immutable = false;

    /**
     * Each element: ['kind' => 'isInt'|'isFloat'|...|'instanceof', 'class' => '']
     * Null means no runtime type check needed.
     */
    public ?array $typeCheck = null;

    /** Human-readable type string for error messages, e.g. "int|string", "?int" */
    public string $typeStr = '';

    /** Original union/nullable AST node. Only set when typeCheck is non-null. */
    public ?NodeAbstract $typeNode = null;

    public function hasDefaultValue(): bool
    {
        return $this->defaultExpr !== null;
    }
}

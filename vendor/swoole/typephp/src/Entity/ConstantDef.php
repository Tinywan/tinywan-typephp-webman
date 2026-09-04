<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Entity;

use PhpParser\NodeAbstract;

class ConstantDef
{
    public string $name;
    public string $type;
    public int $flags;
    public string $value;
    public string $arrayExpr = '';
    public string $class = '';
    /** Trait whose lexical namespace/import context owns this declaration. */
    public string $traitOrigin = '';
    public ?NodeAbstract $valueExpr = null;
    /** True after the declaration AST has been lowered to C++ in convert. */
    public bool $codegenFinalized = false;
    /** Explicit declared type (e.g. `const int FOO`); null for inferred/untyped constants. */
    public ?string $declaredType = null;

    /**
     * Accepted-types DNF for the explicitly declared type, in the same format
     * as ArgInfo::$typeCheck. Empty when the constant is untyped or the
     * declared type accepts everything (`mixed`).
     */
    public array $typeCheck = [];

    /** Human-readable declared type string for diagnostics ('' when untyped). */
    public string $typeStr = '';

    public function __construct(string $name, int $flags, string $type, string $value)
    {
        $this->name  = $name;
        $this->type  = $type;
        $this->flags = $flags;
        $this->value = $value;
    }
}

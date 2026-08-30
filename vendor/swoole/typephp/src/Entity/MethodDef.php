<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Entity;

class MethodDef
{
    public int $flags;
    public string $name;
    public ?FunctionDef $functionDef = null;
    /**
     * The original `ClassMethod` AST node this definition was parsed from.
     * Stored so that later validation (e.g. trait method override compatibility
     * checks performed at the `use` site) can report accurate line information.
     */
    public ?\PhpParser\Node\Stmt\ClassMethod $node = null;

    /** Source trait, retained only for diagnostics and the __TRAIT__ constant. */
    public string $traitOrigin = '';

    public function __construct(int $flags, string $name)
    {
        $this->flags = $flags;
        $this->name = $name;
    }

    public function getReturnType(): string
    {
        return $this->functionDef->returnType;
    }
}

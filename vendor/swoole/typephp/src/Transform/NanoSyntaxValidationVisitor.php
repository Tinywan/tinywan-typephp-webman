<?php

namespace TypePhp\Transform;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/** Reject syntax whose semantics require the runtime PHP compiler or Zend VM. */
final class NanoSyntaxValidationVisitor extends NodeVisitorAbstract
{
    private const array UNSUPPORTED_RUNTIME_CLASSES = [
        'Fiber' => true,
        'Generator' => true,
        'ReflectionFiber' => true,
        'ReflectionGenerator' => true,
    ];

    /** @param callable(Node, string): never $fatal */
    public function __construct(
        private readonly mixed $fatal,
        private readonly bool $phpNanoRuntime = true,
    ) {
    }

    public function enterNode(Node $node): ?Node
    {
        if ($this->phpNanoRuntime && $node instanceof Node\Name) {
            $resolved = $node->getAttribute('resolvedName');
            $className = $resolved instanceof Node\Name
                ? $resolved->toString()
                : ($node instanceof Node\Name\FullyQualified ? $node->toString() : null);
            if ($className !== null && isset(self::UNSUPPORTED_RUNTIME_CLASSES[$className])) {
                ($this->fatal)(
                    $node,
                    "Class `{$className}` is not supported in nano mode because it requires Generator/Fiber runtime support",
                );
            }
        }

        if ($node instanceof Node\Expr\Eval_) {
            ($this->fatal)($node, '`eval` is not supported in nano mode');
        }

        if ($node instanceof Node\Expr\Include_) {
            $keyword = match ($node->type) {
                Node\Expr\Include_::TYPE_INCLUDE => 'include',
                Node\Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
                Node\Expr\Include_::TYPE_REQUIRE => 'require',
                Node\Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
            };
            ($this->fatal)($node, "`{$keyword}` is not supported in nano mode");
        }

        if ($node instanceof Node\Expr\ShellExec) {
            ($this->fatal)($node, 'Backtick shell execution is not supported in nano mode');
        }

        if ($node instanceof Node\Stmt\Class_ && $node->name === null) {
            ($this->fatal)($node, 'Anonymous classes are not supported in nano mode');
        }

        if ($this->phpNanoRuntime
            && ($node instanceof Node\Expr\Yield_ || $node instanceof Node\Expr\YieldFrom)) {
            ($this->fatal)(
                $node,
                'Fiber and Generator are not supported in nano mode because C++17 has no standard stack-switching API',
            );
        }

        return null;
    }
}

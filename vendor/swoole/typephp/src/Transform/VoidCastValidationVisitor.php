<?php
/**
 * This file is part of Swoole-Compiler(AOT).
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use TypePhp\Exception\SyntaxError;

/**
 * Enforces PHP 8.5's statement-only grammar for the (void) cast.
 *
 * php-parser represents (void) as an Expr in every context for parser-version
 * compatibility. PHP itself only accepts it as a statement, or in a discarded
 * for-expression-list position. Marking accepted nodes also makes codegen
 * reject any synthetic/unvalidated Void_ node defensively.
 */
final class VoidCastValidationVisitor extends NodeVisitorAbstract
{
    public const string ALLOWED_ATTRIBUTE = 'typephpVoidCastAllowed';
    private const string ERROR_MESSAGE = 'The (void) cast can only be used as a statement';

    /** @var list<Node> */
    private array $stack = [];

    /** @param \Closure(Node, string): never|null $fatalError */
    public function __construct(private readonly ?\Closure $fatalError = null)
    {
    }

    public function beforeTraverse(array $nodes): null
    {
        $this->stack = [];
        return null;
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Expr\Cast\Void_) {
            $parent = $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];
            if (!$this->isDiscardedPosition($node, $parent)) {
                $this->fail($node);
            }
            $node->setAttribute(self::ALLOWED_ATTRIBUTE, true);
        }

        $this->stack[] = $node;
        return null;
    }

    public function leaveNode(Node $node): null
    {
        array_pop($this->stack);
        return null;
    }

    private function isDiscardedPosition(Expr\Cast\Void_ $node, ?Node $parent): bool
    {
        if ($parent instanceof Stmt\Expression) {
            return $parent->expr === $node;
        }
        if (!$parent instanceof Stmt\For_) {
            return false;
        }
        if ($this->containsIdenticalNode($parent->init, $node)
            || $this->containsIdenticalNode($parent->loop, $node)) {
            return true;
        }

        $lastCondition = array_key_last($parent->cond);
        foreach ($parent->cond as $index => $condition) {
            if ($condition === $node) {
                // Only the final condition controls the loop. Earlier items
                // are evaluated solely for side effects and may be void casts.
                return $index !== $lastCondition;
            }
        }
        return false;
    }

    /** @param list<Expr> $nodes */
    private function containsIdenticalNode(array $nodes, Node $needle): bool
    {
        foreach ($nodes as $node) {
            if ($node === $needle) {
                return true;
            }
        }
        return false;
    }

    private function fail(Node $node): never
    {
        if ($this->fatalError !== null) {
            ($this->fatalError)($node, self::ERROR_MESSAGE);
        }
        throw new SyntaxError(self::ERROR_MESSAGE);
    }
}

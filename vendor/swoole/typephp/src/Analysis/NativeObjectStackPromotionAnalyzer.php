<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Analysis;

use Closure;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;

/**
 * Finds Native Object allocations whose address cannot leave the current
 * function. This is deliberately an allocation-site analysis: proving that a
 * PHP variable is stable is insufficient when an alias of the same pointer can
 * escape.
 *
 * The first implementation accepts one direct `$local = new NativeClass()`
 * definition outside loops. Every use must remain a property receiver or a
 * receiver-preserving Native method call. Anything not explicitly understood
 * invalidates the candidate and leaves it on the Wren GC heap.
 */
final class NativeObjectStackPromotionAnalyzer
{
    /** @var Closure(Expr\New_): ?string */
    private Closure $resolveClass;

    /** @var Closure(string): bool */
    private Closure $classIsEligible;

    /** @var Closure(string, string): bool */
    private Closure $methodPreservesReceiver;

    /**
     * @var array<string, array{
     *     assignment: Expr\Assign,
     *     allocation: Expr\New_,
     *     class: string
     * }>
     */
    private array $candidates = [];

    /** @var array<string, true> */
    private array $invalid = [];

    private bool $hasNonStructuredControlFlow = false;

    public function __construct(
        callable $resolveClass,
        callable $classIsEligible,
        callable $methodPreservesReceiver,
    ) {
        $this->resolveClass = Closure::fromCallable($resolveClass);
        $this->classIsEligible = Closure::fromCallable($classIsEligible);
        $this->methodPreservesReceiver = Closure::fromCallable($methodPreservesReceiver);
    }

    /**
     * @param list<Stmt> $statements
     * @return array<string, array{assignment: Expr\Assign, allocation: Expr\New_, class: string}>
     */
    public function analyze(array $statements): array
    {
        $this->candidates = [];
        $this->invalid = [];
        $this->hasNonStructuredControlFlow = false;

        $this->collectCandidates($statements);
        if ($this->candidates === [] || $this->hasNonStructuredControlFlow) {
            return [];
        }

        $this->scanUses($statements);
        foreach ($this->invalid as $name => $_) {
            unset($this->candidates[$name]);
        }
        return $this->candidates;
    }

    private function collectCandidates(mixed $value, int $loopDepth = 0): void
    {
        foreach (is_array($value) ? $value : [$value] as $node) {
            if (!$node instanceof Node) {
                continue;
            }

            if ($node instanceof Stmt\Goto_ || $node instanceof Stmt\Label) {
                // A backward goto can execute one source allocation more than
                // once even though it is not lexically contained in a loop.
                $this->hasNonStructuredControlFlow = true;
            }

            if ($loopDepth === 0
                && $node instanceof Stmt\Expression
                && $node->expr instanceof Expr\Assign
                && $node->expr->var instanceof Expr\Variable
                && is_string($node->expr->var->name)
                && $node->expr->expr instanceof Expr\New_
            ) {
                $class = ($this->resolveClass)($node->expr->expr);
                if ($class !== null && ($this->classIsEligible)($class)) {
                    $name = $node->expr->var->name;
                    if (isset($this->candidates[$name])) {
                        $this->invalid[$name] = true;
                    } else {
                        $this->candidates[$name] = [
                            'assignment' => $node->expr,
                            'allocation' => $node->expr->expr,
                            'class' => $class,
                        ];
                    }
                }
            }

            if ($node instanceof FunctionLike) {
                // Candidate allocations belong to the current function only.
                // Uses inside nested closures are still inspected by scanUses()
                // and conservatively treated as escapes.
                continue;
            }

            $childLoopDepth = $loopDepth + ($this->isRepeatedRegion($node) ? 1 : 0);
            foreach ($node->getSubNodeNames() as $field) {
                $this->collectCandidates($node->{$field}, $childLoopDepth);
            }
        }
    }

    /** @param list<Node> $ancestors */
    private function scanUses(
        mixed $value,
        ?Node $parent = null,
        string $parentField = '',
        int $functionDepth = 0,
        array $ancestors = [],
    ): void {
        foreach (is_array($value) ? $value : [$value] as $node) {
            if (!$node instanceof Node) {
                continue;
            }

            if ($node instanceof Expr\Variable
                && is_string($node->name)
                && isset($this->candidates[$node->name])
            ) {
                $this->classifyUse($node->name, $parent, $parentField, $functionDepth, $ancestors);
            }

            $childFunctionDepth = $functionDepth + ($node instanceof FunctionLike ? 1 : 0);
            $childAncestors = [...$ancestors, $node];
            foreach ($node->getSubNodeNames() as $field) {
                $this->scanUses($node->{$field}, $node, $field, $childFunctionDepth, $childAncestors);
            }
        }
    }

    /** @param list<Node> $ancestors */
    private function classifyUse(
        string $name,
        ?Node $parent,
        string $parentField,
        int $functionDepth,
        array $ancestors,
    ): void {
        if (isset($this->invalid[$name])) {
            return;
        }

        $candidate = $this->candidates[$name];
        if ($functionDepth === 0
            && $parent instanceof Expr\Assign
            && $parentField === 'var'
            && $parent === $candidate['assignment']
        ) {
            return;
        }

        if ($functionDepth !== 0) {
            $this->invalid[$name] = true;
            return;
        }

        if (($parent instanceof Expr\PropertyFetch || $parent instanceof Expr\NullsafePropertyFetch)
            && $parentField === 'var'
        ) {
            if ($this->propertyAddressMayEscape($ancestors)) {
                $this->invalid[$name] = true;
            }
            return;
        }

        if (($parent instanceof Expr\MethodCall || $parent instanceof Expr\NullsafeMethodCall)
            && $parentField === 'var'
            && $parent->name instanceof Node\Identifier
            && ($this->methodPreservesReceiver)($candidate['class'], $parent->name->toString())
        ) {
            return;
        }

        // Aliases, returns, arguments, array/container storage, comparisons,
        // references, unset and every dynamic operation are escapes unless a
        // future analysis explicitly proves otherwise.
        $this->invalid[$name] = true;
    }

    /** @param list<Node> $ancestors */
    private function propertyAddressMayEscape(array $ancestors): bool
    {
        foreach ($ancestors as $ancestor) {
            if ($ancestor instanceof Expr\AssignRef
                || $ancestor instanceof Node\Arg
                || $ancestor instanceof Stmt\Return_
                || $ancestor instanceof Expr\Yield_
                || $ancestor instanceof Expr\YieldFrom
            ) {
                return true;
            }
        }
        return false;
    }

    private function isRepeatedRegion(Node $node): bool
    {
        return $node instanceof Stmt\For_
            || $node instanceof Stmt\Foreach_
            || $node instanceof Stmt\While_
            || $node instanceof Stmt\Do_;
    }
}

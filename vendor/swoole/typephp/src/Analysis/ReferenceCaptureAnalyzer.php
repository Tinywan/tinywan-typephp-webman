<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;

/**
 * Finds locals which need dynamic storage because a child Closure captures
 * them by reference. Nested function bodies are separate variable scopes and
 * are deliberately analyzed when their own FunctionContext is created.
 */
final class ReferenceCaptureAnalyzer
{
    /**
     * @param Node|list<Node>|null $body
     * @return array{captures: array<string, true>, nonLocals: array<string, true>}
     */
    public function analyze(Node|array|null $body): array
    {
        $captures = [];
        $nonLocals = [];
        $this->scan($body, $captures, $nonLocals);
        return ['captures' => $captures, 'nonLocals' => $nonLocals];
    }

    /**
     * @param array<string, true> $captures
     * @param array<string, true> $nonLocals
     */
    private function scan(mixed $value, array &$captures, array &$nonLocals): void
    {
        foreach (is_array($value) ? $value : [$value] as $node) {
            if (!$node instanceof Node) {
                continue;
            }

            if ($node instanceof Expr\Closure) {
                foreach ($node->uses as $use) {
                    if ($use->byRef
                        && $use->var instanceof Expr\Variable
                        && is_string($use->var->name)
                    ) {
                        $captures[$use->var->name] = true;
                    }
                }
                // The Closure body owns another FunctionContext.
                continue;
            }
            if ($node instanceof Expr\ArrowFunction
                || $node instanceof Stmt\Function_
                || $node instanceof Stmt\ClassLike
                || $node instanceof FunctionLike
            ) {
                continue;
            }

            if ($node instanceof Stmt\Static_) {
                foreach ($node->vars as $var) {
                    if ($var->var instanceof Expr\Variable && is_string($var->var->name)) {
                        $nonLocals[$var->var->name] = true;
                    }
                }
            } elseif ($node instanceof Stmt\Global_) {
                foreach ($node->vars as $var) {
                    if ($var instanceof Expr\Variable && is_string($var->name)) {
                        $nonLocals[$var->name] = true;
                    }
                }
            }

            foreach ($node->getSubNodeNames() as $field) {
                $this->scan($node->{$field}, $captures, $nonLocals);
            }
        }
    }
}

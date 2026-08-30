<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Diagnostics;

use PhpParser\Node;
use PhpParser\Node\Stmt;

final class CompileTimeAttributeDiagnostic
{
    public const GENERATED_BY = 'typephpGeneratedByCompileTimeAttribute';
    public const GENERATED_TARGET = 'typephpCompileTimeAttributeTarget';

    public static function format(
        string $message,
        string $attribute,
        Node $target,
        string $file,
        ?Node $source = null,
        ?string $conflictAttribute = null,
        ?Node $conflictSource = null,
    ): string {
        $source ??= $target;
        $context = '[compile-time attribute: #[' . $attribute . ']; target: ' .
            self::describeTarget($target) . '; source: ' . $file . ':' . $source->getStartLine();
        if ($conflictSource !== null) {
            $context .= $conflictAttribute === null
                ? '; conflict source: declaration at ' . $file . ':' . $conflictSource->getStartLine()
                : '; conflict source: #[' . $conflictAttribute . '] at ' .
                    $file . ':' . $conflictSource->getStartLine();
        }
        return $message . ' ' . $context . ']';
    }

    public static function markGenerated(Node $generated, string $attribute, Node $target): void
    {
        $generated->setAttribute(self::GENERATED_BY, $attribute);
        $generated->setAttribute(self::GENERATED_TARGET, $target);
    }

    public static function formatPositions(
        string $message,
        string $attribute,
        string $target,
        string $sourceFile,
        int $sourceLine,
        ?string $conflictLabel = null,
        ?string $conflictFile = null,
        ?int $conflictLine = null,
    ): string {
        $context = '[compile-time attribute: #[' . $attribute . ']; target: ' . $target .
            '; source: ' . $sourceFile . ':' . $sourceLine;
        if ($conflictFile !== null && $conflictLine !== null) {
            $context .= '; conflict source: ' . ($conflictLabel ?? 'declaration') . ' at ' .
                $conflictFile . ':' . $conflictLine;
        }
        return $message . ' ' . $context . ']';
    }

    public static function describeTarget(Node $node): string
    {
        if ($node instanceof Stmt\Function_) {
            return 'function ' . $node->name->toString() . '()';
        }
        if ($node instanceof Stmt\ClassMethod) {
            return 'method ' . $node->name->toString() . '()';
        }
        if ($node instanceof Stmt\Property) {
            return 'property ' . implode(', ', array_map(
                static fn (Node\PropertyItem $property): string => '$' . $property->name->toString(),
                $node->props,
            ));
        }
        if ($node instanceof Node\Param && is_string($node->var->name)) {
            return ($node->isPromoted() ? 'promoted property/parameter ' : 'parameter ') . '$' . $node->var->name;
        }
        if ($node instanceof Stmt\ClassLike) {
            return strtolower(str_replace('Stmt_', '', $node->getType())) . ' ' . ($node->name?->toString() ?? 'anonymous');
        }
        if ($node instanceof Node\Expr\Closure) {
            return 'anonymous function';
        }
        if ($node instanceof Node\Expr\ArrowFunction) {
            return 'arrow function';
        }
        return $node->getType();
    }
}

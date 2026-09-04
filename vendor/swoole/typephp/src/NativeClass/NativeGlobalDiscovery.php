<?php
/**
 * This file is part of Swoole-Compiler(AOT).
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\NativeClass;

use PhpParser\Node;
use PhpParser\NodeAbstract;

/**
 * Discovers project-wide global slots which carry Native Object pointers.
 *
 * C++ files are emitted one at a time, while a PHP global can be written in a
 * later file. The slot ABI must therefore be known before the first file is
 * converted. This deliberately small, flow-local analysis only tracks Native
 * pointer identities; ordinary PHP value inference remains in the compiler's
 * normal convert phase.
 */
final class NativeGlobalDiscovery
{
    private NativeGlobalTypeResolver $resolver;

    /** @var array<string, string> */
    private array $functionReturns;
    private string $scopeClass = '';

    /**
     * @param array<string, string> $functionReturns
     */
    public function __construct(
        NativeGlobalTypeResolver $resolver,
        array $functionReturns,
    ) {
        $this->resolver = $resolver;
        $this->functionReturns = $functionReturns;
    }

    /**
     * @param list<Node\Stmt> $statements
     * @return list<array{name: string, class: string, node: NodeAbstract}>
     */
    public function discover(array $statements): array
    {
        $result = [];
        $this->discoverStatements($statements, '', $result);
        return $result;
    }

    /**
     * @param list<Node\Stmt> $statements
     * @param list<array{name: string, class: string, node: NodeAbstract}> $result
     */
    private function discoverStatements(array $statements, string $class, array &$result): void
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Node\Stmt\Namespace_) {
                $this->discoverStatements($statement->stmts, '', $result);
                continue;
            }
            if ($statement instanceof Node\Stmt\Function_) {
                $this->discoverFunction($statement, '', $result);
                continue;
            }
            if ($statement instanceof Node\Stmt\ClassLike) {
                $className = isset($statement->namespacedName)
                    ? $statement->namespacedName->toString()
                    : ($statement->name?->toString() ?? '');
                foreach ($statement->getMethods() as $method) {
                    $this->discoverFunction($method, $className, $result);
                }
            }
        }
    }

    /**
     * @param list<array{name: string, class: string, node: NodeAbstract}> $result
     */
    private function discoverFunction(
        Node\Stmt\Function_|Node\Stmt\ClassMethod $function,
        string $class,
        array &$result,
    ): void {
        if ($function->stmts === null) {
            return;
        }

        $globals = [];
        $this->collectGlobals($function->stmts, $globals);

        $locals = [];
        $previousScope = $this->scopeClass;
        $thisClass = $this->resolver->canonicalClass($class);
        $this->scopeClass = $thisClass ?? '';
        if ($thisClass !== null) {
            $locals['this'] = $thisClass;
        }
        foreach ($function->params as $parameter) {
            if (!$parameter->var instanceof Node\Expr\Variable || !is_string($parameter->var->name)) {
                continue;
            }
            $parameterClass = $this->classFromType($parameter->type);
            if ($parameterClass !== null) {
                $locals[$parameter->var->name] = $parameterClass;
            }
        }

        $this->analyzeNodes($function->stmts, $globals, $locals, $result);
        $this->scopeClass = $previousScope;
    }

    /**
     * Analyze a Closure as an independent PHP variable scope while retaining
     * its lexical class scope. Native objects cannot be captured by a Zend
     * Closure, but an ordinary captured object may still construct a Native
     * object and a Closure may write a newly constructed object to a global.
     *
     * @param array<string, string> $outerLocals
     * @param list<array{name: string, class: string, node: NodeAbstract}> $result
     */
    private function discoverClosure(
        Node\Expr\Closure $closure,
        array $outerLocals,
        array &$result,
    ): void {
        $globals = [];
        $this->collectGlobals($closure->stmts, $globals);
        $locals = [];
        if (!$closure->static && isset($outerLocals['this'])) {
            $locals['this'] = $outerLocals['this'];
        }
        foreach ($closure->uses as $use) {
            if ($use->var instanceof Node\Expr\Variable
                && is_string($use->var->name)
                && isset($outerLocals[$use->var->name])
            ) {
                $locals[$use->var->name] = $outerLocals[$use->var->name];
            }
        }
        foreach ($closure->params as $parameter) {
            if (!$parameter->var instanceof Node\Expr\Variable || !is_string($parameter->var->name)) {
                continue;
            }
            $parameterClass = $this->classFromType($parameter->type);
            if ($parameterClass !== null) {
                $locals[$parameter->var->name] = $parameterClass;
            }
        }

        $this->analyzeNodes($closure->stmts, $globals, $locals, $result);
    }

    /** @param array<string, true> $globals */
    private function collectGlobals(mixed $node, array &$globals): void
    {
        if ($node === null) {
            return;
        }
        if (is_array($node)) {
            foreach ($node as $item) {
                $this->collectGlobals($item, $globals);
            }
            return;
        }
        if (!$node instanceof NodeAbstract || $node instanceof Node\FunctionLike) {
            return;
        }
        if ($node instanceof Node\Stmt\Global_) {
            foreach ($node->vars as $variable) {
                if ($variable instanceof Node\Expr\Variable && is_string($variable->name)) {
                    $globals[$variable->name] = true;
                }
            }
            return;
        }
        foreach ($node->getSubNodeNames() as $name) {
            $this->collectGlobals($node->{$name}, $globals);
        }
    }

    /**
     * @param array<string, true> $globals
     * @param array<string, string> $locals
     * @param list<array{name: string, class: string, node: NodeAbstract}> $result
     */
    private function analyzeNodes(
        mixed $node,
        array $globals,
        array &$locals,
        array &$result,
    ): void {
        if ($node === null) {
            return;
        }
        if (is_array($node)) {
            foreach ($node as $item) {
                $this->analyzeNodes($item, $globals, $locals, $result);
            }
            return;
        }
        if (!$node instanceof NodeAbstract) {
            return;
        }
        if ($node instanceof Node\Expr\Closure) {
            $this->discoverClosure($node, $locals, $result);
            return;
        }
        if ($node instanceof Node\Stmt\Function_) {
            $this->discoverFunction($node, '', $result);
            return;
        }
        if ($node instanceof Node\Stmt\ClassLike) {
            $className = isset($node->namespacedName)
                ? $node->namespacedName->toString()
                : ($node->name?->toString() ?? '');
            foreach ($node->getMethods() as $method) {
                $this->discoverFunction($method, $className, $result);
            }
            return;
        }
        if ($node instanceof Node\FunctionLike) {
            return;
        }
        if ($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignOp\Coalesce) {
            $this->analyzeNodes($node->expr, $globals, $locals, $result);
            $class = $this->inferClass($node->expr, $locals);
            $globalName = $this->assignmentGlobalName($node->var, $globals);
            $nativeClass = $class === null ? null : $this->resolver->nativeClass($class);
            if ($globalName !== null && $nativeClass !== null) {
                $result[] = ['name' => $globalName, 'class' => $nativeClass, 'node' => $node];
            } elseif ($node->var instanceof Node\Expr\Variable && is_string($node->var->name)) {
                if ($class !== null) {
                    $locals[$node->var->name] = $class;
                } elseif (!$this->isNull($node->expr)) {
                    unset($locals[$node->var->name]);
                }
            }
            $this->analyzeNodes($node->var, $globals, $locals, $result);
            return;
        }
        foreach ($node->getSubNodeNames() as $name) {
            $this->analyzeNodes($node->{$name}, $globals, $locals, $result);
        }
    }

    /** @param array<string, string> $locals */
    private function inferClass(NodeAbstract $expression, array $locals): ?string
    {
        if ($expression instanceof Node\Expr\Assign || $expression instanceof Node\Expr\AssignOp\Coalesce) {
            return $this->inferClass($expression->expr, $locals);
        }
        if ($expression instanceof Node\Expr\New_ && $expression->class instanceof Node\Name) {
            return $this->resolvedClass($expression->class);
        }
        if ($expression instanceof Node\Expr\Variable && is_string($expression->name)) {
            return $locals[$expression->name] ?? null;
        }
        if ($expression instanceof Node\Expr\Clone_
            || $expression instanceof Node\Expr\ErrorSuppress
        ) {
            return $this->inferClass($expression->expr, $locals);
        }
        if ($expression instanceof Node\Expr\FuncCall && $expression->name instanceof Node\Name) {
            foreach ($this->resolvedNameCandidates($expression->name) as $name) {
                $key = strtolower(ltrim($name, '\\'));
                if (isset($this->functionReturns[$key])) {
                    return $this->functionReturns[$key];
                }
            }
            return null;
        }
        if ($expression instanceof Node\Expr\StaticCall
            && $expression->class instanceof Node\Name
            && $expression->name instanceof Node\Identifier
        ) {
            $class = $this->resolvedClass($expression->class);
            return $class === null
                ? null
                : $this->resolver->methodReturn($class, $expression->name->toString());
        }
        if ($expression instanceof Node\Expr\MethodCall
            && $expression->name instanceof Node\Identifier
        ) {
            $class = $this->inferClass($expression->var, $locals);
            return $class === null
                ? null
                : $this->resolver->methodReturn($class, $expression->name->toString());
        }
        if ($expression instanceof Node\Expr\PropertyFetch
            && $expression->name instanceof Node\Identifier
        ) {
            $class = $this->inferClass($expression->var, $locals);
            return $class === null
                ? null
                : $this->resolver->propertyClass($class, $expression->name->toString());
        }
        if ($expression instanceof Node\Expr\Ternary) {
            $if = $expression->if === null
                ? $this->inferClass($expression->cond, $locals)
                : $this->inferClass($expression->if, $locals);
            return $this->mergeClasses($if, $this->inferClass($expression->else, $locals));
        }
        if ($expression instanceof Node\Expr\BinaryOp\Coalesce) {
            return $this->mergeClasses(
                $this->inferClass($expression->left, $locals),
                $this->inferClass($expression->right, $locals),
            );
        }
        if ($expression instanceof Node\Expr\Match_) {
            $class = null;
            foreach ($expression->arms as $arm) {
                $class = $this->mergeClasses($class, $this->inferClass($arm->body, $locals));
            }
            return $class;
        }
        return null;
    }

    private function mergeClasses(?string $left, ?string $right): ?string
    {
        if ($left === null) {
            return $right;
        }
        if ($right === null) {
            return $left;
        }
        return $this->resolver->commonClass($left, $right);
    }

    private function classFromType(?NodeAbstract $type): ?string
    {
        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }
        return $type instanceof Node\Name
            ? $this->resolvedClass($type)
            : null;
    }

    /** @param array<string, true> $globals */
    private function assignmentGlobalName(NodeAbstract $target, array $globals): ?string
    {
        if ($target instanceof Node\Expr\Variable
            && is_string($target->name)
            && isset($globals[$target->name])
        ) {
            return $target->name;
        }
        return $this->globalArrayName($target);
    }

    private function globalArrayName(NodeAbstract $node): ?string
    {
        if (!$node instanceof Node\Expr\ArrayDimFetch
            || !$node->var instanceof Node\Expr\Variable
            || $node->var->name !== 'GLOBALS'
            || $node->dim === null
        ) {
            return null;
        }
        return $this->resolver->staticString($node->dim, $this->scopeClass);
    }

    private function isNull(NodeAbstract $expression): bool
    {
        return $expression instanceof Node\Expr\ConstFetch
            && strtolower($expression->name->toString()) === 'null';
    }

    private function resolvedName(Node\Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');
        return ltrim($resolved instanceof Node\Name ? $resolved->toString() : $name->toString(), '\\');
    }

    private function resolvedClass(Node\Name $name): ?string
    {
        $keyword = strtolower($name->toString());
        if ($keyword === 'self' || $keyword === 'static') {
            return $this->scopeClass !== '' ? $this->scopeClass : null;
        }
        if ($keyword === 'parent') {
            return $this->scopeClass !== ''
                ? $this->resolver->parentClass($this->scopeClass)
                : null;
        }
        return $this->resolver->canonicalClass($this->resolvedName($name));
    }

    /** @return list<string> */
    private function resolvedNameCandidates(Node\Name $name): array
    {
        $names = [$this->resolvedName($name)];
        $fallback = $name->getAttribute('fallbackName');
        if ($fallback instanceof Node\Name) {
            $names[] = ltrim($fallback->toString(), '\\');
        }
        $names[] = ltrim($name->toString(), '\\');
        return array_values(array_unique($names));
    }
}

<?php
/**
 * This file is part of Swoole-Compiler(AOT).
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\NativeClass;

use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\NodeAbstract;
use TypePhp\Entity\ClassDef;

/**
 * Immutable class metadata used by the Native global pre-pass.
 *
 * The analyzer needs no reference back to the compiler or its mutable
 * per-function context. This keeps the project-level pass deterministic and
 * prevents a short-lived analysis object from retaining the Translator.
 */
final class NativeGlobalTypeResolver
{
    /** @var array<string, string> */
    private array $classes = [];

    /** @var array<string, ClassDef> */
    private array $classDefinitions = [];

    /** @var array<string, Node\Expr> */
    private array $globalConstantExpressions = [];

    /** @var array<string, true> */
    private array $nativeClasses = [];

    /** @var array<string, string> */
    private array $parents = [];

    /** @var array<string, array<string, ?string>> */
    private array $methodReturns = [];

    /** @var array<string, array<string, ?string>> */
    private array $propertyClasses = [];

    /**
     * @param array<string, ClassDef> $classes
     * @param array<string, object> $constants
     */
    public function __construct(array $classes, array $constants = [])
    {
        foreach ($classes as $class) {
            $name = $class->getNamespacedName(false);
            $key = strtolower(ltrim($name, '\\'));
            $this->classes[$key] = $name;
            $this->classDefinitions[$key] = $class;
            if ($class->nativeObject) {
                $this->nativeClasses[$key] = true;
            }
        }

        foreach ($constants as $constant) {
            if (isset($constant->name) && is_string($constant->name)
                && isset($constant->valueExpr) && $constant->valueExpr instanceof Node\Expr
            ) {
                $this->globalConstantExpressions[ltrim($constant->name, '\\')] = $constant->valueExpr;
            }
        }

        foreach ($classes as $class) {
            $name = $class->getNamespacedName(false);
            $key = strtolower(ltrim($name, '\\'));
            $parent = $this->canonicalClass($class->extends);
            if ($parent !== null) {
                $this->parents[$key] = $parent;
            }

            foreach ($class->methods as $method => $definition) {
                $this->methodReturns[$key][$method]
                    = $this->canonicalClass($definition->functionDef->returnClass);
            }
            foreach ($class->abstractMethodDefs as $method => $definition) {
                $this->methodReturns[$key][$method]
                    = $this->canonicalClass($definition->functionDef->returnClass);
            }
            foreach ($class->properties as $property => $definition) {
                $this->propertyClasses[$key][$property]
                    = $this->canonicalClass($definition->class);
            }
        }
    }

    public function canonicalClass(string $class): ?string
    {
        return $this->classes[strtolower(ltrim($class, '\\'))] ?? null;
    }

    public function nativeClass(string $class): ?string
    {
        $key = strtolower(ltrim($class, '\\'));
        return isset($this->nativeClasses[$key]) ? $this->classes[$key] : null;
    }

    public function methodReturn(string $class, string $method): ?string
    {
        $key = strtolower(ltrim($class, '\\'));
        $method = strtolower($method);
        while (isset($this->classes[$key])) {
            if (array_key_exists($method, $this->methodReturns[$key] ?? [])) {
                return $this->methodReturns[$key][$method];
            }
            $parent = $this->parents[$key] ?? null;
            if ($parent === null) {
                return null;
            }
            $key = strtolower($parent);
        }
        return null;
    }

    public function propertyClass(string $class, string $property): ?string
    {
        $key = strtolower(ltrim($class, '\\'));
        while (isset($this->classes[$key])) {
            if (array_key_exists($property, $this->propertyClasses[$key] ?? [])) {
                return $this->propertyClasses[$key][$property];
            }
            $parent = $this->parents[$key] ?? null;
            if ($parent === null) {
                return null;
            }
            $key = strtolower($parent);
        }
        return null;
    }

    public function commonClass(string $left, string $right): ?string
    {
        $leftKey = strtolower(ltrim($left, '\\'));
        $rightKey = strtolower(ltrim($right, '\\'));
        $leftAncestors = [];
        while (isset($this->classes[$leftKey])) {
            $leftAncestors[$leftKey] = true;
            $parent = $this->parents[$leftKey] ?? null;
            if ($parent === null) {
                break;
            }
            $leftKey = strtolower($parent);
        }

        while (isset($this->classes[$rightKey])) {
            if (isset($leftAncestors[$rightKey])) {
                return $this->classes[$rightKey];
            }
            $parent = $this->parents[$rightKey] ?? null;
            if ($parent === null) {
                break;
            }
            $rightKey = strtolower($parent);
        }
        return null;
    }

    public function parentClass(string $class): ?string
    {
        return $this->parents[strtolower(ltrim($class, '\\'))] ?? null;
    }

    /**
     * Evaluate a PHP constant expression used as a `$GLOBALS[...]` key.
     * Returning null keeps the expression on the ordinary dynamic Zend path.
     */
    public function staticString(NodeAbstract $expression, string $scopeClass = ''): ?string
    {
        if ($expression instanceof Node\Scalar\String_) {
            return $expression->value;
        }

        $visiting = [];
        try {
            $value = $this->evaluateConstantExpression($expression, $scopeClass, $visiting, 0);
        } catch (\Throwable) {
            return null;
        }
        return is_string($value) ? $value : null;
    }

    /** @param array<string, true> $visiting */
    private function evaluateConstantExpression(
        NodeAbstract $expression,
        string $scopeClass,
        array &$visiting,
        int $depth,
    ): mixed {
        if ($depth > 32 || !$expression instanceof Node\Expr) {
            throw new \RuntimeException('Constant expression nesting is too deep');
        }

        $evaluator = new ConstExprEvaluator(function (Node\Expr $node) use (
            $scopeClass,
            &$visiting,
            $depth,
        ): mixed {
            if ($node instanceof Node\Expr\ConstFetch) {
                foreach ($this->resolvedNameCandidates($node->name) as $name) {
                    $lower = strtolower($name);
                    if ($lower === 'true') {
                        return true;
                    }
                    if ($lower === 'false') {
                        return false;
                    }
                    if ($lower === 'null') {
                        return null;
                    }
                    if (isset($this->globalConstantExpressions[$name])) {
                        $key = 'global:' . $name;
                        if (isset($visiting[$key])) {
                            throw new \RuntimeException('Circular constant expression');
                        }
                        $visiting[$key] = true;
                        try {
                            return $this->evaluateConstantExpression(
                                $this->globalConstantExpressions[$name],
                                $scopeClass,
                                $visiting,
                                $depth + 1,
                            );
                        } finally {
                            unset($visiting[$key]);
                        }
                    }
                    if (defined($name)) {
                        return constant($name);
                    }
                }
                throw new \RuntimeException('Unresolved global constant');
            }

            if ($node instanceof Node\Expr\ClassConstFetch
                && $node->class instanceof Node\Name
                && $node->name instanceof Node\Identifier
            ) {
                $class = $this->resolvedClassName($node->class, $scopeClass);
                $constant = $node->name->toString();
                if (strcasecmp($constant, 'class') === 0) {
                    return $class;
                }
                $classKey = strtolower(ltrim($class, '\\'));
                while (isset($this->classDefinitions[$classKey])) {
                    $definition = $this->classDefinitions[$classKey];
                    if ($definition->hasConstant($constant)) {
                        $constantDefinition = $definition->getConstant($constant);
                        if (!$constantDefinition->valueExpr instanceof Node\Expr) {
                            throw new \RuntimeException('Class constant has no static expression');
                        }
                        $key = 'class:' . $classKey . '::' . $constant;
                        if (isset($visiting[$key])) {
                            throw new \RuntimeException('Circular class constant expression');
                        }
                        $visiting[$key] = true;
                        try {
                            return $this->evaluateConstantExpression(
                                $constantDefinition->valueExpr,
                                $definition->getNamespacedName(false),
                                $visiting,
                                $depth + 1,
                            );
                        } finally {
                            unset($visiting[$key]);
                        }
                    }
                    $parent = $this->parents[$classKey] ?? null;
                    if ($parent === null) {
                        break;
                    }
                    $classKey = strtolower($parent);
                }
                $runtimeConstant = $class . '::' . $constant;
                if (defined($runtimeConstant)) {
                    return constant($runtimeConstant);
                }
                throw new \RuntimeException('Unresolved class constant');
            }

            throw new \RuntimeException('Unsupported constant expression');
        });

        return $evaluator->evaluateDirectly($expression);
    }

    private function resolvedClassName(Node\Name $name, string $scopeClass): string
    {
        $keyword = strtolower($name->toString());
        if ($keyword === 'self' || $keyword === 'static') {
            if ($scopeClass === '') {
                throw new \RuntimeException('Class-relative constant outside class scope');
            }
            return ltrim($scopeClass, '\\');
        }
        if ($keyword === 'parent') {
            $parent = $this->parentClass($scopeClass);
            if ($parent === null) {
                throw new \RuntimeException('Parent-relative constant without a parent');
            }
            return $parent;
        }
        $resolved = $name->getAttribute('resolvedName');
        return ltrim($resolved instanceof Node\Name ? $resolved->toString() : $name->toString(), '\\');
    }

    /** @return list<string> */
    private function resolvedNameCandidates(Node\Name $name): array
    {
        $names = [];
        $resolved = $name->getAttribute('resolvedName');
        if ($resolved instanceof Node\Name) {
            $names[] = ltrim($resolved->toString(), '\\');
        }
        $fallback = $name->getAttribute('fallbackName');
        if ($fallback instanceof Node\Name) {
            $names[] = ltrim($fallback->toString(), '\\');
        }
        $names[] = ltrim($name->toString(), '\\');
        return array_values(array_unique($names));
    }
}

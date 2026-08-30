<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Resolver;

use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\NodeAbstract;
use TypePhp\Entity\ConstantDef;

trait ClassConstantValueTrait
{
    public function getDefinedConstants(): array
    {
        return $this->internalConstants;
    }

    public function getClassConstValue(NodeAbstract $expr, string $_class, string $name, string $currentClass = ''): mixed
    {
        $namespace = $this->namespace;
        if (!$namespace and $currentClass and !str_contains($_class, '\\')) {
            $namespace = $this->getNamespaceOfClass($currentClass);
        }
        $class = $this->getNamespacedClassName($_class, $namespace);
        $nativeConst = $this->findNativeClassConst(
            $expr,
            $class,
            $name,
            $currentClass !== '' ? $currentClass : null,
        );
        if ($nativeConst and $expr->hasAttribute('nativeConst')) {
            $constDef = $expr->getAttribute('nativeConst');
            if ($constDef->valueExpr !== null) {
                return $this->evaluateClassConstValue($expr, $constDef, $class, $name);
            }
            if ($constDef->class !== '') {
                $refConst = $constDef->class . '::' . $name;
                if (defined($refConst)) {
                    return constant($refConst);
                }
            }
        }
        if ($this->isInternalClass($class)) {
            $constName = $class . '::' . $name;
            if (defined($constName)) {
                return constant($constName);
            }
        }
        [$inheritedFound, $inherited] = $this->resolveInheritedClassConst($class, $name);
        if ($inheritedFound) {
            return $inherited;
        }
        if ($this->hasClass($class)) {
            $classDef = $this->getClass($class);
            if ($classDef->enum && array_key_exists($name, $classDef->enumCases)) {
                $caseValue = $classDef->enumCases[$name];
                return $caseValue ?? $name;
            }
        }
        $this->fatalError($expr, "Class constant `{$class}::{$name}` not found");
    }

    /** @return array{bool, mixed} */
    protected function resolveInheritedClassConst(string $class, string $name): array
    {
        $current = ltrim($class, '\\');
        $visited = [];
        while ($current !== '' && $current !== '\\' && !isset($visited[strtolower($current)])) {
            $visited[strtolower($current)] = true;
            if ($this->hasClass($current)) {
                $classDef = $this->getClass($current);
                if ($classDef->hasConstant($name)) {
                    $constDef = $classDef->getConstant($name);
                    if ($constDef->valueExpr !== null) {
                        return [true, $this->evaluateClassConstValue(null, $constDef, $current, $name)];
                    }
                    if ($constDef->class !== '' && defined($constDef->class . '::' . $name)) {
                        return [true, constant($constDef->class . '::' . $name)];
                    }
                }
                $current = $classDef->extends;
            } elseif (($parent = $this->getParentClass($current)) !== '') {
                $current = $parent;
            } elseif (Reflection::isInternalClass($current)) {
                $constName = $current . '::' . $name;
                if (defined($constName)) {
                    return [true, constant($constName)];
                }
                break;
            } else {
                break;
            }
        }
        return [false, null];
    }

    protected function evaluateClassConstValue(?NodeAbstract $origin, ConstantDef $constDef, string $class, string $name): mixed
    {
        $valueExpr = $constDef->valueExpr;
        if (!$valueExpr instanceof Node\Expr) {
            $this->fatalError($origin, "Class constant `{$class}::{$name}` has no constant expression");
        }

        $evaluator = new ConstExprEvaluator(function (Node\Expr $expr) use ($origin, $class) {
            if ($expr instanceof Node\Expr\ConstFetch) {
                $constName = $expr->name->toString();
                return match (strtolower($constName)) {
                    'true' => true,
                    'false' => false,
                    'null' => null,
                    default => defined($constName)
                        ? constant($constName)
                        : throw new \RuntimeException("Constant `{$constName}` not found"),
                };
            }
            if ($expr instanceof Node\Expr\ClassConstFetch && $expr->class instanceof Node\Name) {
                $constName = $expr->name->toString();
                $className = $expr->class->toString();
                if (strcasecmp($constName, 'class') === 0) {
                    // `::class` is a compile-time magic constant that resolves to the
                    // fully qualified class name of the referenced class.
                    if (strcasecmp($className, 'self') === 0 || strcasecmp($className, 'static') === 0) {
                        $className = $class;
                    } elseif (strcasecmp($className, 'parent') === 0) {
                        $className = $this->getParentClass($class);
                    }
                    return ltrim($this->getNamespacedClassName($className, $this->getNamespaceOfClass($class)), '\\');
                }
                if (strcasecmp($className, 'self') === 0) {
                    $className = $class;
                } elseif (strcasecmp($className, 'parent') === 0) {
                    $className = $this->getParentClass($class);
                }
                return $this->getClassConstValue($origin ?? $expr, $className, $constName, $class);
            }
            throw new \RuntimeException('Unsupported class constant expression');
        });

        return $evaluator->evaluateDirectly($valueExpr);
    }

    public function getConstValue(string $name): mixed
    {
        if ($this->isInternalConstant($name)) {
            $value = $this->internalConstants[$name];
            if (is_int($value)) {
                $expr = $this->genIntegerLiteral($value);
            } elseif (is_float($value)) {
                return $value;
            } elseif (is_bool($value)) {
                return $value ? 1 : 0;
            } elseif (is_string($value)) {
                return $this->genCharPtr($value);
            } else {
                $this->error('Unsupported constant type: ' . gettype($value));
            }
            return $expr;
        }
        throw new \Exception('Constant ' . $name . ' not found');
    }
}

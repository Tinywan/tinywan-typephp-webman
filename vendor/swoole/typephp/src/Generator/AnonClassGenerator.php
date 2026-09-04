<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Generator;

use TypePhp\Type;

use PhpParser\Node;
use PhpParser\NodeAbstract;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use TypePhp\Resolver\Reflection;

trait AnonClassGenerator
{
    public function genAnonClassName(): string
    {
        return self::ANON_CLASS . $this->anonClassIndex++;
    }

    /** Flatten trait templates before an anonymous class is evaluated by ZendVM. */
    protected function flattenEmbeddedClassTraits(Class_ $class): void
    {
        $declaredMethods = [];
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod) {
                $declaredMethods[strtolower($stmt->name->toString())] = true;
            }
        }

        $injected = [];
        foreach ($class->stmts as $stmt) {
            if (!$stmt instanceof TraitUse) {
                continue;
            }
            foreach ($stmt->traits as $traitName) {
                $fullName = $this->getNamespacedClassName($this->parseIdentifier($traitName));
                if (!$this->hasClass($fullName)) {
                    $this->fatalError($stmt, "Trait `{$fullName}` not found");
                }
                $traitDef = $this->getClass($fullName);
                $traitAst = clone $traitDef->trait;
                foreach ($traitAst->stmts as $traitStmt) {
                    if ($traitStmt instanceof ClassMethod) {
                        $name = strtolower($traitStmt->name->toString());
                        if (isset($declaredMethods[$name])) {
                            continue;
                        }
                        $declaredMethods[$name] = true;
                    }
                    if (!$traitStmt instanceof TraitUse) {
                        $injected[] = $traitStmt;
                    }
                }
            }
        }
        $class->stmts = array_values(array_filter(
            $class->stmts,
            static fn (Node $stmt): bool => !$stmt instanceof TraitUse,
        ));
        array_push($class->stmts, ...$injected);
    }

    /** Resolve imported names in an anonymous class before evaluating it in the root namespace. */
    protected function resolveAnonClassNames(Class_ $classDef): void
    {
        // Anonymous classes are emitted through eval() in the root namespace. Names
        // resolved from the declaring file's namespace and imports must therefore be
        // embedded as fully-qualified names, including names used inside method bodies.
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class extends NodeVisitorAbstract {
            public function enterNode(Node $node): ?Node
            {
                if (!$node instanceof Name || $node->isSpecialClassName()) {
                    return null;
                }
                $resolvedName = $node->getAttribute('resolvedName');
                if (!$resolvedName instanceof Name) {
                    return null;
                }
                return new Name\FullyQualified($resolvedName->toString(), $node->getAttributes());
            }
        });
        $traverser->traverse([$classDef]);

        // Lowering may synthesize type nodes after name resolution, so retain the
        // explicit signature pass for nodes which do not carry resolvedName metadata.
        foreach ($classDef->stmts as $stmt) {
            if ($stmt instanceof ClassMethod) {
                foreach ($stmt->params as $param) {
                    if ($param->type !== null) {
                        $param->type = $this->resolveTypeNode($param->type);
                    }
                }
                if ($stmt->returnType !== null) {
                    $stmt->returnType = $this->resolveTypeNode($stmt->returnType);
                }
            } elseif ($stmt instanceof Property) {
                if ($stmt->type !== null) {
                    $stmt->type = $this->resolveTypeNode($stmt->type);
                }
            } elseif ($stmt instanceof ClassConst) {
                if ($stmt->type !== null) {
                    $stmt->type = $this->resolveTypeNode($stmt->type);
                }
            }
        }
    }

    /**
     * Resolve a single type node, converting relative Name to FullyQualified.
     */
    protected function resolveTypeNode(Node\ComplexType|Identifier|Name $type): Node\ComplexType|Identifier|Name
    {
        if ($type instanceof Name) {
            if ($type->isFullyQualified()) {
                return $type;
            }
            $resolved = $this->getNamespacedClassName($type->toString());
            return new Name\FullyQualified($resolved);
        }
        if ($type instanceof NullableType) {
            $type->type = $this->resolveTypeNode($type->type);
            return $type;
        }
        if ($type instanceof UnionType) {
            foreach ($type->types as $i => $subType) {
                $type->types[$i] = $this->resolveTypeNode($subType);
            }
            return $type;
        }
        if ($type instanceof IntersectionType) {
            foreach ($type->types as $i => $subType) {
                $type->types[$i] = $this->resolveTypeNode($subType);
            }
            return $type;
        }
        return $type;
    }

    protected function genEmbeddedCode(NodeAbstract $stmt): string
    {
        if ($stmt instanceof Class_) {
            $stmt = clone $stmt;
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new class($this) extends NodeVisitorAbstract {
                /** @var list<Class_> */
                private array $classStack = [];

                public function __construct(private object $compiler)
                {
                }

                public function enterNode(Node $node)
                {
                    if ($node instanceof Class_) {
                        $this->classStack[] = $node;
                        return null;
                    }

                    if ($node instanceof ClassMethod && $node->returnType === null) {
                        $class = $this->classStack[count($this->classStack) - 1] ?? null;
                        if ($class !== null && $this->compiler->shouldAddMixedReturnToEmbeddedClassMethod($class, $node)) {
                            $node->returnType = new Identifier('mixed');
                            // An implicit fallthrough returns null in PHP. Once the
                            // compatibility return type is made explicit, PHP requires
                            // an explicit value on that path as well.
                            if ($node->stmts !== null) {
                                $node->stmts[] = new Node\Stmt\Return_(
                                    new Node\Expr\ConstFetch(new Name('null')),
                                );
                            }
                        }
                    }
                    return null;
                }

                public function leaveNode(Node $node)
                {
                    if ($node instanceof Class_) {
                        array_pop($this->classStack);
                    }
                    return null;
                }
            });
            $stmt = $traverser->traverse([$stmt])[0];
        }
        return $this->printer->prettyPrint([$stmt]);
    }

    public function shouldAddMixedReturnToEmbeddedClassMethod(Class_ $class, ClassMethod $method): bool
    {
        $methodName = strtolower($method->name->toString());
        if ($this->isEmbeddedMagicMethodReturnSensitive($methodName)) {
            return false;
        }

        if (!empty($class->implements)) {
            return true;
        }

        return $class->extends !== null
            && $this->ancestorMethodMayRequireMixedReturn($class->extends, $methodName);
    }

    protected function isEmbeddedMagicMethodReturnSensitive(string $methodName): bool
    {
        return in_array($methodName, [
            '__construct',
            '__destruct',
            '__clone',
            '__debuginfo',
            '__isset',
            '__serialize',
            '__set',
            '__set_state',
            '__sleep',
            '__tostring',
            '__unserialize',
            '__unset',
            '__wakeup',
        ], true);
    }

    protected function ancestorMethodMayRequireMixedReturn(Name $extends, string $methodName): bool
    {
        $className = ltrim($extends->toString(), '\\');

        while ($className !== '') {
            if ($this->hasClass($className)) {
                $classDef = $this->getClass($className);
                if ($classDef->hasMethod($methodName)) {
                    $functionDef = $classDef->getMethod($methodName)->functionDef;
                    return $functionDef !== null
                        && ($functionDef->returnTypeUndeclared || $functionDef->returnType === Type::VAR);
                }
                $className = $classDef->extends;
                continue;
            }

            if ($this->isInternalClass($className) || $this->isInternalInterface($className)) {
                if (!Reflection::hasMethod($className, $methodName)) {
                    return false;
                }
                $returnType = Reflection::getMethodReturnType($className, $methodName);
                return $returnType === null || strtolower($returnType) === 'mixed';
            }

            return true;
        }

        return false;
    }
}

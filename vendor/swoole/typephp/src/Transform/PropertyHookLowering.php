<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\NodeAbstract;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

final class PropertyHookLowering
{
    public const string BACKING_ACCESS_ATTRIBUTE = 'typephpPropertyHookBackingAccess';
    public const string METHOD_ATTRIBUTE = 'typephpPropertyHookMethod';
    public const string PROPERTY_ATTRIBUTE = 'typephpPropertyHooks';
    private const string GET_PREFIX = '__typephp_property_get_';
    private const string SET_PREFIX = '__typephp_property_set_';
    private const string PRIVATE_SET_PREFIX = '__typephp_property_private_set_';
    private const string PROTECTED_SET_PREFIX = '__typephp_property_protected_set_';

    public static function getterName(string $property): string
    {
        return self::GET_PREFIX . bin2hex($property);
    }

    public static function setterName(string $property): string
    {
        return self::SET_PREFIX . bin2hex($property);
    }

    public static function isGetterName(string $method): bool
    {
        return str_starts_with($method, self::GET_PREFIX);
    }

    public static function isSetterName(string $method): bool
    {
        return str_starts_with($method, self::SET_PREFIX);
    }

    public static function isAsymmetricSetterMarkerName(string $method): bool
    {
        return str_starts_with($method, self::PRIVATE_SET_PREFIX)
            || str_starts_with($method, self::PROTECTED_SET_PREFIX);
    }

    /** @return list<Stmt\ClassMethod> */
    public static function lowerProperty(Stmt\Property $property): array
    {
        if (count($property->props) !== 1) {
            return [];
        }

        $propertyName = $property->props[0]->name->toString();
        $methods = [];
        $hookMethods = [];
        $hasBackingStorage = false;
        if ($property->flags & Modifiers::PRIVATE_SET) {
            $methods[] = self::visibilityMarker(
                self::PRIVATE_SET_PREFIX . bin2hex($propertyName),
                $property->getAttributes()
            );
        } elseif ($property->flags & Modifiers::PROTECTED_SET) {
            $methods[] = self::visibilityMarker(
                self::PROTECTED_SET_PREFIX . bin2hex($propertyName),
                $property->getAttributes()
            );
        }
        foreach ($property->hooks as $hook) {
            $kind = strtolower($hook->name->toString());
            if ($kind !== 'get' && $kind !== 'set') {
                continue;
            }

            self::markBackingAccesses($hook->body, $propertyName);
            if ($kind === 'get') {
                $stmts = self::getterStatements($hook, $propertyName);
                $params = [];
                $returnType = $property->type;
                $methodName = self::getterName($propertyName);
            } else {
                $params = $hook->params;
                if ($params === []) {
                    $params = [new Param(new Expr\Variable('value'), type: $property->type)];
                } elseif ($params[0]->type === null) {
                    $params[0]->type = $property->type;
                }
                $stmts = self::setterStatements($hook, $propertyName, $params[0]);
                $returnType = new Node\Identifier('void');
                $methodName = self::setterName($propertyName);
            }

            $method = new Stmt\ClassMethod($methodName, [
                // Hidden methods participate in inheritance exactly like the
                // corresponding hooks. Marking every generated method final
                // rejects legal child hook overrides and also forces PHPX to
                // erase final unconditionally from the Zend hook metadata.
                'flags' => Modifiers::PUBLIC | ($hook->flags & Modifiers::FINAL),
                'byRef' => $kind === 'get' && $hook->byRef,
                'params' => $params,
                'returnType' => $returnType,
                'stmts' => $stmts,
                'attrGroups' => $hook->attrGroups,
            ], $hook->getAttributes());
            $method->setAttribute(self::METHOD_ATTRIBUTE, [
                'kind' => $kind,
                'property' => $propertyName,
            ]);
            $methods[] = $method;
            $hookMethods[$kind] = $methodName;
            $hasBackingStorage = $hasBackingStorage || self::containsBackingAccess($stmts);
        }

        if ($hookMethods !== []) {
            $property->setAttribute(self::PROPERTY_ATTRIBUTE, [
                'methods' => $hookMethods,
                'virtual' => !$hasBackingStorage,
            ]);
        }

        return $methods;
    }

    public static function markAbstractInterfaceProperty(Stmt\Property $property): void
    {
        if ($property->hooks === []) {
            return;
        }

        $hooks = [];
        foreach ($property->hooks as $hook) {
            $kind = strtolower($hook->name->toString());
            if ($kind === 'get' || $kind === 'set') {
                $hooks[$kind] = true;
            }
        }
        $property->setAttribute(self::PROPERTY_ATTRIBUTE, [
            'methods' => $hooks,
            'virtual' => true,
            'abstract' => true,
        ]);
    }

    public static function lowerPromotedProperty(Param $param): ?Stmt\ClassMethod
    {
        if (!$param->isPromoted() || !is_string($param->var->name)) {
            return null;
        }
        if ($param->flags & Modifiers::PRIVATE_SET) {
            $prefix = self::PRIVATE_SET_PREFIX;
        } elseif ($param->flags & Modifiers::PROTECTED_SET) {
            $prefix = self::PROTECTED_SET_PREFIX;
        } else {
            return null;
        }
        return self::visibilityMarker($prefix . bin2hex($param->var->name), $param->getAttributes());
    }

    private static function visibilityMarker(string $name, array $attributes): Stmt\ClassMethod
    {
        return new Stmt\ClassMethod($name, [
            // A child declaration may replace the generated visibility marker.
            // This method is metadata for the object handler, not a final PHP API.
            'flags' => Modifiers::PUBLIC,
            'returnType' => new Node\Identifier('void'),
            'stmts' => [],
        ], $attributes);
    }

    /** @return list<Stmt> */
    private static function getterStatements(Node\PropertyHook $hook, string $property): array
    {
        if ($hook->body === null) {
            return [new Stmt\Return_(self::backingFetch($property))];
        }
        if ($hook->body instanceof Expr) {
            return [new Stmt\Return_($hook->body, $hook->body->getAttributes())];
        }
        return $hook->body;
    }

    /** @return list<Stmt> */
    private static function setterStatements(Node\PropertyHook $hook, string $property, Param $param): array
    {
        if ($hook->body instanceof Expr) {
            return [new Stmt\Expression(
                new Expr\Assign(self::backingFetch($property), $hook->body),
                $hook->body->getAttributes()
            )];
        }
        if ($hook->body !== null) {
            return $hook->body;
        }
        return [new Stmt\Expression(new Expr\Assign(
            self::backingFetch($property),
            new Expr\Variable($param->var->name)
        ))];
    }

    private static function backingFetch(string $property): Expr\PropertyFetch
    {
        $fetch = new Expr\PropertyFetch(new Expr\Variable('this'), $property);
        $fetch->setAttribute(self::BACKING_ACCESS_ATTRIBUTE, true);
        return $fetch;
    }

    private static function markBackingAccesses(NodeAbstract|array|null $body, string $property): void
    {
        if ($body === null) {
            return;
        }
        $nodes = is_array($body) ? $body : [$body];
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class($property) extends NodeVisitorAbstract {
            public function __construct(private readonly string $property)
            {
            }

            public function enterNode(Node $node): null
            {
                if ($node instanceof Expr\PropertyFetch
                    && $node->var instanceof Expr\Variable
                    && $node->var->name === 'this'
                    && $node->name instanceof Node\Identifier
                    && $node->name->toString() === $this->property) {
                    $node->setAttribute(PropertyHookLowering::BACKING_ACCESS_ATTRIBUTE, true);
                }
                return null;
            }
        });
        $traverser->traverse($nodes);
    }

    /** @param list<Stmt> $stmts */
    private static function containsBackingAccess(array $stmts): bool
    {
        $finder = new NodeFinder();
        return $finder->findFirst(
            $stmts,
            static fn (Node $node): bool => $node->getAttribute(self::BACKING_ACCESS_ATTRIBUTE, false) === true,
        ) !== null;
    }
}

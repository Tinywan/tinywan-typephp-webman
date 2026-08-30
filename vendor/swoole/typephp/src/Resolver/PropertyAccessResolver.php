<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Resolver;

use PhpParser\Modifiers;
use PhpParser\NodeAbstract;
use TypePhp\Entity\ClassDef;
use TypePhp\Entity\PropertyDef;
use TypePhp\Type;

final class PropertyAccessResolver
{
    public function __construct(
        private readonly PropertyAccessContext $compiler,
    ) {
    }

    public function isSameClassName(string $classA, string $classB): bool
    {
        return strcasecmp(ltrim($classA, '\\'), ltrim($classB, '\\')) === 0;
    }

    public function isSameOrSubclassOf(string $class, string $parent): bool
    {
        $class = strtolower(ltrim($class, '\\'));
        $parent = strtolower(ltrim($parent, '\\'));
        while ($class !== '') {
            if ($class === $parent) {
                return true;
            }

            // The conversion snapshot may not retain every user-to-internal
            // edge in the parent index. A compiled class still carries its
            // declared parent, so prefer that value when it is available.
            $classDef = $this->compiler->getClassDef($class);
            $next = $classDef?->extends ?: $this->compiler->getParentClass($class);

            // Parent names retain declaration casing. Normalize every hop,
            // not only the initial class name.
            $class = strtolower(ltrim($next, '\\'));
        }
        return false;
    }

    public function canAccessProtectedProperty(string $scope, string $declaringClass): bool
    {
        if ($scope === '') {
            return false;
        }
        return $this->isSameOrSubclassOf($scope, $declaringClass)
            || $this->isSameOrSubclassOf($declaringClass, $scope);
    }

    public function resolveNativeProperty(
        NodeAbstract $expr,
        string $property,
        string $class,
        string $scope,
        bool $static = false,
    ): ?PropertyAccessResult {
        $class = ltrim($class, '\\');
        $findClass = $class;

        while (true) {
            $classDef = $this->compiler->getClassDef($findClass);
            if ($classDef === null) {
                // 非编译单元内的类：尝试按内置类的声明属性解析（offset 缓存）
                return $this->resolveInternalClassProperty($expr, $property, $findClass, $class, $scope, $static);
            }

            if ($classDef->hasProperty($property)) {
                $propertyDef = $classDef->getProperty($property);
                if (!$static && $propertyDef->isStatic()) {
                    $this->fatal($expr, "Cannot access static property `{$class}::\${$property}` as non-static instance property.");
                }
                if ($static && !$propertyDef->isStatic()) {
                    $this->fatal($expr, "Cannot access non-static property `{$class}::\${$property}` as static property.");
                }
                if ($propertyDef->isPublic()) {
                    return new PropertyAccessResult($class, $findClass, $property, $classDef, $propertyDef);
                }
                if ($propertyDef->isProtected()) {
                    if ($this->canAccessProtectedProperty($scope, $findClass)) {
                        return new PropertyAccessResult($class, $findClass, $property, $classDef, $propertyDef);
                    }
                    $displayClass = ltrim($class, '\\');
                    $this->fatal($expr, "Cannot access protected property `{$property}` of class `{$displayClass}`");
                }
                if ($this->isSameClassName($scope, $findClass)) {
                    return new PropertyAccessResult($class, $findClass, $property, $classDef, $propertyDef);
                }
                $displayClass = ltrim($class, '\\');
                $this->fatal($expr, "Cannot access private property `{$property}` of class `{$displayClass}`");
            }

            if (!$classDef->extends) {
                break;
            }
            $findClass = $classDef->extends;
        }

        return null;
    }

    public function resolveNativeInstanceProperty(
        NodeAbstract $expr,
        string $property,
        string $class,
        string $scope,
    ): ?PropertyAccessResult {
        return $this->resolveNativeProperty($expr, $property, $class, $scope);
    }

    public function resolveNativeStaticProperty(
        NodeAbstract $expr,
        string $property,
        string $class,
        string $scope,
    ): ?PropertyAccessResult {
        return $this->resolveNativeProperty($expr, $property, $class, $scope, true);
    }

    /**
     * @param array<int, array{node: NodeAbstract, property: string}> $properties
     * @return array<int, PropertyAccessResult>
     */
    public function resolveNullsafePropertyChain(
        string $baseClass,
        array $properties,
        string $scope,
        string $objectType,
    ): array {
        if ($baseClass === '') {
            return [];
        }

        $className = $baseClass;
        $resolved = [];
        foreach ($properties as $index => $property) {
            $result = $this->resolveNativeProperty($property['node'], $property['property'], $className, $scope);
            if ($result === null) {
                break;
            }

            $resolved[$index] = $result;
            $def = $result->propertyDef;
            if ($def->type !== $objectType || $def->class === '') {
                break;
            }
            $className = $def->class;
        }

        return $resolved;
    }

    /**
     * 解析 PHP 内置类的声明属性，使其可以进入稳定属性 offset 缓存。
     *
     * 仅处理反射可见的声明属性：动态属性、魔术属性（__get/__set）反射不可见，
     * 返回 null 回退到按名字符串查找路径。内置类在 MINIT 注册、进程级存活，
     * 其声明属性的 offset 终身不变，缓存安全。
     */
    private function resolveInternalClassProperty(
        NodeAbstract $expr,
        string $property,
        string $findClass,
        string $requestedClass,
        string $scope,
        bool $static,
    ): ?PropertyAccessResult {
        $ref = Reflection::getClass($findClass);
        if ($ref === null || !$ref->isInternal()) {
            return null;
        }
        if (!$ref->hasProperty($property)) {
            return null;
        }
        $propRef = $ref->getProperty($property);
        // PHP 8.4 属性钩子必须由引擎调用，offset 直读会绕过钩子，回退字符串路径
        if ($propRef->hasHooks()) {
            return null;
        }
        if (!$static && $propRef->isStatic()) {
            $this->fatal($expr, "Cannot access static property `{$requestedClass}::\${$property}` as non-static instance property.");
        }
        if ($static && !$propRef->isStatic()) {
            $this->fatal($expr, "Cannot access non-static property `{$requestedClass}::\${$property}` as static property.");
        }

        $declaringClass = $propRef->getDeclaringClass()->getName();
        if ($propRef->isProtected()) {
            // Reaching an internal declaration by walking requestedClass's
            // compiled parent chain already proves that requestedClass may
            // access the inherited protected slot. Avoid rebuilding that
            // relationship from a conversion-time parent index here.
            if (!$this->isSameClassName($scope, $requestedClass)
                && !$this->canAccessProtectedProperty($scope, $declaringClass)) {
                $displayClass = ltrim($requestedClass, '\\');
                $this->fatal($expr, "Cannot access protected property `{$property}` of class `{$displayClass}`");
            }
        } elseif ($propRef->isPrivate() && !$this->isSameClassName($scope, $declaringClass)) {
            $displayClass = ltrim($requestedClass, '\\');
            $this->fatal($expr, "Cannot access private property `{$property}` of class `{$displayClass}`");
        }

        // 复合类型（union/intersection）的运行时检查结构依赖 AST 构建，
        // 无法从反射便捷还原，回退字符串路径以保证类型安全
        $propType = $propRef->getType();
        if ($propType !== null && !$propType instanceof \ReflectionNamedType) {
            return null;
        }

        $type = Type::VAR;
        $nullable = true;
        $objectClass = '';
        if ($propType instanceof \ReflectionNamedType) {
            $nullable = $propType->allowsNull();
            $typeName = $propType->getName();
            $type = match ($typeName) {
                'int' => Type::INT,
                'float' => Type::FLOAT,
                'bool', 'true', 'false' => Type::BOOL,
                'string' => Type::STR,
                'array' => Type::ARRAY,
                'object' => Type::OBJECT,
                default => $propType->isBuiltin() ? Type::VAR : Type::OBJECT,
            };
            if ($type === Type::OBJECT && $typeName !== 'object') {
                $objectClass = $typeName;
            }
        }

        $flags = 0;
        if ($propRef->isPublic()) {
            $flags |= Modifiers::PUBLIC;
        }
        if ($propRef->isProtected()) {
            $flags |= Modifiers::PROTECTED;
        }
        if ($propRef->isPrivate()) {
            $flags |= Modifiers::PRIVATE;
        }
        if ($propRef->isStatic()) {
            $flags |= Modifiers::STATIC;
        }
        if ($propRef->isPrivateSet()) {
            $flags |= Modifiers::PRIVATE_SET;
        } elseif ($propRef->isProtectedSet()) {
            $flags |= Modifiers::PROTECTED_SET;
        }

        $propertyDef = new PropertyDef($property, $flags, $type, null, $nullable);
        $propertyDef->readonly = $propRef->isReadOnly();
        $propertyDef->class = $objectClass;

        $declaringName = $declaringClass;
        $namespace = '';
        if (($pos = strrpos($declaringName, '\\')) !== false) {
            $namespace = substr($declaringName, 0, $pos);
            $declaringName = substr($declaringName, $pos + 1);
        }
        $classDef = new ClassDef($declaringName, 0, $namespace);

        return new PropertyAccessResult($requestedClass, $declaringClass, $property, $classDef, $propertyDef);
    }

    private function fatal(NodeAbstract $expr, string $message): never
    {
        $this->compiler->fatalError($expr, $message);
    }
}

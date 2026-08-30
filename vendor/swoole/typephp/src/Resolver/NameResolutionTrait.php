<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Resolver;

use TypePhp\Type;

use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\NodeAbstract;

trait NameResolutionTrait
{
    public function getNamespacedClassName(string $class, string $currentNamespace = ''): string
    {
        if ($class === '') {
            $this->error('Class name can not be empty');
        }
        if ($class[0] === '\\') {
            return ltrim($class, '\\');
        }

        $ns2 = explode('\\', trim($class, '\\'));

        $aliasTarget = $this->getClassImportAlias($ns2[0]);
        if ($aliasTarget !== null) {
            $ns = '\\' . $aliasTarget;
            _return:
            if (count($ns2) > 1) {
                $ns .= '\\' . implode('\\', array_slice($ns2, 1));
            }
            return ltrim($ns, '\\');
        }

        foreach ($this->useNamespaces as $useNamespace) {
            $ns1 = explode('\\', trim($useNamespace, '\\'));
            if (strcasecmp($ns1[array_key_last($ns1)], $ns2[0]) === 0) {
                $ns = '\\' . implode('\\', $ns1);
                goto _return;
            }
        }

        // Handle qualified names that exactly match a use import (e.g. the extends
        // of an anonymous class may already be a qualified name like "A\B\C" when the
        // use import is also "A\B\C").
        if (count($ns2) > 1) {
            foreach ($this->useNamespaces as $useNamespace) {
                if (strcasecmp(trim($useNamespace, '\\'), $class) === 0) {
                    return $class;
                }
            }
        }

        if (!$currentNamespace) {
            $currentNamespace = $this->namespace;
        }
        if (!empty($currentNamespace)) {
            return trim($currentNamespace, '\\') . '\\' . $class;
        }

        return $class;
    }

    /**
     * 将 trait 方法参数中的类名 Name 节点升级为 Name\FullyQualified。
     * 对于已由 parseTypeDecl() 解析的限定名（含 \），直接升级节点类型；
     * 对于尚未解析的非限定名（如 NullableType 内层，parseTypeDecl 返回 TYPE_VAR 跳过了解析），
     * 先通过 useAliases/useNamespaces 解析再升级。
     * gen_stub.php 的 SimpleType::fromNode() 依赖 isFullyQualified() 判断是否需要再次解析，
     * 若不升级为 FullyQualified，在上下文丢失后会被错误地追加当前 namespace 前缀。
     */
    protected function upgradeToFullyQualifiedName(?NodeAbstract $type): ?NodeAbstract
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof Node\NullableType) {
            return new Node\NullableType($this->upgradeToFullyQualifiedName($type->type));
        }
        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $i => $subType) {
                $type->types[$i] = $this->upgradeToFullyQualifiedName($subType);
            }
            return $type;
        }
        if ($type instanceof Node\IntersectionType) {
            foreach ($type->types as $i => $subType) {
                $type->types[$i] = $this->upgradeToFullyQualifiedName($subType);
            }
            return $type;
        }
        if ($type instanceof Node\Name\FullyQualified) {
            return $type;
        }
        if ($type instanceof Node\Name) {
            $typeName = $type->toString();
            if (isset($this->zendTypeMap[strtolower($typeName)]) || in_array(strtolower($typeName), ['self', 'static', 'parent'], true)) {
                return $type;
            }
            $resolved = $typeName;
            $firstSegment = explode('\\', $typeName, 2)[0];
            $hasImportedPrefix = $this->getClassImportAlias($firstSegment) !== null;
            if (!$hasImportedPrefix) {
                foreach ($this->useNamespaces as $useNamespace) {
                    $segments = explode('\\', trim($useNamespace, '\\'));
                    if (strcasecmp($segments[array_key_last($segments)], $firstSegment) === 0) {
                        $hasImportedPrefix = true;
                        break;
                    }
                }
            }
            if (!$type->isQualified() || $hasImportedPrefix) {
                $resolved = $this->getNamespacedClassName($typeName);
            }
            return new Node\Name\FullyQualified($resolved, $type->getAttributes());
        }
        return $type;
    }

    private function getClassImportAlias(string $name): ?string
    {
        return $this->useAliases[strtolower($name)] ?? null;
    }

    /**
     * 函数名称处理，补齐 namespace
     */
    public function getNamespacedFuncName(string $funcName): string
    {
        if ($funcName[0] == '\\') {
            return ltrim($funcName, '\\');
        }
        if (isset($this->useFunctions[$funcName])) {
            return $this->useFunctions[$funcName];
        }
        return $funcName;
    }

    /**
     * @param string $class 一定是带有命名空间的完整类名
     */
    protected function resolveTypeDecl(?NodeAbstract $type, int $what): array
    {
        $class = '';
        $declaredType = $this->parseTypeDecl($type, $what, $class);
        return [$declaredType, $class];
    }

    protected function parseTypeDecl(?NodeAbstract $type, int $what, string &$class): string
    {
        // 未定义类型视为 var (mixed, any)
        if ($type === null) {
            return Type::VAR;
        }
        if ($type instanceof UnionType || $type instanceof NullableType || $type instanceof IntersectionType) {
            // 复杂类型静态阶段统一按 mixed/var 处理，运行时再由 typeCheck 兜底。
            return Type::VAR;
        } else {
            $typeName = $this->parseIdentifier($type);
            $typeNameLower = strtolower($typeName);
            // 属性和类常量的类型不能声明为 void/never ，只有返回值可以
            if ($what !== self::DECL_TYPE_OF_RETURN and ($typeNameLower === 'void' or $typeNameLower === 'never')) {
                $this->fatalError($type, 'The type `void`/`never` is allowed only for return type');
            } elseif (isset($this->zendTypeMap[$typeNameLower])) {
                return $this->getTypeFromZendType($typeNameLower);
            } else {
                if ($typeName === 'self') {
                    $class = $this->getFullClassLikeName();
                } elseif ($typeName === 'parent') {
                    if (!$this->classDef) {
                        $this->fatalError($type, 'Cannot use "parent" type declaration outside a class');
                    }
                    $class = $this->classDef->extends;
                } elseif ($typeName === 'static') {
                    // static 类无法在编译期获取
                    $class = '';
                } else {
                    $class = $this->getNamespacedClassName($typeName);
                }
                // Trait 在注入 class 需要使用完整类名
                if ($class and $this->classDef and $this->classDef->trait) {
                    $type->name = $class;
                }
                return Type::OBJECT;
            }
        }
    }
}

<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Resolver;

use TypePhp\Metadata\Constants;

class Reflection
{
    private static array $functions = [];
    private static array $classes = [];
    private static array $interfaces = [];

    public static function isTypePhpExtension(mixed $extensionName): bool
    {
        return is_string($extensionName)
            && str_starts_with($extensionName, Constants::EXTENSION_PREFIX);
    }

    public static function isInternalClass(string $class): bool
    {
        static $internalClasses = null;

        if ($internalClasses === null) {
            $allClasses = get_declared_classes();

            $internalClasses = [];
            foreach ($allClasses as $className) {
                try {
                    $ref = new \ReflectionClass($className);
                    $extensionName = $ref->getExtensionName();
                    // Classes registered by the host AOT binary are implementation
                    // details, not built-ins of the target PHP environment.
                    if ($ref->isInternal() && !self::isTypePhpExtension($extensionName)) {
                        $internalClasses[strtolower($className)] = true;
                    }
                } catch (\ReflectionException) {
                    continue;
                }
            }
        }

        return isset($internalClasses[strtolower($class)]);
    }

    public static function isInternalInterface(string $interface): bool
    {
        static $internalInterfaces = null;

        if ($internalInterfaces === null) {
            $allInterfaces = get_declared_interfaces();
            $internalInterfaces = [];
            foreach ($allInterfaces as $interfaceName) {
                try {
                    $ref = new \ReflectionClass($interfaceName);
                    if ($ref->isInternal() && !self::isTypePhpExtension($ref->getExtensionName())) {
                        $internalInterfaces[strtolower($interfaceName)] = true;
                    }
                } catch (\ReflectionException) {
                    continue;
                }
            }
        }

        return isset($internalInterfaces[strtolower($interface)]);
    }

    public static function getFunction(string $fn): ?\ReflectionFunction
    {
        if (!isset(self::$functions[$fn])) {
            try {
                $ref = new \ReflectionFunction($fn);
            } catch (\ReflectionException $e) {
                return null;
            }
            self::$functions[$fn] = $ref;
        }

        return self::$functions[$fn];
    }

    public static function getClass(string $className): ?\ReflectionClass
    {
        if (!isset(self::$classes[$className])) {
            try {
                $ref = new \ReflectionClass($className);
            } catch (\ReflectionException $e) {
                return null;
            }
            self::$classes[$className] = $ref;
        }

        return self::$classes[$className];
    }

    public static function getFunctionReturnType(string $fn): ?string
    {
        $func = self::getFunction($fn);
        if (!$func) {
            return null;
        }
        return self::extractNamedReturnType($func->getReturnType());
    }

    public static function getFunctionParameter(string $fn, int $index): ?\ReflectionParameter
    {
        $func = self::getFunction($fn);
        if (!$func) {
            return null;
        }
        $args = $func->getParameters();
        if ($index >= count($args)) {
            return null;
        }

        return $args[$index];
    }

    public static function getClassMethodModifiers(string $className, string $fn): ?int
    {
        $classRef = self::getClass($className);
        if (!$classRef) {
            return null;
        }

        try {
            $method = $classRef->getMethod($fn);
            return $method->getModifiers();
        } catch (\ReflectionException $e) {
            return null;
        }
    }

    public static function getClassMethodParameter(string $className, string $fn, int $index): ?\ReflectionParameter
    {
        $classRef = self::getClass($className);
        if (!$classRef) {
            return null;
        }

        try {
            $method = $classRef->getMethod($fn);
        } catch (\ReflectionException $e) {
            return null;
        }

        $args = $method->getParameters();
        if ($index >= count($args)) {
            return null;
        }
        return $args[$index];
    }

    public static function hasMethod(string $extends, string $method): bool
    {
        $class = self::getClass($extends);
        if (!$class) {
            return false;
        }
        return $class->hasMethod($method);
    }

    public static function getMethodReturnType(string $class, string $method): ?string
    {
        $classRef = self::getClass($class);
        if (!$classRef) {
            return null;
        }
        if (!$classRef->hasMethod($method)) {
            return null;
        }
        $methodDef = $classRef->getMethod($method);
        return self::extractNamedReturnType($methodDef->getReturnType());
    }

    public static function isAbstractClass(string $name): bool
    {
        $class = self::getClass($name);
        if (!$class) {
            return false;
        }
        return $class->isAbstract();
    }

    /**
     * When the parameter index exceeds the declared range, try to obtain the last parameter as a variadic parameter (...$rest).
     * Returns the variadic parameter object, or null.
     */
    public static function getVariadicParameter(string $funcName, string $className = ''): ?\ReflectionParameter
    {
        if ($className) {
            $classRef = self::getClass($className);
            if (!$classRef) {
                return null;
            }
            try {
                $params = $classRef->getMethod($funcName)->getParameters();
            } catch (\ReflectionException) {
                return null;
            }
        } else {
            $funcRef = self::getFunction($funcName);
            if (!$funcRef) {
                return null;
            }
            $params = $funcRef->getParameters();
        }

        if (empty($params)) {
            return null;
        }

        $lastParam = end($params);
        return $lastParam->isVariadic() ? $lastParam : null;
    }

    private static function extractNamedReturnType(?\ReflectionType $returnType): ?string
    {
        if (!$returnType instanceof \ReflectionNamedType) {
            return null;
        }

        return $returnType->getName();
    }
}

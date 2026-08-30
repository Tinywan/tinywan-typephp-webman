<?php

/**
 * PHP 反射 API 示例 - 打印函数的参数和返回值类型信息
 */

// ============================================================
// 1. 打印内置函数的信息 (展示 Tentative Return Type)
// ============================================================
echo "===== 1. 内置函数: fopen =====" . PHP_EOL;
printFunctionReflection('random_bytes');
echo PHP_EOL;

echo "===== 1b. 内置方法: DateTime::format(展示 tentative return type) =====" . PHP_EOL;
printMethodReflection(\DateTime::class, 'format');
echo PHP_EOL;

// ============================================================
// 2. 打印用户自定义函数的信息
// ============================================================

/**
 * 计算两个数的和
 */
function add(int $a, int $b): int
{
    return $a + $b;
}

/**
 * 连接字符串和数组
 */
function concat(string $prefix, array $items, string $separator = ', '): string
{
    return $prefix . implode($separator, $items);
}

/**
 * 返回一个对象或 null
 */
function findUser(int $id): ?stdClass
{
    return null;
}

/**
 * 无返回值函数
 */
function logMessage(string $message): void
{
    echo "[LOG] $message" . PHP_EOL;
}

/**
 * 可变参数 + 联合类型
 */
function sum(int|float ...$numbers): int|float
{
    return array_sum($numbers);
}

echo "===== 2. 自定义函数: add =====" . PHP_EOL;
printFunctionReflection('add');
echo PHP_EOL;

echo "===== 3. 自定义函数: concat =====" . PHP_EOL;
printFunctionReflection('concat');
echo PHP_EOL;

echo "===== 4. 自定义函数: findUser =====" . PHP_EOL;
printFunctionReflection('findUser');
echo PHP_EOL;

echo "===== 5. 自定义函数: logMessage =====" . PHP_EOL;
printFunctionReflection('logMessage');
echo PHP_EOL;

echo "===== 6. 自定义函数: sum(可变参数+联合类型) =====" . PHP_EOL;
printFunctionReflection('sum');
echo PHP_EOL;

// ============================================================
// 3. 打印类方法的信息
// ============================================================

class Calculator
{
    /**
     * 除法运算
     */
    public function divide(float $a, float $b): float|false
    {
        if ($b == 0) {
            return false;
        }
        return $a / $b;
    }

    /**
     * 批量计算
     */
    public static function batchCalculate(array $operations): array
    {
        return $operations;
    }
}

echo "===== 7. 类方法: Calculator::divide =====" . PHP_EOL;
printMethodReflection('Calculator', 'divide');
echo PHP_EOL;

echo "===== 8. 类方法: Calculator::batchCalculate(static) =====" . PHP_EOL;
printMethodReflection('Calculator', 'batchCalculate');
echo PHP_EOL;

// ============================================================
// 4. 打印闭包(Closure)的信息
// ============================================================
echo "===== 9. 闭包(Closure) =====" . PHP_EOL;
$closure = function (string $name, int $age = 18): string {
    return "Name: $name, Age: $age";
};
printFunctionReflection($closure);
echo PHP_EOL;

// ============================================================
// 辅助函数
// ============================================================

/**
 * 打印函数的参数和返回值类型信息
 *
 * @param ReflectionFunction|Closure|string $function 函数名、闭包或 ReflectionFunction 对象
 */
function printFunctionReflection(Closure|string $function): void
{
    try {
        $ref = new ReflectionFunction($function);
        printReflectionDetails($ref);
    } catch (ReflectionException $e) {
        echo "  [错误] " . $e->getMessage() . PHP_EOL;
    }
}

/**
 * 打印类方法的参数和返回值类型信息
 *
 * @param string $className  类名
 * @param string $methodName 方法名
 */
function printMethodReflection(string $className, string $methodName): void
{
    try {
        $ref = new ReflectionMethod($className, $methodName);
        printReflectionDetails($ref);
    } catch (ReflectionException $e) {
        echo "  [错误] " . $e->getMessage() . PHP_EOL;
    }
}

/**
 * 打印反射细节（通用，适用于 ReflectionFunction / ReflectionMethod）
 */
function printReflectionDetails(ReflectionFunctionAbstract $ref): void
{
    // 名称
    $name = $ref instanceof ReflectionMethod
        ? $ref->getDeclaringClass()->getName() . '::' . $ref->getName()
        : $ref->getName();
    echo "  名称: {$name}" . PHP_EOL;

    // 是否内置
    echo "  类型: " . ($ref->isInternal() ? '内置函数' : '用户自定义') . PHP_EOL;

    // 是否为静态方法
    if ($ref instanceof ReflectionMethod) {
        echo "  静态: " . ($ref->isStatic() ? '是' : '否') . PHP_EOL;
    }

    // 是否为可变参数
    echo "  可变参数: " . ($ref->isVariadic() ? '是' : '否') . PHP_EOL;

    // 参数信息
    $params = $ref->getParameters();
    echo "  参数个数: " . count($params) . PHP_EOL;

    foreach ($params as $i => $param) {
        echo "    参数 #{$i}: \${$param->getName()}" . PHP_EOL;

        // 类型
        $type = $param->getType();
        if ($type) {
            echo "      类型: " . formatType($type) . PHP_EOL;
        } else {
            echo "      类型: (未声明)" . PHP_EOL;
        }

        // 默认值
        if ($param->isDefaultValueAvailable()) {
            $defaultValue = $param->getDefaultValue();
            echo "      默认值: " . formatDefaultValue($defaultValue) . PHP_EOL;
        }

        // 是否为可变参数
        if ($param->isVariadic()) {
            echo "      (可变参数)" . PHP_EOL;
        }

        // 是否可为 null
        if ($type && $type->allowsNull()) {
            echo "      允许 null: 是" . PHP_EOL;
        }

        // 是否为引用传递
        if ($param->isPassedByReference()) {
            echo "      引用传递: 是" . PHP_EOL;
        }
    }

    // 返回值类型
    $returnType = $ref->getReturnType();
    if ($returnType) {
        echo "  返回值类型: " . formatType($returnType) . PHP_EOL;
    } else {
        echo "  返回值类型: (未声明)" . PHP_EOL;
    }

    // Tentative Return Type (PHP 8.0+，主要用于内置函数/方法)
    $tentativeReturnType = $ref->getTentativeReturnType();
    if ($tentativeReturnType) {
        echo "  Tentative 返回值类型: " . formatType($tentativeReturnType) . PHP_EOL;
        echo '  说明: 这是 PHP 8.0+ 为旧版内置函数/方法添加的【暂定返回值类型】，不强制要求继承类或用户代码实现' . PHP_EOL;
    } else {
        echo "  Tentative 返回值类型: (未声明)" . PHP_EOL;
    }
}

/**
 * 格式化类型信息（支持联合类型、交集类型等）
 */
function formatType(ReflectionType $type): string
{
    if ($type instanceof ReflectionUnionType) {
        $types = [];
        foreach ($type->getTypes() as $t) {
            $types[] = formatNamedType($t);
        }
        return implode(' | ', $types);
    }

    if ($type instanceof ReflectionIntersectionType) {
        $types = [];
        foreach ($type->getTypes() as $t) {
            $types[] = formatNamedType($t);
        }
        return implode(' & ', $types);
    }

    if ($type instanceof ReflectionNamedType) {
        return formatNamedType($type);
    }

    return (string)$type;
}

/**
 * 格式化命名类型
 */
function formatNamedType(ReflectionNamedType $type): string
{
    $prefix = $type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '';
    return $prefix . $type->getName();
}

/**
 * 格式化默认值显示
 */
function formatDefaultValue(mixed $value): string
{
    if ($value === null) {
        return 'null';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_string($value)) {
        return "'{$value}'";
    }
    if (is_array($value)) {
        return '[]';
    }
    if (is_float($value)) {
        return var_export($value, true);
    }
    return (string)$value;
}

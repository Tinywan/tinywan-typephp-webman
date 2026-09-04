# Swoole AOT 强类型高性能容器，数组访问性能提升 10 倍

> Std Container 使用 PHPX Box 保存具体 C++ 模板实例。它与普通 Zend Object、Native
> Class Object 的存储和传递边界见
> [OBJECT_STORAGE_AND_PASSING_MODELS.md](OBJECT_STORAGE_AND_PASSING_MODELS.md)。

Swoole AOT 编译器为 PHP 提供了一组 `std` 强类型容器，用于在 AOT 编译场景下替代部分性能敏感路径中的 PHP Array。它们保留接近 PHP 的访问语法，同时让编译器获得明确的元素类型、键类型和容器结构，从而生成更直接、更低开销的 C++ 代码。

## PHP Array 的问题

PHP Array 是一个非常灵活的数据结构，它既可以作为列表，也可以作为哈希表、字典、结构体使用：

```php
$data = [];
$data[] = 1;
$data["name"] = "swoole";
$data[10] = new stdClass();
```

这种灵活性带来了便利，但在大型工程和高性能场景中也会产生问题。

### 编程规范问题

PHP Array 的 key 和 value 类型都不固定，容易出现隐式约定：

```php
$user = [
    "id" => 1,
    "name" => "alice",
    "tags" => ["php", "swoole"],
];
```

这类结构通常依赖注释、文档或团队规范来保证正确性：

```php
/**
 * @param array{id:int, name:string, tags:string[]} $user
 */
function saveUser(array $user): void
{
}
```

但运行时并不会天然保证：

- `id` 一定是 int
- `name` 一定存在
- `tags` 一定是字符串数组
- 数组是否连续
- key 是 int 还是 string
- value 是否混入了其他类型

这会导致大量防御式代码：

```php
if (!isset($user["id"]) || !is_int($user["id"])) {
    throw new InvalidArgumentException("invalid user id");
}
```

在 AOT 编译场景中，类型不确定也会限制编译器优化。编译器无法可靠推断数组内部元素类型，就只能保守生成通用的 `php::Array` / `php::Var` 操作。

### 性能问题

PHP Array 是通用 HashTable，适合动态语言语义，但不是所有场景的最优数据结构。

典型开销包括：

- 每个元素都需要保存 zval 类型信息
- key/value 都是动态结构
- int key 和 string key 混用需要兼容处理
- 元素访问通常需要哈希查找或间接访问
- 内存布局不连续，CPU cache 命中率较低
- value 类型不确定，计算前可能需要动态类型转换
- copy-on-write、引用计数等机制带来额外运行时成本

例如：

```php
$sum = 0;
foreach ($numbers as $n) {
    $sum += $n;
}
```

如果 `$numbers` 是普通 PHP Array，编译器无法确认每个元素一定是 int。即使业务上知道它是 `int[]`，底层仍需要保留动态类型处理能力。

## std 强类型容器

Swoole AOT 提供 `std` 容器，用来表达“这个容器的结构和元素类型在编译期就是确定的”。

目前支持：

- `std::array`
- `std::vector`
- `std::ordered_map`
- `std::map`

它们的目标不是完全替代 PHP Array，而是用于性能敏感、结构稳定、类型明确的代码路径。

## std::array

`std::array` 是固定长度数组，长度和元素类型在编译期确定。

```php
function main(): void
{
    $array = std::array(Type::Int, 100);

    $array[0] = 123;
    $array[99] = 456;

    var_dump($array[0]);
}
```

特点：

- 固定长度
- 支持边界检查
- 元素类型固定
- 支持嵌套结构
- 适合矩阵、定长缓冲区、固定结构数据

嵌套示例：

```php
function main(): void
{
    $matrix = std::array(
        std::array(Type::Int, 4),
        3
    );

    $matrix[0][0] = 10;
    $matrix[2][3] = 99;

    var_dump($matrix[2][3]);
}
```

`std::array` 支持同类型 copy：

```php
function main(): void
{
    $a = std::array(Type::Int, 3);
    $b = std::array(std::array(Type::Int, 3), 2);

    $b[1][0] = 10;
    $b[1][1] = 20;
    $b[1][2] = 30;

    $a = $b[1]; // 允许，类型完全一致，执行 std::array copy
}
```

## std::vector

`std::vector` 是动态长度连续数组。

```php
function main(): void
{
    $vector = std::vector(Type::Int);

    $vector[] = 1;
    $vector[] = 2;
    $vector[] = 3;

    var_dump($vector[1]);
    var_dump(count($vector));
}
```

也可以指定初始长度：

```php
$vector = std::vector(Type::Float, 1024);
```

特点：

- 动态长度
- 连续内存
- 适合大量同类型元素
- 访问性能优于 PHP Array
- 元素类型固定

同类型 vector 可以 copy：

```php
$a = std::vector(Type::Int);
$b = std::vector(Type::Int);

$b[] = 10;
$b[] = 20;

$a = $b; // 允许，类型完全一致，执行容器 copy
```

### foreach 中修改元素

遍历 `std::vector`、`std::map` 或 `std::ordered_map` 时，可以更新已经存在的元素值，例如使用 `+=`：

```php
foreach ($vector as $index => $value) {
    $vector[$index] += 10;
}
```

遍历期间不能执行可能使 C++ iterator 失效的结构修改，包括追加元素、插入或覆盖 key、`unset()` 以及整体替换容器。编译器会直接报告错误。需要改变结构时，先记录待处理的 key，结束 `foreach` 后再统一修改。

## std::ordered_map

`std::ordered_map` 是有序 key-value 容器。

```php
function main(): void
{
    $map = std::ordered_map(
        Type::String,
        Type::Int
    );

    $map["a"] = 1;
    $map["b"] = 2;

    var_dump($map["a"]);
}
```

特点：

- key 类型固定
- value 类型固定
- 适合需要稳定 key-value 结构的场景
- 支持 string key 和 int key

示例：

```php
$map = std::ordered_map(Type::Int, Type::Float);

$map[10] = 1.25;
$map[20] = 3.5;
```

同类型 ordered_map 可以 copy：

```php
$a = std::ordered_map(Type::Int, Type::Int);
$b = std::ordered_map(Type::Int, Type::Int);

$b[10] = 100;
$a = $b;
```

## std::map

`std::map` 是哈希表 key-value 容器。

```php
function main(): void
{
    $map = std::map(
        Type::Int,
        Type::Int
    );

    $map[100] = 1;
    $map[200] = 2;

    var_dump($map[100]);
}
```

特点：

- key 类型固定
- value 类型固定
- 适合大量 key-value 查找
- 通常用于不要求顺序的映射场景

同类型 map 可以 copy：

```php
$a = std::map(Type::Int, Type::Int);
$b = std::map(Type::Int, Type::Int);

$b[1] = 42;
$a = $b;
```

## 支持的元素类型

类型符号：

```php
Type::Int
Type::Float
Type::Bool
Type::String
Type::Array
Type::Object
Type::Any
Type::Stream
Type::Box
```

也可以使用类名作为 value 类型：

```php
class User
{
}

$vector = std::vector(User::class);
$array = std::array(User::class, 10);
$map = std::ordered_map(Type::String, User::class);
```

类类型容器会在写入时检查对象类型，避免错误对象混入。

## 与 PHP Array 的转换

当 std 容器赋值给普通变量时，会自动转换为 PHP Array：

```php
function main(): void
{
    $vector = std::vector(Type::Int);
    $vector[] = 1;
    $vector[] = 2;

    $array = $vector; // 转为 PHP Array

    var_dump(is_array($array)); // true
}
```

如果左值本身是同类型 std 容器，则执行容器 copy，而不是转 PHP Array：

```php
$a = std::vector(Type::Int);
$b = std::vector(Type::Int);

$a = $b; // std::vector copy
```

如果类型不同，则不允许 copy：

```php
$a = std::vector(Type::Int);
$b = std::vector(Type::Float);

$a = $b; // 编译失败
```

## UnsafePtr 与 native 函数参数

对于需要在 native 函数之间传递 std 容器引用的场景，可以使用 `UnsafePtr` 参数。

调用方不需要显式创建 unsafe pointer：

```php
function update(UnsafePtr $ptr): void
{
    $vector = std::unsafe_cast(
        std::vector(Type::Int),
        $ptr
    );

    $vector[0] = 100;
}

function main(): void
{
    $vector = std::vector(Type::Int, 1);
    update($vector); // 编译器自动转为 UnsafePtr box
}
```

规则：

- `UnsafePtr` 只能作为 native 函数或方法参数使用
- 实参必须是 std 容器变量
- 局部变量不会是 `UnsafePtr`
- `std::unsafe_cast()` 的第二个参数必须是当前函数签名中的 `UnsafePtr` 参数
- 运行时会校验容器类型 ID，类型不一致会抛出异常

这保证了 unsafe cast 不依赖用户手写临时代码，也避免在局部变量中传播不安全指针。

## 编译原理简介

普通 PHP Array 在 AOT 编译时通常会被表示为动态结构：

```cpp
php::Array
php::Var
```

这意味着每次访问都需要保留 PHP 动态语义。

std 容器则不同。编译器在解析代码时记录容器元信息：

- 容器类型
- 元素类型
- key 类型
- class 类型
- `std::array` 的维度信息
- 类型 ID

例如：

```php
$vector = std::vector(Type::Int);
```

可以生成类似：

```cpp
php::StdVector<php::Int> vector;
```

再例如：

```php
$array = std::array(std::array(Type::Int, 3), 2);
```

可以生成类似：

```cpp
php::StdArray<php::StdArray<php::Int, 3>, 2> array;
```

因此编译器可以直接生成强类型访问代码：

```php
$array[1][2] = 100;
```

对应接近：

```cpp
array[safeIndex(1, 2)][safeIndex(2, 3)] = 100;
```

这带来几个收益：

- 类型转换在编译期确定
- 容器访问路径更短
- 元素布局更紧凑
- C++ 编译器可以进一步优化
- 错误更早暴露在编译期
- 对性能敏感代码更友好

## 使用建议

适合使用 std 容器的场景：

- 大量数值计算
- 固定结构数据
- 大量同类型元素
- 高频循环中的数组访问
- key/value 类型稳定的映射表
- 需要减少 PHP Array 动态开销的热点路径

不适合使用 std 容器的场景：

- 数据结构高度动态
- key/value 类型经常变化
- 需要完全兼容 PHP Array 行为
- 业务层灵活对象结构
- 数据来自外部输入且结构不稳定

推荐方式是：业务边界仍使用 PHP Array 或对象，性能热点内部使用 std 容器。

## 性能测试
### PHP 数组
测试代码：
```php
$u = (int)$argv[1];
echo "u: $u\n";
$r = rand(0, 10000);
$a = array_fill(0, 10000, 0);

$begin = microtime(true);
for ($i = 0; $i < 10000; $i++) {
    for ($j = 0; $j < 100000; $j++) {
        $a[$i] += $j % $u;
    }
    $a[$i] += $r;
}

echo $a[$r] . "\n";
$end = microtime(true);
echo "sec: " . ($end - $begin) . "\n";
```
测试结果：
```bash
php examples/array-loop/jit.php 999999
u: 999999
4999953010
sec: 67.638107061386108
```

### std::array
测试代码：
```php
use native_types;

function main(int $argc, array $argv): void
{
    $u = (int)$argv[2];
    echo "u: $u\n";
    $r = rand(0, 10000);
    $a = std::array(Type::Int, 10000);

    $begin = microtime(true);
    for ($i = 0; $i < 10000; $i++) {
        for ($j = 0; $j < 100000; $j++) {
            $a[$i] += $j % $u;
        }
        $a[$i] += $r;
    }

    echo $a[$r] . "\n";
    $end = microtime(true);
    echo "sec: " . ($end - $begin) . "\n";
}
```

测试结果：
```shell
./main examples/array-loop/main.php 999999
u: 999999
4999950397
sec: 6.3918659687042236
```

### C++ 测试
测试代码：
```cpp
#include <iostream>
#include <vector>
#include <cstdlib>
#include <ctime>
#include <chrono>

int main(int argc, char* argv[]) {
    std::srand(static_cast<unsigned>(std::time(nullptr)));

    long u = std::stoi(argv[1]);
    std::cout << "u: " << u << "\n";

    long r = std::rand() % 10001;
    std::vector<long> a(10000, 0);

    auto begin = std::chrono::high_resolution_clock::now();

    for (int i = 0; i < 10000; i++) {
        for (int j = 0; j < 100000; j++) {
            a[i] += j % u;
        }
        a[i] += r;
    }

    std::cout << a[r] << "\n";

    auto end = std::chrono::high_resolution_clock::now();
    std::chrono::duration<double> diff = end - begin;
    std::cout << "sec: " << diff.count() << "\n";

    return 0;
}
```
测试结果
```bash
g++ examples/array-loop/loop.cc -o loop -O3
./loop 999999
u: 999999
4999954742
sec: 6.22351
swoole@swoole-26:~/workspace/aot/compiler$ 
```

### 结论
`AOT`编译器提供的`std::array`容器性几乎是`PHP Array`的`10`倍，与`C++`的`std::vector`性能是完全一致的。

## 总结

PHP Array 是通用、灵活、表达力强的数据结构，但它的动态性会带来类型规范和性能上的成本。

Swoole AOT 的 std 容器提供了一条更适合编译优化的路径：

- 用 `std::array` 表达固定长度强类型数组
- 用 `std::vector` 表达动态连续强类型数组
- 用 `std::ordered_map` / `std::map` 表达强类型映射
- 普通变量接收 std 容器时自动转 PHP Array
- 同类型 std 容器之间支持原生 copy
- UnsafePtr 支持 native 函数间安全地传递容器引用

它们让 PHP 代码在保持较高可读性的同时，为 AOT 编译器提供足够明确的类型信息，从而获得更稳定、更可预测的性能表现。

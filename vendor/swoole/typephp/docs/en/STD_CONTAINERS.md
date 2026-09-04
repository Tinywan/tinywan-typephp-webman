# Swoole AOT Strongly-Typed High-Performance Containers — Array Access Performance Improved by 10x

> Std Container uses a PHPX Box to hold a concrete C++ template instance. For its storage
> and passing boundary relative to ordinary Zend Objects and Native
> Class Objects, see
> [OBJECT_STORAGE_AND_PASSING_MODELS.md](OBJECT_STORAGE_AND_PASSING_MODELS.md).

The Swoole AOT compiler provides PHP with a set of `std` strongly-typed containers for replacing PHP Arrays in some performance-sensitive paths under AOT compile scenarios. They keep access syntax close to PHP while letting the compiler obtain a definite element type, key type, and container structure, thereby generating more direct, lower-overhead C++ code.

## The Problem with PHP Arrays

PHP Array is a very flexible data structure that can serve as a list, a hash table, a dictionary, or a struct:

```php
$data = [];
$data[] = 1;
$data["name"] = "swoole";
$data[10] = new stdClass();
```

This flexibility brings convenience, but it also causes problems in large-scale projects and high-performance scenarios.

### Programming-Convention Problems

The key and value types of a PHP Array are not fixed, which easily leads to implicit conventions:

```php
$user = [
    "id" => 1,
    "name" => "alice",
    "tags" => ["php", "swoole"],
];
```

Structures like this usually rely on comments, documentation, or team conventions to guarantee correctness:

```php
/**
 * @param array{id:int, name:string, tags:string[]} $user
 */
function saveUser(array $user): void
{
}
```

But the runtime does not naturally guarantee:

- `id` is always an int
- `name` always exists
- `tags` is always an array of strings
- whether the array is contiguous
- whether the key is int or string
- whether values of other types are mixed in

This leads to a large amount of defensive code:

```php
if (!isset($user["id"]) || !is_int($user["id"])) {
    throw new InvalidArgumentException("invalid user id");
}
```

In AOT compile scenarios, uncertain types also limit compiler optimization. When the compiler cannot reliably infer the element types inside an array, it can only conservatively generate generic `php::Array` / `php::Var` operations.

### Performance Problems

PHP Array is a generic HashTable suited to dynamic-language semantics, but it is not the optimal data structure for all scenarios.

Typical overheads include:

- each element needs to store zval type information
- key/value are both dynamic structures
- mixing int keys and string keys requires compatibility handling
- element access usually requires hash lookup or indirect access
- the memory layout is not contiguous, resulting in a lower CPU cache hit rate
- the value type is uncertain, possibly requiring dynamic type conversion before computation
- mechanisms such as copy-on-write and reference counting add extra runtime cost

For example:

```php
$sum = 0;
foreach ($numbers as $n) {
    $sum += $n;
}
```

If `$numbers` is an ordinary PHP Array, the compiler cannot confirm that every element is definitely an int. Even if it is known from the business logic to be `int[]`, the underlying code still needs to keep dynamic type handling capability.

## std Strongly-Typed Containers

Swoole AOT provides `std` containers to express "the structure and element type of this container are definite at compile time."

Currently supported:

- `std::array`
- `std::vector`
- `std::ordered_map`
- `std::map`

Their goal is not to fully replace PHP Array, but to be used in performance-sensitive, structurally stable, and clearly-typed code paths.

## std::array

`std::array` is a fixed-length array whose length and element type are determined at compile time.

```php
function main(): void
{
    $array = std::array(Type::Int, 100);

    $array[0] = 123;
    $array[99] = 456;

    var_dump($array[0]);
}
```

Characteristics:

- fixed length
- supports bounds checking
- fixed element type
- supports nested structures
- suited for matrices, fixed-length buffers, and fixed-structure data

Nested example:

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

`std::array` supports copy of the same type:

```php
function main(): void
{
    $a = std::array(Type::Int, 3);
    $b = std::array(std::array(Type::Int, 3), 2);

    $b[1][0] = 10;
    $b[1][1] = 20;
    $b[1][2] = 30;

    $a = $b[1]; // allowed, types are exactly identical, performs a std::array copy
}
```

## std::vector

`std::vector` is a dynamically-sized contiguous array.

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

An initial length can also be specified:

```php
$vector = std::vector(Type::Float, 1024);
```

Characteristics:

- dynamic length
- contiguous memory
- suited for a large number of elements of the same type
- better access performance than PHP Array
- fixed element type

Vectors of the same type can be copied:

```php
$a = std::vector(Type::Int);
$b = std::vector(Type::Int);

$b[] = 10;
$b[] = 20;

$a = $b; // allowed, types are exactly identical, performs a container copy
```

### Modifying Elements in foreach

When iterating over `std::vector`, `std::map`, or `std::ordered_map`, you can update the values of existing elements, for example using `+=`:

```php
foreach ($vector as $index => $value) {
    $vector[$index] += 10;
}
```

During iteration you cannot perform structural modifications that may invalidate the C++ iterator, including appending elements, inserting or overwriting keys, `unset()`, and replacing the container as a whole. The compiler reports these directly as errors. When the structure needs to change, record the keys to be processed first and apply the modifications uniformly after the `foreach` ends.

## std::ordered_map

`std::ordered_map` is an ordered key-value container.

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

Characteristics:

- fixed key type
- fixed value type
- suited for scenarios requiring a stable key-value structure
- supports string keys and int keys

Example:

```php
$map = std::ordered_map(Type::Int, Type::Float);

$map[10] = 1.25;
$map[20] = 3.5;
```

ordered_map of the same type can be copied:

```php
$a = std::ordered_map(Type::Int, Type::Int);
$b = std::ordered_map(Type::Int, Type::Int);

$b[10] = 100;
$a = $b;
```

## std::map

`std::map` is a hash-table key-value container.

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

Characteristics:

- fixed key type
- fixed value type
- suited for a large number of key-value lookups
- usually used for mapping scenarios that do not require ordering

map of the same type can be copied:

```php
$a = std::map(Type::Int, Type::Int);
$b = std::map(Type::Int, Type::Int);

$b[1] = 42;
$a = $b;
```

## Supported Element Types

Type symbols:

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

Class names can also be used as the value type:

```php
class User
{
}

$vector = std::vector(User::class);
$array = std::array(User::class, 10);
$map = std::ordered_map(Type::String, User::class);
```

Class-typed containers check the object type at write time to prevent mixing in incorrect objects.

## Conversion with PHP Array

When a std container is assigned to an ordinary variable, it is automatically converted to a PHP Array:

```php
function main(): void
{
    $vector = std::vector(Type::Int);
    $vector[] = 1;
    $vector[] = 2;

    $array = $vector; // converted to PHP Array

    var_dump(is_array($array)); // true
}
```

If the lvalue itself is a std container of the same type, a container copy is performed instead of converting to a PHP Array:

```php
$a = std::vector(Type::Int);
$b = std::vector(Type::Int);

$a = $b; // std::vector copy
```

If the types differ, the copy is not allowed:

```php
$a = std::vector(Type::Int);
$b = std::vector(Type::Float);

$a = $b; // compile failure
```

## UnsafePtr and native Function Parameters

For scenarios where a std container reference needs to be passed between native functions, the `UnsafePtr` parameter can be used.

The caller does not need to explicitly create an unsafe pointer:

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
    update($vector); // the compiler automatically converts to an UnsafePtr box
}
```

Rules:

- `UnsafePtr` can only be used as a native function or method parameter
- the argument must be a std container variable
- local variables are never `UnsafePtr`
- the second argument of `std::unsafe_cast()` must be an `UnsafePtr` parameter in the current function signature
- the runtime validates the container type ID; inconsistent types throw an exception

This ensures that unsafe casts do not depend on user-written temporary code and also avoids propagating unsafe pointers in local variables.

## A Brief Introduction to the Compilation Principle

An ordinary PHP Array is usually represented as a dynamic structure during AOT compilation:

```cpp
php::Array
php::Var
```

This means every access needs to preserve PHP's dynamic semantics.

std containers are different. The compiler records container metadata while parsing the code:

- container type
- element type
- key type
- class type
- dimension information of `std::array`
- type ID

For example:

```php
$vector = std::vector(Type::Int);
```

can generate something like:

```cpp
php::StdVector<php::Int> vector;
```

For another example:

```php
$array = std::array(std::array(Type::Int, 3), 2);
```

can generate something like:

```cpp
php::StdArray<php::StdArray<php::Int, 3>, 2> array;
```

Therefore the compiler can directly generate strongly-typed access code:

```php
$array[1][2] = 100;
```

which corresponds approximately to:

```cpp
array[safeIndex(1, 2)][safeIndex(2, 3)] = 100;
```

This brings several benefits:

- type conversion is determined at compile time
- shorter container access paths
- more compact element layout
- the C++ compiler can further optimize
- errors surface earlier at compile time
- friendlier to performance-sensitive code

## Usage Recommendations

Scenarios suitable for std containers:

- large-scale numeric computation
- fixed-structure data
- a large number of elements of the same type
- array access in high-frequency loops
- mapping tables with stable key/value types
- hot paths that need to reduce the dynamic overhead of PHP Array

Scenarios not suitable for std containers:

- highly dynamic data structures
- frequently changing key/value types
- requiring full compatibility with PHP Array behavior
- flexible object structures at the business layer
- data from external input with an unstable structure

The recommended approach is: keep using PHP Array or objects at the business boundary, and use std containers inside performance hot spots.

## Performance Tests
### PHP Array
Test code:
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
Test result:
```bash
php examples/array-loop/jit.php 999999
u: 999999
4999953010
sec: 67.638107061386108
```

### std::array
Test code:
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

Test result:
```shell
./main examples/array-loop/main.php 999999
u: 999999
4999950397
sec: 6.3918659687042236
```

### C++ Test
Test code:
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
Test result
```bash
g++ examples/array-loop/loop.cc -o loop -O3
./loop 999999
u: 999999
4999954742
sec: 6.22351
swoole@swoole-26:~/workspace/aot/compiler$ 
```

### Conclusion
The `std::array` container provided by the `AOT` compiler is almost `10` times as fast as `PHP Array`, and its performance is completely consistent with C++'s `std::vector`.

## Summary

PHP Array is a general, flexible, and highly expressive data structure, but its dynamism brings costs in type specification and performance.

Swoole AOT's std containers provide a path better suited for compiler optimization:

- use `std::array` to express fixed-length strongly-typed arrays
- use `std::vector` to express dynamic contiguous strongly-typed arrays
- use `std::ordered_map` / `std::map` to express strongly-typed mappings
- an ordinary variable receiving a std container is automatically converted to a PHP Array
- std containers of the same type support native copy
- UnsafePtr supports safely passing container references between native functions

They let PHP code maintain high readability while providing the AOT compiler with sufficiently clear type information, thereby achieving more stable and more predictable performance.

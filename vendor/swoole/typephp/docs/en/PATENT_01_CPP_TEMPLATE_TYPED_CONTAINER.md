# Patent Application Technical Disclosure: A Method for Implementing Strongly-Typed Data Containers in a Dynamic Language Using C++ Templates

> This document is a draft technical disclosure for a patent application, intended to explain the technical solution to a patent agent. In this document, "the present invention" refers to "a method for implementing strongly-typed data containers in a dynamic language using C++ templates." This document does not constitute legal advice; the formal claims should be further drafted by a patent agent based on search results.

## 1. Technical Application Product

The present invention is applied to the Swoole-Compiler PHP AOT compiler. This product is used to pre-compile PHP dynamic-language programs into C++ native code, PHP extensions, or executable programs, while preserving PHP runtime compatibility in the compiled program.

The present invention focuses on solving the problems of indeterminate array and container structure types, high runtime overhead, and difficulty in leveraging C++ static-type optimizations in dynamic languages. The solution introduces strongly-typed container declarations at the dynamic-language syntax level and converts them into C++ template container instances during the AOT compilation stage, enabling dynamic-language programs to achieve data-structure performance close to that of a static language in localized performance hotspots, while maintaining interoperability with dynamic-language arrays.

## 2. Terminology

| Term | English Explanation |
| --- | --- |
| PHP | A dynamically-typed scripting language |
| AOT | Ahead-Of-Time; compiling to target code before the program runs |
| C++ Template | A C++ mechanism for generating strongly-typed code at compile time |
| Dynamic language | A language in which variable types and function call targets can change at runtime |
| Strongly-typed container | A container whose key, value, length, nested structure, or class constraints are determined at compile time |
| PHP Array | The built-in array of the PHP language, which combines list, dictionary, and hash table semantics |
| zval | The internal structure used by the PHP runtime to store a value of any type |
| HashTable | The common underlying hash table structure of PHP Array |
| AST | Abstract Syntax Tree |
| Meta-information | Information recorded by the compiler about container kind, type, dimension, C++ declaration, etc. |
| Type identifier | An integer identifier used to distinguish different strongly-typed container structures |
| UnsafePtr | A controlled pointer wrapper that carries a container pointer and a type identifier |

## 3. Technical Background and Existing Technical Solutions

### 3.1 Broad Technical Background

The advantage of dynamic languages lies in development flexibility. Taking PHP as an example, the same variable can hold an integer, a floating-point number, a string, an array, or an object in different places. The same PHP Array can act as a contiguous list, as a dictionary with string keys, and can simultaneously mix integer keys, string keys, and values of different types.

For example:

```php
$data = [];
$data[] = 1;
$data["name"] = "swoole";
$data[10] = new stdClass();
```

This design lowers the barrier to business development, but it also makes it difficult for the compiler to determine the real shape of a data structure at compile time. For an AOT compiler, if the array element type, key type, length, and nested structure cannot be determined, it can only conservatively generate generic dynamic container code and cannot fully leverage C++ static typing and template optimization capabilities.

In contrast, static languages such as C++, Rust, and Go generally require containers to have explicit types, for example `std::vector<int>` and `std::array<double, 100>`. Such containers have element sizes, access patterns, and memory layouts determined at compile time, so the access path is short and the compiler can further perform inlining, register allocation, and loop optimization.

The present invention attempts to establish a localized strongly-typed data container mechanism between dynamic and static languages, so that dynamic-language code still keeps array-like syntax but is converted into C++ template containers at compile time.

### 3.2 Narrow Technical Background

Swoole-Compiler is a PHP AOT compiler. It parses PHP source files into an abstract syntax tree, then generates C++ code and compiles it into a PHP extension or a binary program. For ordinary PHP variables, the compiler can use runtime wrappers such as `php::Var` and `php::Array` to represent dynamic semantics.

However, for scenarios such as high-frequency array access, numerical computation, fixed-length buffers, mapping tables, and object collections, continuing to use ordinary PHP Array incurs the following overhead:

- Both keys and values require dynamic type judgment;
- Each value usually needs to be represented by a zval;
- Hash lookup, reference counting, copy-on-write, and other mechanisms lengthen the runtime path;
- The memory layout is not contiguous, resulting in poor CPU cache utilization;
- It is difficult for the compiler to confirm whether an array stores only one specific type;
- Passing large containers across functions tends to cause copying or dynamic wrapping overhead.

Therefore, a strongly-typed container expression, checking, and code generation scheme suitable for an AOT compiler is needed.

### 3.3 Closest Existing Technical Solutions

Existing technologies can be roughly classified into the following categories:

1. **Dynamic container solution using PHP Array exclusively**  
   All arrays are represented by the PHP runtime HashTable and zval. This solution offers good compatibility, but performance is limited by dynamic typing and the hash structure.

2. **User-handwritten C++ extension solution**  
   Developers handwrite structures such as `std::vector` and `std::map` in C++ and expose them to PHP through PHP extension functions. This solution offers high performance, but the development cost is high, and users must manually maintain the type mapping between PHP and C++.

3. **Generic JIT or AOT type inference solution**  
   The compiler attempts to infer the element types of a PHP Array from context. However, the dynamic writes, dynamic keys, function parameters, and return values of PHP Array make inference unstable, so optimization is usually only possible in very localized scenarios.

4. **PHP userland container class solution**  
   For example, wrapping arrays or specific data structures through object classes. This solution improves the interface specification, but the underlying implementation may still rely on PHP objects, zval, and dynamic method calls, making it difficult to reach the performance level of C++ template containers.

## 4. Shortcomings of Existing Technologies and Objectives of the Present Invention

### 4.1 Shortcomings of Existing Technologies

Existing solutions have the following shortcomings:

1. The indeterminate types of dynamic arrays prevent the compiler from stably generating strongly-typed target code.
2. The underlying structure of ordinary PHP Array is too generic, incurring hash lookup and dynamic typing overhead in high-frequency access.
3. Handwritten C++ extensions require users to understand PHP extension development, memory management, and type conversion, raising the development barrier.
4. Generic type inference finds it hard to express complete container information such as fixed length, nested dimension, key type, and value class constraints.
5. There is a lack of unified interoperability rules between strongly-typed containers and PHP Array, easily splitting performance from compatibility.
6. When container references are passed across functions, directly exposing C++ pointers creates risks of type misuse and memory unsafety.

### 4.2 Objectives of the Present Invention

The objective of the present invention is to provide a method for implementing strongly-typed data containers in a dynamic language using C++ templates, so that dynamic-language programs can declare strongly-typed containers in localized code, the compiler generates C++ template container code, and automatic conversion to and from dynamic-language arrays is performed when necessary.

Further, the present invention also provides a container reference passing mechanism carrying a type identifier, so that strongly-typed containers can be passed between Native functions with low copy cost while runtime type consistency checking is performed.

## 5. Technical Solution of the Present Invention

### 5.1 Overall Solution

The present invention defines a set of strongly-typed container construction syntax in the dynamic language, for example:

```php
$a = std::array(Type::Int, 100);
$v = std::vector(Type::Float);
$m = std::ordered_map(Type::String, Type::Int);
$h = std::map(Type::Int, User::class);
```

The compiler recognizes these construction expressions during the AOT stage, generates container meta-information, and converts them into C++ template instances:

```cpp
php::StdArray<php::Int, 100> a{};
php::StdVector<php::Float> v{};
php::StdOrderedMap<php::Str, php::Int> m{};
php::StdMap<php::Int, php::Object> h{};
```

The compiler subsequently uses this meta-information for static checking and code generation during subscript access, assignment, iteration, function argument passing, and type conversion.

### 5.2 System Composition

```text
Figure 1: System composition diagram

PHP source input module
        |
        v
Abstract syntax tree parsing module
        |
        v
Strongly-typed container recognition module
        |
        v
Container meta-information construction module
        |
        +--> Type identifier registration module
        |
        +--> Subscript access code generation module
        |
        +--> Assignment and copy determination module
        |
        +--> PHP Array interoperability module
        |
        +--> UnsafePtr auto-boxing and checking module
        |
        v
C++ template code generation module
        |
        v
C++ compiler
        |
        v
PHP extension or binary program
```

Each module is described as follows:

- PHP source input module: reads dynamic-language source files.
- Abstract syntax tree parsing module: parses source code into syntax tree nodes.
- Strongly-typed container recognition module: recognizes container construction expressions such as `std::array`.
- Container meta-information construction module: records the container kind, key type, value type, class constraints, dimensions, etc.
- Type identifier registration module: generates a comparable type identifier for each container structure.
- Subscript access code generation module: generates array access, bounds checking, and key conversion code based on the container type.
- Assignment and copy determination module: determines whether to perform a C++ container copy or convert to PHP Array.
- PHP Array interoperability module: generates `php::toArray()` at dynamic semantic boundaries.
- UnsafePtr auto-boxing and checking module: generates container pointer wrappers carrying type identifiers at function call boundaries.
- C++ template code generation module: outputs strongly-typed C++ template container code.

### 5.3 Method Flow

```text
Figure 2: Method flow diagram

Step S1: Parse the dynamic-language source code to obtain an abstract syntax tree;
Step S2: Recognize strongly-typed container construction expressions;
Step S3: Parse the container kind, key type, value type, class constraints, and dimensions;
Step S4: Generate container meta-information and register the type identifier;
Step S5: Record in the variable table that the variable is a strongly-typed container;
Step S6: When a subscript access is encountered, generate strongly-typed access code based on the container meta-information;
Step S7: When an assignment is encountered, determine whether the left and right sides are the same strongly-typed container;
Step S8: If they are the same, generate a C++ container copy; if not, report an error or convert to PHP Array according to the rules;
Step S9: When a Native function UnsafePtr parameter is encountered, auto-box the container pointer and type identifier;
Step S10: When the callee unboxes, check the type identifier; if it matches, return a C++ reference; otherwise, throw an exception.
```

### 5.4 Container Meta-information

Container meta-information includes at least the following fields:

```text
kind: container kind, for example array, vector, map, ordered_map;
decl: target C++ template declaration;
type: C++ type of the value;
class: class name when the value is an object;
keyType: key type of map or ordered_map;
sizes: dimension array of std::array;
bytes: estimated memory size of std::array;
typeId: type identifier generated from the above fields.
```

For example:

```php
$b = std::array(std::array(Type::Int, 3), 2);
```

The corresponding meta-information can be represented as:

```text
kind=array
decl=php::StdArray<php::StdArray<php::Int, 3>, 2>
type=php::Int
sizes=[3, 2]
bytes=2 * 3 * sizeof(php::Int)
typeId=automatically assigned integer
```

### 5.5 std::array Nested Type Derivation

`std::array` supports nested structures. The present invention derives the sub-array type based on the access level.

Example:

```php
$a = std::array(Type::Int, 3);
$b = std::array(std::array(Type::Int, 3), 2);
$a = $b[1];
```

Processing method:

1. The compiler reads the dimension information `[2, 3]` of `$b`.
2. Parse the access level of `$b[1]` as 1.
3. Compute the remaining dimensions `[3]`.
4. Derive the type of `$b[1]` as `std::array<int, 3>`.
5. Compare it with the type of `$a`.
6. If they are exactly the same, generate a C++ copy:

```cpp
a = b[php::safeIndex(php::toInt(1L), 2)];
```

Here `safeIndex` denotes the bounds-checking function. It ensures that dynamic-language subscript access still preserves out-of-bounds checking semantics.

### 5.6 Same-type Copy and Dynamic Array Conversion

The present invention divides assignment into two categories:

The first category: the left value is a strongly-typed container and the right value is an exactly identical strongly-typed container:

```php
$a = std::vector(Type::Int);
$b = std::vector(Type::Int);
$a = $b;
```

Generated:

```cpp
a = b;
```

The second category: the left value is an ordinary dynamic variable and the right value is a strongly-typed container:

```php
$arr = $a;
```

Generated:

```cpp
arr = php::toArray(a);
```

This rule keeps C++ performance within strongly-typed regions, and automatically turns strongly-typed containers into ordinary PHP Array when they flow into dynamic-language regions.

### 5.7 Subscript Access and Writing

For `std::vector`:

```php
$v[] = 1;
$v[0] = 2;
```

Generated similarly to:

```cpp
v.push_back(php::toInt(1L));
v.offsetSet(php::toInt(0L), php::toInt(2L));
```

For `std::map`:

```php
$m["x"] = 10;
```

Generated similarly to:

```cpp
m.offsetSet(php::toString("x"), php::toInt(10L));
```

For class-typed values:

```php
$v = std::vector(User::class);
$v[] = new User();
```

The compiler checks whether the written object is of the specified class, avoiding mixing incorrect objects into the container.

### 5.8 foreach Iteration

Strongly-typed container iteration is compiled into a C++ iterator loop:

```php
foreach ($v as $i => $value) {
    // ...
}
```

Generated similarly to:

```cpp
for (auto it = v.begin(); it != v.end(); ++it) {
    i = it - v.begin();
    value = *it;
}
```

For map types, the key is obtained from `it->first` and the value from `it->second`.

### 5.9 UnsafePtr Auto-boxing and Type Checking

To support low-copy passing of containers between Native functions, the present invention provides the `UnsafePtr` parameter mechanism.

User code:

```php
function update(UnsafePtr $ptr): void
{
    $v = std::unsafe_cast(std::vector(Type::Int), $ptr);
    $v[0] = 100;
}

function main(): void
{
    $v = std::vector(Type::Int, 1);
    update($v);
}
```

Caller-side generation:

```cpp
php_update(php_create_unsafe_ptr(&v, typeId));
```

Callee-side generation:

```cpp
auto &v = php_unsafe_cast<php::StdVector<php::Int>>(ptr, typeId);
```

Where `UnsafePtr` holds:

```cpp
void *ptr;
uint32_t type_id;
```

When unboxing, `type_id` is compared; a C++ reference is returned only if it matches, otherwise a type exception is thrown. This avoids incorrectly converting `std::vector<int>` to `std::vector<float>`.

## 6. Key Points and Points to Protect

1. Recognize strongly-typed container construction syntax during the AOT compilation of a dynamic language, and convert it into C++ template container instances.
2. Use a container meta-information table to uniformly record the container kind, C++ declaration, key type, value type, class constraints, dimensions, memory size, and type identifier.
3. Derive the sub-array type of nested `std::array` based on the access level, and support C++ copy between sub-arrays and same-type containers.
4. Determine assignment semantics based on the left and right container meta-information: if exactly the same, generate a C++ copy; otherwise, convert to a dynamic array or report a compile-time error.
5. Generate strongly-typed C++ access code for subscript access while preserving the dynamic language's bounds-checking or key type conversion semantics.
6. Generate a C++ iterator loop for foreach while preserving the dynamic language's key/value iteration form.
7. At Native function call boundaries, auto-box strongly-typed containers into UnsafePtr carrying type identifiers based on ArgInfo.
8. Perform runtime checking based on the type identifier during unboxing, and return a C++ container reference only after the check passes.

## 7. Advantages Compared with Existing Technologies

Compared with the ordinary PHP Array solution, the present invention can determine the container structure and element types at compile time and generate C++ template instances, thereby reducing dynamic type judgment, hash lookup, and zval wrapping overhead.

Compared with the handwritten C++ extension solution, developers still use syntax close to PHP arrays; container declaration, type checking, subscript access, copy, iteration, dynamic array conversion, and cross-function reference passing are all completed automatically by the compiler, lowering the development barrier.

Compared with ordinary type inference solutions, the present invention does not rely on guessing how a PHP Array is used; instead, it establishes stable type meta-information through explicit strongly-typed container syntax, making the compilation result more predictable.

## 8. Optional Implementations

The present invention is not limited to the PHP language; it can also be used in AOT compilers for Python, JavaScript, Ruby, and other dynamic languages. As long as a dynamic-language compiler can recognize strongly-typed container declarations and generate C++, Rust, Go, or other static-language target code, a similar technical solution can be adopted.

The C++ template containers in the present invention are also not limited to `StdArray`, `StdVector`, `StdOrderedMap`, and `StdMap`; they can be extended to strongly-typed containers such as queues, sets, ring buffers, matrices, and tensors.

## 9. Confidentiality Statement

This document involves the internal compiler implementation of Swoole-Compiler, the design of strongly-typed container meta-information, the UnsafePtr type identifier mechanism, and code generation strategies. Before formal filing, it is recommended that it be managed as internal technical material.

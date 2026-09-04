# Patent Application Technical Disclosure: A Method and System for Compilation and Execution of a Hybrid-State Programming Language

> This document is a draft technical disclosure for a patent application, explaining the technical solution to a patent agent. "The present invention" herein refers to "a method and system for compilation and execution of a hybrid-state programming language". This document does not constitute legal advice; the formal claims should be further drafted by a patent agent in combination with search results.

## 1. Technical Application Product

The present invention is applied to the Swoole-Compiler PHP AOT compiler. Swoole-Compiler neither simply fully staticizes PHP, nor is it a pure interpreter or pure JIT compiler in the traditional sense, but rather a "hybrid-state programming language compilation and execution system".

In this system, the same PHP program can simultaneously contain:

- Static-state code that can be statically analyzed and compiled ahead of time;
- Dynamic-state code that needs to preserve PHP's dynamic semantics;
- Boundary transition code between static state and dynamic state;
- Entry points that can be called by the dynamic runtime as a PHP extension;
- Entry points that can be executed as a standalone binary program.

This system enables a dynamic language to gain the performance advantages of a static language while preserving the flexibility advantages of a dynamic language.

## 2. Terminology

| Term | Description |
| --- | --- |
| Hybrid state | A single program simultaneously contains static compilation state and dynamic interpretation state, and can switch between the two states |
| Static state | A state where the compiler can determine types, functions, methods, properties, or control paths at compile time |
| Dynamic state | A state where types, functions, methods, or properties must be resolved at runtime according to PHP semantics |
| AOT | Ahead-Of-Time compilation |
| VM | Virtual Machine; here it refers to the PHP Zend VM |
| Native function | A PHP function or method compiled by AOT into a C++ function |
| Dynamic call | Looking up and calling a target at runtime based on function name, method name, object type, or callback object |
| Static direct link | Determining the call target at compile time and generating a direct C++ function call |
| ArgInfo | Parameter information, including type, default value, reference, variadic parameters, etc. |
| Symbol table | A data structure in which the compiler records information about functions, classes, interfaces, traits, constants, properties, methods, etc. |
| Wrapper function | A bridging function that converts a Zend VM call entry point into a Native function call |
| Fallback | Falling back to the dynamic runtime path when static analysis cannot guarantee semantic correctness |

## 3. Technical Background and Existing Technical Solutions

### 3.1 Broad Technical Background

Traditional programming languages can generally be divided into two categories.

The first category is statically compiled languages, such as C, C++, Go, and Rust. They complete compilation before running, and types, function signatures, and memory layouts are mostly determined at compile time, resulting in high runtime performance. However, such languages generally have weaker development flexibility, with limited support for dynamic loading, dynamic method calls, and runtime modification of data structures.

The second category is dynamically interpreted languages, such as PHP, Python, Ruby, and JavaScript. They resolve variable types, function calls, object properties, and method dispatch at runtime, resulting in high development efficiency and flexible expressiveness. However, such languages generally require virtual machines, interpreters, dynamic type determination, and runtime lookups, and their performance is inferior to statically compiled languages.

Existing JIT technologies attempt to compile hot code into machine code at runtime, but JIT still relies on runtime sampling, type feedback, and hot-spot detection, and it is difficult to obtain fully deterministic native code before deployment. Traditional AOT technologies tend to fully staticize a program, but they easily lose language semantics when facing dynamic-language features such as variable functions, dynamic methods, magic methods, reflection, dynamic properties, closures, and callbacks.

### 3.2 Narrow Technical Background

Swoole-Compiler targets PHP programs. PHP programs have the following dynamic characteristics:

- Variables can hold any type;
- Functions and methods can be called dynamically through strings or arrays;
- Objects can handle dynamic behavior through magic methods such as `__call()`, `__get()`, `__set()`;
- Namespaces, use aliases, traits, inheritance, and method overrides affect the actual call target;
- Parameters support default values, named arguments, variadic parameters, and reference parameters;
- PHP built-in functions and user functions can be called interchangeably;
- A program can run either as a PHP extension or as a standalone binary program.

If the compiler forcibly converts all PHP code into static C++ calls, it will break the dynamic semantics described above. If dynamic interpreted execution is fully preserved, the performance advantages of AOT cannot be obtained.

Therefore, a new hybrid-state programming language design is needed: enter static state in regions that can be statically determined, enter dynamic state in regions that cannot, and achieve interoperation between the two states through a unified boundary mechanism.

### 3.3 Closest Existing Technical Solutions

Existing technologies include:

1. **Pure interpreted execution**  
   PHP source code is interpreted and executed by the Zend VM. This solution has strong compatibility, but every function call, property access, and array operation relies on runtime dynamic mechanisms.

2. **Pure static compilation**  
   The entire source program is converted to C/C++ or machine code. This solution has high performance, but has difficulty supporting dynamic language features, usually requiring significant syntax restrictions or changes to language semantics.

3. **JIT compilation**  
   Compilation is performed at runtime based on hot paths and type feedback. This solution can improve hot-spot performance, but still requires interpreter cooperation, and the compilation result depends on runtime state.

4. **Handwritten extensions or FFI**  
   Dynamic languages call static code through C/C++ extensions or foreign function interfaces. This solution can partially improve performance, but lacks a unified language-level compilation model between static and dynamic code.

## 4. Drawbacks of Existing Technologies and Objectives of the Present Invention

### 4.1 Drawbacks of Existing Technologies

Existing solutions have the following problems:

1. Pure interpreted execution cannot fully exploit static types and native code performance.
2. Pure static compilation has difficulty being compatible with dynamic language features such as dynamic calls, magic methods, dynamic properties, and reflection.
3. JIT relies on runtime hot spots and type feedback, making it difficult to obtain stable, predictable compilation artifacts before deployment.
4. Handwritten extensions require developers to explicitly maintain interfaces, type conversions, and lifetimes between dynamic and static languages.
5. Function, class, property, and method lookups in dynamic languages usually rely on strings, and repeated lookups are costly.
6. Traditional compilers often use "whether it can be fully staticized" as the criterion, lacking a language-level model expressing the coexistence of multiple execution states within the same program.

### 4.2 Objectives of the Present Invention

The objective of the present invention is to provide a method and system for compilation and execution of a hybrid-state programming language, so that the same dynamic language program can be divided into static state and dynamic state:

- In static state, the compiler generates C++ direct calls, strongly typed variables, strongly typed containers, and native code;
- In dynamic state, the system preserves the PHP VM's dynamic lookup, dynamic calls, dynamic properties, callbacks, and general zval semantics;
- Between the two states, interoperation is achieved through ArgInfo, wrapper functions, symbol caching, type conversion, and dynamic fallback mechanisms.

In this way, the performance advantages of a static language and the flexibility advantages of a dynamic language are both obtained.

## 5. Technical Solution of the Present Invention

### 5.1 Hybrid-State Language Model

The present invention proposes a "hybrid-state" language model. This model does not simply compile a dynamic language into a static language; instead, with the joint support of the compiler and the runtime, it divides statements, expressions, functions, methods, variables, and calls in a program into different states.

The hybrid state includes at least:

1. **Static function state**: function definitions, parameter types, and return types can be determined, generating C++ Native functions.
2. **Static object state**: the class of an object variable can be determined, and method calls can be directly linked to C++ functions.
3. **Static data state**: variables can be mapped to C++ native types or C++ template containers.
4. **Dynamic value state**: variables use `php::Var`, zval, or PHP objects for storage, preserving dynamic type semantics.
5. **Dynamic call state**: when the function name, method name, or object type cannot be determined, calls are made through the PHP runtime.
6. **Boundary bridging state**: between static and dynamic states, argument extraction, type conversion, return value write-back, and symbol caching are performed.

### 5.2 System Composition

```text
Figure 1: Hybrid-state programming language system composition diagram

PHP source code / project configuration
        |
        v
Preprocessing and symbol extraction module
        |
        v
Dependency ordering and semantic analysis module
        |
        v
Hybrid-state determination module
        |
        +--> Static-state code generation module
        |       |
        |       +--> C++ function direct link
        |       +--> C++ native types
        |       +--> C++ template containers
        |
        +--> Dynamic-state code generation module
        |       |
        |       +--> PHP VM dynamic calls
        |       +--> zval / php::Var dynamic values
        |       +--> magic methods and callbacks
        |
        +--> Boundary bridging module
                |
                +--> ArgInfo argument conversion
                +--> Zend wrapper functions
                +--> symbol caching
                +--> dynamic fallback
        |
        v
C++ source code and extension registration code
        |
        v
PHP extension or executable binary
```

### 5.3 Method Flow

```text
Figure 2: Hybrid-state compilation and execution flow diagram

Step S1: Read PHP source files and project configuration;
Step S2: Parse the abstract syntax tree;
Step S3: Extract symbols such as functions, classes, interfaces, traits, constants, properties, and methods;
Step S4: Order files by dependency based on symbol usage relationships;
Step S5: Resolve the ArgInfo of functions and methods;
Step S6: Determine for each expression or call site whether it can enter static state;
Step S7: If the target, types, and arguments can be determined, generate C++ static direct link code;
Step S8: If there is a risk of dynamic semantics, generate dynamic-state call code;
Step S9: Generate argument conversion, return value conversion, and wrapper functions for the boundary between static and dynamic states;
Step S10: Generate runtime cache mappings for functions, classes, methods, and properties;
Step S11: Compile the C++ code to produce a PHP extension or executable program;
Step S12: At runtime, execute the static direct link or dynamic fallback path according to the generated code.
```

### 5.4 Preprocessing and Symbol Extraction

Before formally generating C++ code, the compiler first scans the PHP source code and extracts:

- Function definitions;
- Class definitions;
- Interface definitions;
- Trait definitions;
- Class properties;
- Class methods;
- Class constants;
- Global constants;
- Namespaces and use aliases;
- Usage relationships among functions, classes, methods, and constants.

It then builds a symbol dependency graph and performs topological sorting on the source files. This improves the success rate of static analysis and avoids functions or classes being temporarily invisible due to file ordering.

### 5.5 ArgInfo Unified Parameter Model

The present invention uses ArgInfo to describe function and method parameters, including:

```text
Parameter name;
Parameter type;
Object class name;
Whether it is a reference parameter;
Whether it is a variadic parameter;
Whether it is nullable;
Default value;
Whether it is an UnsafePtr;
Whether it is a constructor property promotion parameter.
```

ArgInfo serves two directions simultaneously:

1. **AOT internal direct-link calls**: the compiler reorders named arguments, fills default values, merges variadic parameters, and generates C++ direct calls based on ArgInfo.
2. **Zend runtime entry calls**: when a compiled function is called by the PHP VM as a PHP extension function, the wrapper function reads parameters from the call stack and converts types according to ArgInfo.

This design allows static and dynamic states to share the same set of parameter semantics.

### 5.6 Static-State Code Generation

When the compiler can determine the call target, it generates a C++ direct call.

Example:

```php
function add(int $a, int $b): int
{
    return $a + $b;
}

add(1, 2);
```

Generates something like:

```cpp
php_add(php::toInt(1L), php::toInt(2L));
```

For object methods:

```php
$obj->run($arg);
```

If the class of `$obj` can be determined, and there is no method override or dynamic magic method risk, it generates:

```cpp
php_Class_run(obj, converted_arg);
```

Static state also includes:

- Native types such as `int`, `float`, `bool`;
- C++ template containers such as `std::array`, `std::vector`;
- Property access on objects whose class can be determined;
- Default argument and variadic parameter handling for functions that can be determined;
- Constant and class constant access that can be determined at compile time.

### 5.7 Dynamic-State Code Generation

The compiler enters dynamic state in the following cases:

- The function name comes from a variable;
- The method name comes from a variable;
- The actual type of an object cannot be determined;
- The class may have a `__call()` magic method;
- A method is overridden by a subclass, and static direct link may change dynamic dispatch semantics;
- A PHP built-in dynamic function needs to be called;
- Callbacks, closures, or placeholder expressions cannot be fully determined statically;
- Variables use general `mixed` or dynamic array semantics.

Dynamic state generates something like:

```cpp
php::call(function_ptr, arg_list);
```

or:

```cpp
object.call(method_ptr, arg_list);
```

Dynamic state preserves the PHP VM's function lookup, method dispatch, zval types, and dynamic callback semantics.

### 5.8 Static Direct Link vs. Dynamic Fallback Determination

The present invention does not require all code to be staticized; instead, a determination is made at each call site.

```text
Figure 3: Call-site state determination flow

Call expression
    |
    v
Is the function name or method name a literal?
    |
    +-- No --> dynamic-state call
    |
    +-- Yes
          |
          v
Can a Native target be found in the symbol table?
          |
          +-- No --> dynamic-state call
          |
          +-- Yes
                |
                v
Can the object class or function signature be determined?
                |
                +-- No --> dynamic-state call
                |
                +-- Yes
                      |
                      v
Are there risks such as magic methods, overrides, or dynamic callbacks?
                      |
                      +-- Yes --> dynamic-state call
                      |
                      +-- No --> static-state direct link
```

This mechanism means the language runtime is not a single state, but automatically selects the best state by code location.

### 5.9 Boundary Bridging Mechanism

Static and dynamic states interoperate through a boundary bridging mechanism.

#### 5.9.1 Dynamic Call to Static Function

When the PHP VM calls a compiled function, it enters a Zend wrapper function:

```text
Zend call entry point
    |
    v
Read call stack arguments
    |
    v
Convert arguments according to ArgInfo
    |
    v
Call the Native function
    |
    v
Write the return value back to return_value
```

#### 5.9.2 Static Code Calling Dynamic Function

When AOT code cannot determine the target, it generates a dynamic call:

```text
C++ static code
    |
    v
Construct a PHP dynamic argument list
    |
    v
Look up the function or method
    |
    v
Call the PHP VM dynamic path
    |
    v
Return a php::Var dynamic value
```

#### 5.9.3 Static Data Flowing into Dynamic State

For example, when a strongly typed container is assigned to a normal PHP variable, it is automatically converted to a PHP Array:

```php
$arr = $vector;
```

Generates:

```cpp
arr = php::toArray(vector);
```

#### 5.9.4 Dynamic Object Flowing into Static State

When an object is obtained from a dynamic array or function return value, and a subsequent static method call is needed, the compiler can be informed of the object's class name through a compile-time type declaration function or a type conversion function, thereby entering static object state.

### 5.10 Symbol Caching Mechanism

To reduce the runtime string lookup overhead of dynamic state, the present invention establishes integer IDs and cache arrays for symbols:

```cpp
zend_class_entry *class_map[N];
zend_function *func_map[M];
uint32_t property_map[K];
```

On first lookup, the Zend structure pointer is obtained by string name and cached; subsequent calls access it quickly through the integer ID.

Example:

```cpp
zend_function *get_func(int id, const php::Str &name) {
    if (func_map[id] == nullptr) {
        func_map[id] = php::getFunction(name);
    }
    return func_map[id];
}
```

This mechanism makes dynamic state still more efficient than the pure interpreted path.

### 5.11 Dual Runtime Forms

The present invention supports two runtime forms:

1. **Extension form**: the compilation result is a PHP extension that can be loaded into PHP-FPM or CLI and called by the PHP VM.
2. **Binary form**: the compilation result is an executable program, entered through the `main()` function and directly runnable.

These two forms share the same hybrid-state compilation model.

## 6. Key Points and Points to Protect

1. Propose a hybrid-state programming language model that allows static state and dynamic state to coexist in the same dynamic language program.
2. Automatically determine static or dynamic state in the compiler by function, method, variable, expression, and call site.
3. Generate C++ static direct link code for determinable targets, and automatically fall back to PHP dynamic call code for undeterminable targets.
4. Use ArgInfo as the parameter semantic model shared by static and dynamic states.
5. Generate Zend wrapper functions so the dynamic runtime can call AOT-generated Native functions.
6. Construct dynamic argument lists in AOT static code so static code can call PHP dynamic functions, dynamic methods, or callbacks.
7. Use a symbol caching mechanism to reduce the string lookup overhead of functions, classes, methods, and properties in dynamic-state runtime.
8. Support two runtime artifacts, extension form and binary form, both based on the same hybrid-state language model.
9. Support native types and C++ template containers in static state, and preserve zval, php::Var, PHP Array, and PHP object semantics in dynamic state.

## 7. Advantages over Existing Technologies

Compared with traditional statically compiled languages, the present invention preserves the dynamic call, dynamic type, dynamic object, and callback capabilities of dynamic languages, without requiring full program staticization.

Compared with traditional dynamically interpreted languages, the present invention can generate C++ direct calls, native types, and strongly typed containers in regions determinable at compile time, significantly reducing runtime lookup and dynamic type overhead.

Compared with JIT, the present invention generates stable compilation artifacts before deployment, does not rely on runtime hot-spot sampling and type feedback, and is suitable for production environments that require stable deployment, source code protection, and performance improvement.

Compared with handwritten extensions, the present invention automatically generates wrapper functions, argument conversion, symbol caching, and dynamic fallback paths through the compiler, reducing the cost of manually bridging between PHP and C++.

## 8. Alternative Embodiments

The present invention is not limited to PHP; it can also be applied to dynamic languages such as Python, JavaScript, Ruby, and Lua. The target static language is not limited to C++; it can also be Rust, Go, C, or LLVM intermediate representation.

The granularity of hybrid-state determination can be function-level, basic-block-level, statement-level, or expression-level. Symbol caching can also be implemented using hash tables, arrays, handle tables, or runtime inline caching.

## 9. Confidentiality Statement

This document involves the overall compilation model of Swoole-Compiler, the static direct link and dynamic fallback strategy, the ArgInfo boundary bridging mechanism, and the symbol caching solution. Before formal application, it is recommended to manage it as internal technical material.

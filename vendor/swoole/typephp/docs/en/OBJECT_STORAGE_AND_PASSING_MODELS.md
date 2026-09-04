# TypePHP's Three Object Storage and Passing Models

> Status: current architectural constraint. This document explains why TypePHP simultaneously keeps three object-style value models — Zend Object, PHPX Box, and
> Native Class Object — along with their respective ownership, passing methods, and boundaries.

## 1. Conclusion

TypePHP currently has three object storage and passing mechanisms:

1. Ordinary PHP/Zend Objects;
2. PHPX Box, including Std Containers and high-precision types;
3. `#[Native]` Native Class Objects.

These three are not historical residue of the same design, but separately solve three mutually conflicting problems:

- Zend Object preserves PHP's dynamic object semantics and ZendVM ecosystem compatibility;
- Box provides an opaque Zend value carrier for C++ types that cannot be fully written into PHP type declarations;
- Native Class Object provides statically-knowable business objects with a fixed layout close to C/C++, raw-pointer calls, and
  tracing GC.

None of these mechanisms can replace the other two without losing a core capability of the others. The current design explicitly accepts
the long-term coexistence of the three models and does not target a "unified object representation".

## 2. Overview

| Dimension | Zend Object | PHPX Box | Native Class Object |
| --- | --- | --- | --- |
| Typical value | Ordinary PHP class instance | Std Container, BigInt, BigFloat, Decimal | `#[Native] class` instance |
| Primary representation | `zend_object` / zval | `zend_resource` + `php::Box *` | C++ struct in Native Heap + raw pointer |
| Type identity | `zend_class_entry *` | Box C++ dynamic type, `type_info`/type ID | Compile-time Native class, dynamic type saved in descriptor |
| Lifecycle | Zend reference counting + Zend cycle GC | Zend resource reference counting calling the Box destructor | Wren-style precise, non-moving mark-sweep GC |
| Argument passing | `php::Object` / `php::Var`, copying the handle and adjusting RC | `php::Var` carrying the resource; hot paths extract the concrete C++ reference | Concrete `NativeClass *` passed by value, without adjusting RC |
| Property/method access | Zend handlers, dynamic lookup, or already-cached Native Call | The compiler generates operations based on the concrete Box type | Fixed-offset field access and definite `php_*` Native Call |
| Dynamic PHP interop | Complete | Limited interop as an opaque resource | Cannot enter the ZendVM value boundary |
| Cyclic graph handling | Zend GC can scan the Zend object graph | Zend GC does not scan the C++ object graph inside Box | The Native descriptor precisely traces the Native pointer graph |
| Core goal | PHP compatibility | Carrying C++ generic/extended values | Extreme static performance |

## 3. Ordinary PHP/Zend Object

### 3.1 Storage

Ordinary classes are registered with the ZendVM, and instances are represented by `zend_object`. TypePHP holds the corresponding zval through PHPX RAII types such as `php::Object`,
`php::Variant`/`php::Var`.

The object has Zend's class entry, property table, object handlers, and method metadata. Based on compile-time information,
TypePHP can optimize some accesses into definite Native Calls, but the object identity and lifecycle still belong to the ZendVM.

### 3.2 Passing and Lifecycle

PHP object assignment and argument passing copy the object handle, not the object entity, and follow Zend reference counting. Cyclic references in the object graph
are handled by Zend GC. Objects can naturally enter:

- PHP arrays and ordinary object properties;
- `mixed`/`object` variables;
- Closures, Generators, Fibers, and dynamic calls;
- Reflection, serialization, and extension functions;
- PHP code executed by the ZendVM.

### 3.3 Why It Must Be Kept

Only Zend Object can fully carry PHP's runtime object semantics. Replacing it with Box would lose the class entry, object
handlers, visibility, Reflection, and dynamic dispatch; replacing it with Native Object would lose ZendVM visibility,
and force all dynamic behavior to degrade to compile-time restrictions.

Ordinary PHP classes therefore always use Zend Object. The compiler can optimize calls, but cannot change its object model.

## 4. PHPX Box

### 4.1 Storage

`php::Box` is a C++ polymorphic base class managed by PHPX. The Box pointer is registered as a Zend resource and carried by
`php::Var`:

```text
zval(IS_RESOURCE)
    -> zend_resource
        -> php::Box*
            -> concrete C++ value
```

The Zend resource's destructor callback ultimately calls `Box::destroy()`. Box can therefore pass through ordinary zval/Variant
call boundaries while hiding the concrete C++ type that Zend cannot express.

Current main users include:

- `StdContainerBox<std::vector<T>>`;
- `StdContainerBox<std::array<T, N>>`;
- `StdContainerBox<map-like type>`;
- High-precision values such as BigInt, BigFloat, and Decimal.

### 4.2 The Std Container Hot Path

Std Container local variables have a two-layer representation:

```cpp
php::Var values = php::Var(new php::StdContainerBox<Container>(type_id));
auto &values_ref = values.toBox<php::StdContainerBox<Container>>()->container;
```

`php::Var` is responsible for the lifecycle and necessary boundary passing; the concrete container reference is used for subsequent element access, avoiding re-extracting the Box on every operation. The container's key/value/length and other generic information are jointly saved by the compiler and the concrete C++ template type.

When a Std Container is passed across TypePHP functions, the PHP function signature cannot express the following C++ type information:

```text
std::vector<int>
std::vector<string>
std::map<string, App\User>
```

A PHP parameter can at most declare a non-generic class name or pseudo-type; it cannot simultaneously carry the container kind, key type, value
type, array dimensions, and length. The current approach uses `UnsafePtr`/`std::unsafe_cast()` with compiler type ID checking,
rather than generating every combination as a PHP class.

In theory, parameter and return value annotations could be added to describe generics, but this would require maintaining extra metadata at every declaration, call, return, property, and propagation point,
and PHP Reflection still cannot fully express it. This standalone generic ABI is not being introduced for now.

### 4.3 Box Boundaries

Box is an opaque value carrier, not a general-purpose object system:

- Zend GC only sees the resource and does not scan C++ references held inside Box;
- Box does not provide PHP class method tables, property tables, inheritance, or Reflection;
- The concrete type is recovered through `dynamic_cast`, type ID, or dedicated helpers;
- Box should not be used to build arbitrary cyclic object graphs that require bidirectional Zend/Box tracing;
- The usable locations and escape paths of Std Container continue to be restricted by the compiler.

Box is suitable for numeric values, containers, and other extension values with clear boundaries. It is not suitable for replacing Native
business objects with arbitrary field reference relationships.

### 4.4 Why It Must Be Kept

The generic types of Std Container cannot be fully expressed by PHP function parameters; high-precision values in turn need to participate in existing operations and calls as
`php::Var`. Box provides all of the following:

- A stable carrier that can be placed into a zval;
- Runtime recovery of the concrete C++ type;
- Automatic destruction within the Zend request lifecycle;
- A lightweight implementation that does not register a PHP class for each template instantiation.

Zend Object cannot directly express C++ template instances; Native raw pointers cannot safely cross `php::Var` and dynamic
ZendVM boundaries. Therefore Box still has a reason to exist independently.

## 5. Native Class Object

### 5.1 Storage

`#[Native]` classes do not register a Zend class, do not generate Zend object handlers, and have no zval representation. Each
object is a fixed-layout C++ struct in the Native Heap; TypePHP local variables, parameters, return values, and fields hold
concrete Native pointers:

```cpp
php_app__point *point;
```

Methods continue to use TypePHP's free-function ABI:

```cpp
php::Float php_app__point__length(php_app__point &this_);
```

Ordinary calls only pass a pointer value. No zval is created, no resource is registered, no reference counting is performed, and nothing goes through
`zend_call_function()`.

### 5.2 Lifecycle

Native Objects use an independent Wren-style precise, non-moving, stop-the-world mark-sweep GC in PHPX:

- Native local variables, parameters, return temporaries, and global/static slots enter a precise root frame;
- The Native object descriptor is responsible for tracing Native pointer fields;
- When a Std Container saves a Native pointer, a dedicated container root frame is registered;
- Cyclic references are collected by the tracing GC, without relying on reference counts dropping to zero;
- The 16-byte GC header saves the minimal state required by the collector;
- `__destruct()` is executed by Native finalization, not by the Zend object destructor.

Native pointer assignment does not increase the reference count and does not need a write barrier. Fixed fields are accessed directly by C++ offset.

### 5.3 Passing Boundaries

Native Object parameters and return values must explicitly declare a concrete Native class, or a supported nullable concrete type:

```php
function distance(Point $left, Point $right): float;
function findPoint(): ?Point;
```

This lets the compiler generate the signature directly as `Point *`. Native Objects do not support:

- Passing to PHP/ZendVM functions, Closures, or dynamic callables;
- Saving into PHP arrays, ordinary Zend Object properties, or `mixed`;
- Automatic conversion to `php::Object`, `php::Var`, or Interface value;
- Recovering the type through the runtime class name;
- Using the generic PHPX `toObject()` helper to complete boxing or unboxing. Native Classes can declare their own
  `toObject(): object` method; keyword calls resolve directly to that Native Call and do not provide a generic bridge.

When entering the PHP API, the user must explicitly convert the data, for example first calling Native `toArray(): array`, then passing
the result to `json_encode()`. This conversion produces a data copy and does not preserve the Native object identity.

### 5.4 Why It Must Be Kept

The goal of Native Class is hot-path performance close to C/C++:

- A one-machine-word object handle;
- Fixed field layout;
- No Zend RC increment/decrement;
- No `zend_object` or `zend_resource` carrier allocation;
- Definite-symbol Native Calls;
- Inlinable and devirtualizable by the C++ compiler.

If Box were used instead, each Native Object would need resource/zval wrapping, RC management, and concrete type recovery, and
Zend GC cannot scan the Native pointer graph inside Box; this both reduces performance and cannot correctly replace Native tracing
GC. If a custom `zend_object` were used instead, although it could connect to Zend GC and dynamic boundaries, the object header, RC,
handlers, and access paths would all change the performance positioning of Native Class.

Therefore Native Class continues to use an independent Native Heap and a raw-pointer ABI.

## 6. Why They Cannot Be Unified

### 6.1 They Cannot All Become Zend Object

This would unify dynamic semantics, but it would make Std Container generic instances and Native Class both bear the Zend object
header, RC, handlers, class registration, and dynamic access costs. Native Class would no longer be close to C/C++,
and Std Container would need a runtime class system designed for a large number of template combinations.

### 6.2 They Cannot All Become Box

Box can carry C++ values through zval, but Zend GC does not understand the object graph inside Box. It cannot replace the dynamic metadata of ordinary PHP
Object, nor can it provide a raw-pointer hot path while retaining Native cycle collection capability.

### 6.3 They Cannot All Become Native Pointers

Native pointers require complete static typing. Ordinary PHP objects need Reflection, dynamic properties, dynamic callables,
and Zend extension interop; the complete generic types of Std Container cannot be written into PHP parameter signatures. Turning these values into
raw pointers would produce type erasure that cannot be proven safe statically, and could lead to incorrect pointer conversion and crashes.

### 6.4 No Automatic Bridging

There is no implicit object identity conversion among the three models. Automatic boxing/unboxing would hide allocation, copying, RC, and GC root
changes, and would also make compiler boundaries no longer reliable.

Allowed conversions must have clear semantics:

- Std Container to PHP array: copies container data;
- Entity methods of Native Object such as `toArray()`: defined by the user and explicitly copy data;
- Explicit scalar conversion of high-precision types: produces new PHP scalar values;
- Ordinary Zend Object does not automatically become a Native Object.

## 7. Compiler Implementation Constraints

Future changes must preserve the following invariants:

1. Determine the object model from the static type first, then choose the code generation path; never guess one of the three at runtime.
2. Native Objects must not be wrapped into `php::Var` or passed into the ZendVM due to a generic fallback.
3. Box concrete type recovery must validate the resource type and the concrete C++ type / type ID.
4. Zend Object optimization must not change Zend object identity, lifecycle, or dynamic visibility.
5. The argument ABIs of the three models must not be mixed: `php::Object`, Box-bearing `php::Var`, and `NativeClass *`
   respectively represent different ownership and type constraints.
6. Cross-model conversions must be explicit, and the allocation or copying cost must be reflected in documentation and generated code.
7. If a new feature requires sacrificing all Native Class hot paths to gain a small amount of dynamic compatibility, it should be prohibited at compile time first.
8. If a new C++ generic type needs to cross the Zend value boundary, Box should be evaluated first, rather than widening the dynamic
   boundary of Native Object.
9. If a value needs complete PHP object semantics, Zend Object should be used, and Box must not be treated as a simplified PHP class.

## 8. Code Locations

Main implementation entry points:

```text
Ordinary Zend Object
  compiler/src/Parser/*
  phpx/include/phpx.h          Object / Variant / Zend API wrappers

PHPX Box and Std Container
  phpx/include/phpx.h          Box / StdContainerBox<T>
  phpx/src/core/base.cc        Box resource registration and destructor
  compiler/src/Parser/StdContainerTrait.php

Native Class Object
  compiler/src/NativeClass/
  compiler/src/Transform/NativeClassAttributeLowering.php
  phpx/include/phpx_native_gc.h
  phpx/src/core/native_gc.cc
  phpx/thirdparty/wren-gc/
```

Detailed rules are in [STD_CONTAINERS.md](STD_CONTAINERS.md),
[NATIVE_CLASS_OBJECT.md](NATIVE_CLASS_OBJECT.md), and
[NATIVE_CLASS_IMPLEMENTATION_AUDIT.md](NATIVE_CLASS_IMPLEMENTATION_AUDIT.md).

## 9. Current Decision

The following refactorings are not being implemented at the current stage:

- Not removing Wren GC;
- Not changing Native Object to Box or a custom Zend Object;
- Not adding a generic `toObject()` dynamic recovery mechanism to Native Object; the Native Class custom
  `toObject(): object` remains an ordinary definite Native Call;
- Not changing Std Container to a raw-pointer ABI whose type cannot be expressed across signatures;
- Not attempting to cover the three object models with a single unified wrapper.

These boundaries will be re-evaluated in the future only when the PHP language layer can stably express generic parameters, or when a new ABI
that has passed benchmark and complete GC correctness validation emerges. Until then, the coexistence of the three mechanisms is an intentional architectural choice.

# Native Class Object Design and Implementation

> Status: the core design is implemented and is undergoing final end-to-end validation. Fixed layout, Native Call, precise tracing GC,
> construction/cloning/destruction, Trait, Getter/Setter, Property Hook, abstract classes, single inheritance, limited virtual dispatch,
> compile-time Interface contracts, and project-level global slot pre-discovery have all landed. Item-by-item implementation evidence is in
> [NATIVE_CLASS_IMPLEMENTATION_AUDIT.md](NATIVE_CLASS_IMPLEMENTATION_AUDIT.md).
> For the architectural reasons why Native Class coexists with ordinary Zend Object and PHPX Box, see
> [OBJECT_STORAGE_AND_PASSING_MODELS.md](OBJECT_STORAGE_AND_PASSING_MODELS.md).

## 1. Background

TypePHP's ordinary classes are registered with the ZendVM and generate `zend_class_entry`, object handlers, property metadata, and Zend method wrapper functions. This makes ordinary classes compatible with dynamic PHP, Reflection, dynamic invocation, serialization, and so on, but it also brings a fixed runtime cost.

Native Class Object targets a small number of scenarios that demand extreme performance. It is only usable in statically compiled TypePHP code, is not registered with the ZendVM, and is generated directly as a data structure close to a C++ `struct`.

This capability is not an automatic optimization mode for ordinary classes, nor does it replace the existing object model. Developers must explicitly opt into Native Class and accept the corresponding functional restrictions.

## 2. Design Goals

1. Properties have a fixed memory layout and can be accessed directly as C++ fields.
2. Methods continue to compile into the existing `php_*` C++ free functions, whose first parameter is the concrete Native struct's `this_`.
3. No `zend_class_entry`, Zend object handlers, or Zend method wrapper functions are generated.
4. No `zend_call_function()` is performed, and no ZendVM dynamic dispatch is used.
5. The object handle occupies only one machine word; no `std::shared_ptr` or atomic reference counting is used.
6. Ordinary parameter passing does not increase the reference count and does not copy the object entity.
7. Static features such as Getter, Setter, and Property Hook can be inlined by the C++ compiler.
8. The Native Class compiler implementation lives in a separate directory, to avoid scattering special rules throughout the existing compiler code.
9. Do not sacrifice the performance and maintainability of the main path for compatibility with a small number of ZendVM-dependent features.
10. All properties must have explicitly declared types; inferring property layout from first assignment is forbidden.
11. Besides `bool`, `int`, and `float`, properties may hold legal and supported PHP/TypePHP types such as string, array, object, Stream, and mixed.

## 3. Non-Goals

The initial Native Class Object does not aim for the following capabilities:

- Interoperating with dynamic PHP code.
- Access within `eval()` or dynamic `include`.
- Zend Reflection metadata.
- Dynamic properties, variable method names, and dynamic class-name instantiation.
- Mutual inheritance with ordinary ZendVM classes.
- Automatic boxing into `php::Object` or `php::Variant`.
- Full compatibility with PHP object destruction timing and garbage collection behavior.
- Automatic degradation to ZendVM Object when static proof of safety is impossible.

`new (expression)()` is always PHP's dynamic class-name syntax. The Native Class branch does not additionally evaluate, recognize, or add dedicated diagnostics for constructs such as `new (NativeClass::class)()`; such dynamic creation behavior is outside the scope of Native Class. A Native Object can only be created through a statically resolvable `new NativeClass(...)` in source code.

## 4. Explicit Declaration

Use a dedicated built-in attribute:

```php
#[Native]
class Point
{
    public float $x;
    public float $y;

    public function length(): float
    {
        return sqrt($this->x ** 2 + $this->y ** 2);
    }
}
```

Native Class supports single inheritance, but it can only inherit from another Native Class. Mutual inheritance between ordinary ZendVM classes and Native Classes is forbidden. `final class` and `final` methods continue to take effect and give the compiler stronger devirtualization conditions.

Every concrete class in the inheritance chain must explicitly declare `#[Native]`; the object model is not implicitly switched merely because the parent class is a Native Class:

```php
#[Native]
class Base {}

#[Native]
class Child extends Base {}
```

`#[Native]` is the formal explicit declaration for Native Class Object. Ordinary classes without this attribute continue into the existing ZendVM Object compilation flow.
This attribute may only be used on a named `class`; Interface, Trait, and Enum cannot be declared as Native. A Trait may still be injected into a Native Class during the convert phase, but the Trait itself does not form a Native runtime type.

## 5. Generated C++ Structure

The above code is approximately generated as:

```cpp
struct php_app__point {
    php::Float x;
    php::Float y;
};

php::Float php_app__point__length(php_app__point &this_);
```

A Native Class with no parent, no subclasses, and no override methods contains:

- no virtual function table.
- no Native Object base class.
- no runtime class id.
- no in-object reference count.
- no Zend object header.
- no property name table or method name table.

When it does not participate in override dispatch, the Native struct contains only fields and generates no C++ member functions. Instance methods in PHP source continue to use TypePHP's current functional ABI:

```cpp
php::Float php_app__point__length(php_app__point &this_) {
    return php::fn::sqrt(this_.x * this_.x + this_.y * this_.y);
}
```

Calls are generated as:

```cpp
php_app__point__length(*point);
```

The caller performs one null check before dereferencing, and the `php_*` method function may assume `this_` is valid. Ordinary instance methods uniformly use a writable `native_struct &this_`; the ABI does not differ depending on whether the method body mutates properties.

This rule yields the following benefits:

- Fully reuses the current `php_*` method symbol naming and Native Call mechanism.
- Argument evaluation order, default arguments, type checks, and exception boundaries continue through the existing function generation logic.
- Native structs that do not participate in inheritance dispatch contain no vtable or member function declarations.
- All Native structs can be forward-declared and have their fields defined first, then method functions generated uniformly.
- Getter, Setter, Property Hook, and magic methods can be uniformly lowered into the same kind of free function.

Native methods generate no Zend method wrapper and are not registered with the ZendVM. The `php_*` symbols of ordinary PHP classes and Native Classes must still go through the existing compiled symbol conflict detection.

### 5.1 Inheritance and Virtual Dispatch

Native Class uses C++ public single inheritance to preserve the base-class subobject layout. The implementation body of a PHP method is still a `php_*` free function, not a complex C++ member-function model.

Native abstract classes and abstract methods are also fully implemented at compile time. Abstract methods generate pure virtual thunks in the C++ struct; the concrete Native subclass's implementation continues to call the corresponding `php_*` free function. Calling through an abstract base-class typed parameter performs a single C++ virtual dispatch, without registering a Zend class or method.

When whole-program analysis finds public/protected instance methods with the same name in the inheritance chain, a distinct internal virtual slot is generated for each declaration level. The subclass implementation overrides the ancestor slots at the same time, and generates adapters with each slot's own parameter and return signatures, which then forward to the subclass's `php_*` implementation. This preserves PHP's permitted parameter contravariance and return covariance without coercing incompatible C++ function pointers or references into the same vtable slot:

```cpp
struct php_app__base;
php::Str php_app__base__name(php_app__base &this_);

struct php_app__base {
    virtual php::Str __native_dispatch_base_name() {
        return php_app__base__name(*this);
    }

    ~php_app__base() noexcept = default;
};

struct php_app__child;
php::Str php_app__child__name(php_app__child &this_);

struct php_app__child : public php_app__base {
    php::Str __native_dispatch_base_name() override {
        return php_app__child__name(*this);
    }
};
```

Call rules:

- When the receiver's static type is determined to be the final implementation class, the corresponding `php_*` function is called directly.
- When the receiver is a base-class pointer that may point to a subclass, and the method family has an override, dispatch through the internal virtual thunk.
- Methods that are not overridden continue to call the `php_*` function directly, without introducing a virtual call.
- private, static, constructor, and destructor do not join the virtual method family.
- PHP does not support overloading by parameter signature; Native Class likewise does not add C++ overload semantics.
- Overrides must pass the existing PHP method compatibility rules and Interface checks.
- Ordinary value parameters support PHP's parameter contravariance and return covariance through per-declaration adapters. Native Object parameters forbid `&`; typed pointers passed by value already share object identity, while `&` would additionally expose the ability to rebind the caller's pointer slot, which is not part of the Native Object ABI.
- C++ default arguments are bound by the receiver's static type and cannot be placed directly on a virtual declaration. The compiler generates one overloaded virtual slot per available positional-argument count; the dynamically selected adapter then calls its own `php_*` implementation, so the dynamic implementation class's default values are used without requiring a runtime presence mask.
- Named-argument calls on Native virtual methods may omit trailing consecutive optional arguments; leaving a named-argument gap before subsequent arguments is rejected at compile time. This rare shape cannot be expressed by positional C++ overloads, and supporting it would require a presence mask on every virtual call. Non-virtual Native Calls retain ordinary named-argument behavior.

This provides the limited single dispatch polymorphism necessary for inheritance, but does not support variable method names, runtime overload resolution, `__call()`, or ZendVM dynamic invocation. The C++ compiler can still devirtualize `final` classes, `final` methods, and known exact types.

The GC header's `NativeTypeDescriptor` always records the most-derived dynamic type. Even when an object survives through a base-class pointer, trace, finalize, and destroy must use the dynamic descriptor, not merely the variable's static type.

### 5.2 Inheritance Object Layout

The inheritance hierarchy must satisfy the following layout rules:

- Parent fields live in the C++ base subobject; subclasses only append their own new fields.
- public/protected properties with the same name must pass the existing PHP property compatibility checks; when they denote the same inherited property, they reuse the parent's slot and must not be stored again in the subclass.
- A parent private property and a subclass property with the same name are two different slots. Generated field names must include the declaring class or a stable slot id, to avoid mis-access caused by C++ name hiding.
- When accessing an inherited property, the code generator computes the fixed field path from the property definition's declaring class, without performing name lookup.
- The most-derived type's `trace()` must cover Native pointer fields in itself and in all base subobjects.
- constructors do not participate in virtual dispatch. As in PHP, whether a subclass calls `parent::__construct()` is explicitly decided by source code and directly generates a determined `php_*` call.
- `final` classes may not be inherited; `final` methods may not be overridden.

The inheritance graph must be topologically sorted before generating structs. Native Class supports only a single class parent; multiple Interfaces do not participate in object layout.

## 6. Property Types and Storage

Every physical property of a Native Class must have an explicit type:

```php
#[Native]
final class RequestContext
{
    public bool $ready = false;
    public int $status = 0;
    public float $elapsed = 0.0;
    public string $method = '';
    public array $headers = [];
    public object $request;
    public Stream $body;
    public mixed $metadata = null;

    public function __construct(object $request, Stream $body)
    {
        $this->request = $request;
        $this->body = $body;
    }
}
```

Properties without a declared type are forbidden:

```php
#[Native]
final class InvalidContext
{
    public $value; // FatalError
}
```

Type declarations are used to determine the C++ field layout at compile time. The suggested mapping for different types is:

| TypePHP property type | C++ field representation | Notes |
|---|---|---|
| `bool` | `php::Bool` | Native value field |
| `int` | `php::Int` | Native value field |
| `float` | `php::Float` | Native value field |
| `string` | `php::Str` | The PHPX RAII string type currently used by TypePHP |
| `array` | `php::Array` | PHPX RAII array, preserving PHP COW semantics |
| Concrete Zend class | `php::Object` | Stores a Zend Object and validates the class at the assignment entry |
| `object` | `php::Object` | Stores any Zend Object |
| Native Class | `native_struct *` | Stores a raw pointer within the same Native Heap |
| `Stream` | `php::Var` | Stores the stream resource zval and performs an exact type check at the assignment entry |
| `mixed` / `any` | `php::Var` | Stores any PHP zval; only `any` explicitly allows exposing references |
| union/intersection/nullable without Native Class | `php::Var` | Uses the same type descriptor and runtime write checks as ordinary class properties |
| `?NativeClass` | `native_struct *` | `nullptr` denotes null; unions/intersections containing Native Class are not supported |
| BigInt/BigFloat/Decimal | `php::Var` | Stores PHPX boxed high-precision values; field addressing is still fixed offset, and arithmetic reuses the existing Variant ABI |

`string`, `array`, Zend Object, Stream, and mixed fields are still located directly at fixed offsets in the C++ `struct`. The underlying zval or zend object they hold is managed by the PHPX RAII type, but property reads do not require a property hash table, object handler, or ZendVM dispatch.

PHP itself does not allow `resource` as a property type; stream resources in TypePHP should be declared with the existing `Stream` pseudo-type. Types that PHP itself forbids in property declarations, such as `void`, `never`, and `callable`, are likewise forbidden in Native Class.

The following types are explicitly forbidden as Native Class property types:

- `Box`
- `std\array`
- `std\vector`
- `std\map`
- `std\ordered_map`
- Other Std Container types added later

These types have independent generic layouts and reference or ownership semantics; embedding them in Native Class would significantly expand the first version's type-combination and lifetime-analysis scope. Developers can use ordinary PHP `array` fields; Native Objects still cannot be stored in a PHP array, because Native Object has no `zval` representation.

For example:

```cpp
struct php_app__requestcontext final {
    php::Bool ready;
    php::Int status;
    php::Float elapsed;
    php::Str method;
    php::Array headers;
    php::Object request;
    php::Var body;
    php::Var metadata;
};
```

Allowing fields to hold ZendVM values does not mean the Native Class Object itself enters the ZendVM. The ZendVM can manage the String, Array, Object, or resource that a field points to, but it is unaware of the existence of the enclosing Native Class.

### 6.1 Property References

Whether a Native property may be taken by reference must be decided entirely at compile time from declaration metadata, without generating runtime type branches:

- Only `any` properties allow `$ref =& $object->property`; this is an explicit choice to allow Zend dynamic code to replace the slot value.
- `mixed` also uses `php::Var` storage but still rejects taking references; in Native Class all declared types except `any` must maintain compile-time type constraints.
- `bool`, `int`, `float`, and other fixed-layout fields cannot represent PHP references and are rejected at compile time.
- `string`, `array`, `object`, Stream, and high-precision types have PHPX wrapper layers but are still fixed declared types; reference writes would bypass type constraints, so they are rejected at compile time.
- nullable, union, and intersection constrained `php::Var` fields also reject references; they cannot be allowed merely because the underlying storage is also `php::Var`.
- Properties with Property Hook have no physical slot to expose, and always reject references.

This rule only allows references to unconstrained field values, not references to the Native Object pointer variable itself. Ordinary assignment between Native Object variables already shares object identity.

### 6.2 Initialization State

Native Class does not preserve the `UNDEF` state of PHP typed properties, nor does it add extra state bits to fields. At object creation, each field without an explicit default value uses the type's zero value directly:

- `bool` is `false`; `int`, `float` are `0`.
- `string` is an empty string, `array` is an empty array.
- `mixed`/`php::Var` is `null`.
- Zend Object and Native Class pointers are `null`.
- `Stream` is the empty resource state.
- When a property has an explicit default value, it is applied after zero-value construction.
- The first version forbids `unset()` on Native Class properties, to avoid reintroducing the runtime UNDEF state.

Therefore properties without default values can also be read immediately, but the read yields the deterministic zero values above, not PHP's "uninitialized typed property" exception. All properties must still declare a type.

Virtual properties of Property Hook have no physical field, but the Hook declaration must still include a type.

### 6.3 Assignment Checks

Definitely-resolved assignments are checked at compile time. Values from `mixed`, dynamic PHP return values, or other statically indeterminable sources undergo one runtime type check before being written to the field. After the check, the value is written directly to the corresponding field, without going through the Zend property handler.

This means supporting arbitrary PHP field types does not change the property-addressing performance of Native Class; the extra cost only appears at assignment boundaries where static type safety cannot be proven.

## 7. Object Variables and Identity Semantics

To preserve PHP object identity and aliasing semantics, TypePHP variables hold the raw object pointer:

```cpp
php_app__point *point;
```

Assignment only copies the pointer:

```php
$a = new Point();
$b = $a;
$b->x = 10;
```

The generated semantics are approximately:

```cpp
auto *a = native_heap.make<php_app__point>();
auto *b = a;
b->x = 10;
```

Therefore `$a` and `$b` still point to the same object; adopting a C++ `struct` does not turn it into value copy.

Native Class Object does not use `std::shared_ptr`. The control block, atomic reference counting, and cycle problem of `std::shared_ptr` are inconsistent with the extreme performance goals of this feature.

Strict comparison of Native Objects uses pointer identity: `===`/`!==` determine whether two slots point to the same Native object, and comparison with `null` is also supported. Strict comparison with any Zend scalar or Zend Object is always `false`; the raw pointer is not implicitly converted to `bool`. `match` conditions use the same pointer identity rule.
PHP's `==`/`!=` recursively compares Zend Object properties; Native Object has no Zend object handler, and the object graph may contain cycles, so implicit field-value comparison is not provided.
Loose comparison, ordering comparison, and arithmetic/bitwise operations report errors directly at compile time. When value-equality semantics are needed, declare an ordinary Native method with explicit fields and cycle-handling rules.

Unary arithmetic/bitwise operations, `++`/`--`, compound arithmetic assignment, and `switch` also depend on PHP's numeric or loose comparison semantics, so they are forbidden for Native Objects. This check must happen before C++ generation, to avoid raw pointers accidentally entering legal but dangerous C++ pointer arithmetic.

`isset($native)` and `empty($native)` directly check whether the raw pointer is `nullptr`. Named Native property chains use short-circuit lambdas to check intermediate pointers level by level, and never pass the pointer into `php::Variant`, so `isset($node->next->next)` returns `false` when an intermediate slot is null, rather than triggering a null-object call.

### 7.1 Native Class Circular References

Two or more Native Classes can reference each other in their property types:

```php
#[Native]
final class A
{
    public ?B $b = null;
}

#[Native]
final class B
{
    public A $a;

    public function __construct(A $a)
    {
        $this->a = $a;
    }
}
```

All Native structs must be forward-declared before generating C++:

```cpp
struct php_a;
struct php_b;

struct php_a final {
    php_b *b;
};

struct php_b final {
    php_a *a;
};
```

Native Class properties always hold pointers and never embed another Native struct by value, so there is no infinitely recursive object size and no requirement to fully define structs in dependency order.

The compiler should compute strongly connected components (SCC) of the Native Class type dependency graph:

- SCC is only used to arrange the generation order of forward declarations, full definitions, and method implementations.
- Circular type dependencies are not themselves errors.
- Generating Native Class properties as by-value struct fields is forbidden.
- After all Native struct full definitions are complete, method bodies that depend on the complete type are generated.

Native Heap tracing GC can traverse all Native pointer fields, so A and B pointing to each other do not form a reference-counting cycle and do not produce a permanent leak. Raw pointer fields have no destruction action themselves.

The zero value of a Native Object property is `nullptr`, including Native Class properties declared non-nullable in source. The non-nullable constraint only applies to subsequent explicit assignments and does not introduce the UNDEF state of PHP typed properties. Therefore a circular object graph can be constructed separately first, then establish the bidirectional relationship:

```php
$a = new A();
$b = new B($a);
$a->b = $b;
```

No construction-dependency SCC or "two-phase publication" mechanism is needed; the type SCC is only used for C++ forward declaration and generation order.

## 8. Memory and Lifetime

Request Arena can only serve as an allocator and a Request Shutdown safety net, not as the sole lifetime mechanism. For long-running CLI, HTTP Server, or a single extremely long request, if objects can only be freed at Request Shutdown, memory keeps growing.

Native Class Object should use an independent, non-moving, precise tracing GC. This document calls that runtime the Native Heap.

### 8.1 Native Heap

The Wren-derived implementation allocates a contiguous block of memory for each object and places a hidden GC header before the struct. The header is fixed at two machine words, 16 bytes on a 64-bit platform:

```cpp
struct NativeGcHeader {
    // Low 3 bits are reused as marked/finalized/allocated-during-collection flags.
    uintptr_t nextAndFlags;
    const NativeTypeDescriptor *type;
};

// Memory layout: [NativeGcHeader][php_app__point]
auto *point = native_heap.make<php_app__point>();
```

The GC header is not part of the generated C++ struct and does not change property offsets. The user-visible object variable is still just a `native_struct *`. Object size, alignment, and trace/finalize/destroy callbacks are kept in a per-type static `NativeTypeDescriptor`, not repeated per instance.

The current allocator uses independent non-moving allocation; Arena/chunk/free-list can be a later allocator optimization, but must not change object address stability, header layout, precise tracing, or finalization semantics.

The Native Heap has the following characteristics:

- non-moving: object addresses never change from creation to reclamation.
- precise: only Native pointers explicitly registered by the compiler are scanned; memory is not conservatively scanned.
- stop-the-world: the first version only performs full collection at safe points of the current TypePHP request/thread.
- non-atomic: Native Objects do not cross threads; GC metadata does not use atomic operations.
- no per-assignment retain/release: ordinary pointer assignment does not modify reference counts.

Request Shutdown destroys all remaining objects in the Native Heap, but unreachable objects are also periodically reclaimed during normal operation.

### 8.2 Type Descriptor and Object Graph Traversal

Each Native struct generates a static type descriptor:

```cpp
struct NativeTypeDescriptor {
    void (*trace)(void *object, NativeMarkVisitor &visitor);
    void (*destroy)(void *object);
    size_t size;
    size_t alignment;
};
```

`trace()` only visits Native Class pointer fields:

```cpp
static void trace_a(void *ptr, NativeMarkVisitor &visitor) {
    auto *object = static_cast<php_a *>(ptr);
    visitor.mark(object->b);
}
```

`php::Str`, `php::Array`, `php::Object`, `php::Var`, and Stream fields are managed by Zend reference counting, but they cannot hold Native Objects in reverse, so the Native GC does not need to scan them deeply.

Forbidding Native Objects from entering PHP Array, Box, and Zend Object is an important condition for keeping the Native object graph closed and precisely traversable. Local Std Containers are an exception: when the element type is explicitly written as a Native Class, the container directly holds typed Native pointers and is traversed by a separate container Root Frame during the GC marking phase. This Root Frame tracks the container rather than the element addresses, so vector/map reallocation does not create dangling roots.

### 8.3 Root Management

The GC must know which Native Objects are still referenced by TypePHP code. The compiler generates lightweight shadow root frames for local variables that may survive across a GC safe point:

```cpp
struct FunctionNativeRoots {
    NativeRootFrame frame;
    php_a *a;
    php_b *b;
};
```

At function entry the frame is linked into the current Native Heap; at exit it is automatically unlinked via C++ RAII. The frame must also be correctly removed during C++ exception unwinding.

To reduce overhead:

- Functions that contain no Native Object create no root frame.
- Leaf methods that only borrow the caller's object and do not trigger Native allocation create no root frame.
- Only variables that may survive across Native allocation or an explicit GC safe point are registered.
- The receiver of an ordinary method is kept alive by the caller's root or the object graph, and is not registered again.
- A temporary object that survives across a call that may trigger GC must first be written to a root slot.
- Native Classes may be stored in TypePHP globals and static locals. These slots are not registered in the Zend symbol table; instead independent Native pointer slots are generated.
- `global $slot` and `$GLOBALS['slot']` use the same Native pointer slot; the key of `$GLOBALS` may also be a global constant, class constant, or constant expression that evaluates to a string at compile time.
- `$GLOBALS[$dynamicKey]` still goes through the Zend HashTable per PHP syntax. Since Native Object has no zval representation, dynamic keys cannot be used to read or write Native globals.
- In ZTS builds, global/static pointer slots and static initialization state use `THREAD_LOCAL`; Native objects are not shared across threads.
- RINIT registers these slots as request roots; RSHUTDOWN clears the slots and initialization state, after which the Native Heap uniformly performs finalization and reclamation.

This is more suitable than reference counting on every object assignment for heavy property writes and loop computation.

### 8.4 GC Trigger Points

The first version runs GC only at determined safe points:

- Native Heap allocation exceeds the adaptive threshold.
- Request Shutdown forcibly cleans up all objects.

The Native GC does not expose a language-level explicit collection function. The internal collection entry in PHPX is only for the runtime threshold policy and low-level tests, and is not registered as a TypePHP/PHP API.

Ordinary field reads, field writes, and method calls do not trigger GC themselves. GC should not run asynchronously, nor occur between arbitrary C++ instructions.

The first version adopts a full mark-sweep:

1. Start marking from shadow root frames and global/static roots.
2. Traverse Native pointer fields through each type's `trace()`.
3. Use an explicit worklist, to avoid C++ stack overflow from recursive traversal.
4. Sweep the Native Heap and reclaim unmarked objects.
5. Preserve surviving object addresses and clear the mark state.
6. Adjust the next GC threshold based on this cycle's survival ratio.

When A/B reference each other but are unreachable from any root, both are reclaimed in the same sweep.

### 8.5 Destruction and GC Reentry

An unreachable object may contain `php::Array`, `php::Object`, or `php::Var`. When these fields are destroyed, a Zend Object destructor may execute user code or even allocate Native Objects again. Therefore the sweep cannot modify the GC linked list while directly executing all C++ destructors.

The first version must adopt a finalize/destroy separated reclamation flow:

1. Mark and remove all unreachable objects from the active object set, setting their state to `finalizing`.
2. After completing the GC internal data-structure update, invoke the user `__destruct()` finalizer outside the GC critical section.
3. New Native Objects produced during the finalizing phase join a new active list.
4. Recursive entry into GC is forbidden during finalizing; new collection requests are recorded as pending and executed after this cycle completes.
5. Native Object itself cannot enter the ZendVM, but user `__destruct()` can save `$this` to another Native root, resurrecting the object during finalization; the object's finalized state guarantees the user destructor executes at most once.
6. After the finalizer completes, rescan roots; user `__destruct()` is separated from the actual C++ field destruction, and only non-resurrected objects undergo C++ destroy and storage release.

### 8.6 Later Stack Allocation Optimization

When escape analysis can prove an object never leaves the current function, it can be stack-allocated directly:

```cpp
php_app__point point_storage;
auto *point = &point_storage;
```

Stack allocation is a later optimization and should not be a first-version correctness dependency. Return values, objects written into another Native Object property, or objects passed to unknown functions are all considered to escape.

A stack Native Object itself does not join the Native Heap, but if it contains Native pointer fields, the GC root descriptor must be able to traverse that stack object's outgoing references.

### 8.7 Request Shutdown

Request Shutdown is the final safety net, not the normal object reclamation timing. It must stop GC, remove root frames, and destroy all remaining objects in the Native Heap before the PHP memory pool is destroyed.

It cannot simply wait for the PHP memory pool to be released uniformly; otherwise the resources held by `php::Str`, `php::Array`, `php::Object`, `php::Var`, and other fields cannot be correctly destructed.

### 8.8 Open-Source GC Implementation References

The Native Heap should not invent an unverified GC model from scratch, but also should not embed a complete language VM directly. Mature algorithms and test methods should be reused, and a small dedicated GC should be implemented for TypePHP's closed Native object graph.

| Project/Algorithm | Characteristics | Suitability for TypePHP |
|---|---|---|
| Wren GC | Small, non-moving, precise mark-sweep, explicit gray worklist, adaptive heap threshold | Best suited as the first-version upstream; no object-assignment barrier, easy to verify and port |
| BDWGC | A long-standing C/C++ conservative collector, STW by default, with incremental/parallel capability on some platforms | Highest maturity and integration convenience, but cannot guarantee reclaiming all unreachable objects |
| Oilpan/cppgc | C++ tracing GC used by Chrome/Blink, precise heap scanning, conservative native-stack scanning, supports concurrent/incremental processing | Mature for large C++ projects, but requires `GarbageCollected<T>`, `Member<T>`, Trace, and write barriers; integration is too heavy |
| MMTk | Rust GC framework with multiple plans (MarkSweep, Immix, generational) and multi-language VM bindings | High performance ceiling, but requires a complete VM binding, root scanning, object model, barrier, and safepoint |
| mruby GC | Tri-color incremental mark-sweep, optional generational, with root arena and write barrier | Suitable as a second-phase incremental GC reference; implementation and state machine are more complex |
| Lua 5.4 GC | Mature incremental/generational collector with tunable pause, step multiplier, and step size | Rich long-running experience, but deeply coupled with the Lua VM, not suitable for direct integration |
| PHP/CPython-style RC + cycle collector | Unreachable objects are usually freed immediately; cycles are handled by an additional collector | High-frequency argument passing, assignment, and field writes all incur INCREF/DECREF, inconsistent with the main performance goals |

Official references:

- Wren VM GC: <https://github.com/wren-lang/wren/blob/main/src/vm/wren_vm.c>
- BDWGC: <https://github.com/bdwgc/bdwgc>
- Oilpan standalone library: <https://v8.dev/blog/oilpan-library>
- Oilpan C++ GC design: <https://v8.dev/blog/high-performance-cpp-gc>
- MMTk plans/bindings status: <https://www.mmtk.io/status>
- MMTk VM porting guide: <https://docs.mmtk.io/portingguide/>

#### 8.8.1 Performance Comparison

TypePHP's main hot path is Native Object pointer passing, variable assignment, and Native pointer property writes, not the GC itself. Candidate solutions must prioritize avoiding additional cost on every assignment.

| Solution | Pointer-assignment hot path | Allocation and reclamation | Pause characteristics |
|---|---|---|---|
| Wren-derived STW mark-sweep | Raw pointer writes, no RC, no barrier | Simple free-list/page allocator, full heap mark/sweep | Full mark pause is longer when the heap is very large |
| BDWGC default mode | Ordinary raw pointer writes, no explicit barrier | Highly optimized and mature; scans stack, registers, globals, and GC heap | STW by default; incremental/parallel on some platforms |
| Oilpan/cppgc | `Member<T>` writes; incremental/concurrent marking requires a barrier fast path | Mature page heap and concurrent/incremental marking/sweeping | Best low-pause capability, but a more complex mutator hot path |
| MMTk MarkSweep | A non-moving MarkSweep with NoBarrier is selectable | Strong allocator/metadata/parallel-worker infrastructure | Depends on binding and plan; the first-version binding itself is expensive |
| MMTk Immix/Generational | Requires barriers, object logging, or remembered sets; some plans may move objects | Highest throughput and space-utilization potential | Can achieve lower pauses, but higher risk of breaking raw pointer stability |

For TypePHP programs where "objects are passed, assigned, and referenced very frequently", the Wren-derived STW and BDWGC default-mode mutator hot paths have the most advantage. The advantages of Oilpan and MMTk advanced plans mainly show up in large-heap pauses and throughput, while the cost enters every pointer write or the overall runtime integration.

#### 8.8.2 Precision and Long-Term Memory Stability

BDWGC is a conservative collector. It treats machine words in stack/register/global that look like GC heap addresses as potential pointers. Its official documentation explicitly states that it does not guarantee reclaiming all inaccessible storage. Misidentification usually only delays reclamation, but in a long-running program the memory upper bound depends on stack contents, address layout, and compiler behavior.

BDWGC can use typed allocation descriptors to reduce mis-scanning inside the heap, but the native stack is still a conservative root. TypePHP can already know Native pointer locals and Native pointer fields accurately at compile time, so giving up that information for conservative scanning is not ideal.

Oilpan is likewise heap-precise but native-stack-conservative. It is reliable in Chrome/Blink, but may still delay reclamation due to false pointers on the native stack. Oilpan's use case can leverage event-loop task boundaries to choose a cleaner stack state; a long-running TypePHP CLI does not necessarily have the same conditions.

Both the Wren-derived GC and MMTk can use TypePHP-generated shadow root frames to be fully precise. As long as root frames and `trace()` are generated correctly, no object retention caused by false pointers exists.

#### 8.8.3 Object Layout Compatibility

TypePHP has already determined that Native Object variables are raw pointers, Native structs contain only public fields, and methods are `php_*` free functions.

- The Wren-derived GC can place the GC header before the struct and scan raw pointer fields via `NativeTypeDescriptor`, fully matching that layout.
- BDWGC allows returning raw pointers directly, with the least layout intrusion, but cannot naturally reuse TypePHP's precise root information.
- Oilpan requires GC objects to use `GarbageCollected<T>`, heap pointers to use `Member<T>`, and a `Trace()`; this would change the already-determined struct and field design.
- MMTk does not force a C++ base class, but the binding must define object reference, header/side metadata, copy/pin, root slots, and object scanning. If a moving plan is used, all raw pointers must also be updatable or permanently pinned.

#### 8.8.4 C++ Destruction and Zend Reentry

Native Objects can contain `php::Str`, `php::Array`, `php::Object`, and `php::Var`, and C++ destructors must run during reclamation; a Zend Object destructor may also execute PHP user code.

- A Wren-derived library can implement TypePHP's required two-phase flow of "removing unreachable objects, then destructing outside the GC critical section".
- BDWGC provides finalizers, but their execution order and re-reachability semantics require additional adaptation; it cannot directly understand the ZendVM's exception and request lifecycle.
- Oilpan performs finalization for objects with non-trivial destructors, but officially constrains finalizers not to access other on-heap objects; complex scenarios need pre-finalizers and depend on its runtime rules.
- MMTk leaves finalizer/weak-reference semantics to the VM binding, so the implementation responsibility still falls to TypePHP.

Therefore none of the four solutions can directly solve Zend reentry; the Wren-derived solution, while requiring its own implementation, can implement only the strict semantics TypePHP actually needs.

#### 8.8.5 Maturity and Integration Risk

| Solution | Upstream maturity | TypePHP new-code risk | Build and distribution |
|---|---|---|---|
| Wren-derived GC | The Wren algorithm has been used long-term; the extracted derived library needs TypePHP's own verification | Medium; the core is small but root/finalization adapters must be fully tested | Small C static library, easy to support GCC/Clang/MSVC/WASI |
| BDWGC | Highest, with a long C/C++ usage history and multi-platform code | Low to medium; main risks are conservative retention and Zend finalizer adapters | Mature CMake/static library, closest to GMP-like dependencies |
| Oilpan/cppgc | Very high maturity within Chrome/Blink | High; API, object layout, and platform/task integration all conflict with the current design | Originates from the V8 project; large GN/platform dependencies and version-upgrade costs |
| MMTk | Active GC framework and multiple VM bindings | Very high; the new TypePHP binding itself is a large runtime project | Adds Rust/Cargo, C ABI, worker/safepoint, and cross-platform build chain |

Oilpan's and MMTk's "upstream maturity" cannot be directly equated with "reliable TypePHP integration". What really determines reliability is the newly built adapter/binding, and the binding surface required by these two solutions is far larger than that of the Wren-derived library or BDWGC.

#### 8.8.6 Cross-Platform and WASM

TypePHP needs to consider Linux, Windows, macOS, and wasm32-wasip2 simultaneously:

- The Wren-derived GC depends only on explicit root frames and ordinary linear memory, making it the easiest to port across platforms.
- BDWGC's native stack/register/dynamic-library scanning contains platform-specific implementations. Native desktop platforms are mature, but WASI needs separate verification; WebAssembly generally cannot arbitrarily inspect the VM stack like native programs, and traditional ports often require a shadow stack.
- Oilpan/cppgc depends on V8 platform/task infrastructure and is unsuitable as a WASI static-library dependency.
- MMTk currently has no TypePHP/WASI binding; a Rust target being available does not mean the GC plan, threads, memory mapping, and root scanning are available.

#### 8.8.7 Final Choice

Overall conclusion: the first version continues to select the Wren-derived precise, non-moving, stop-the-world mark-sweep.

Selection rationale, in priority order:

1. Native pointer assignment and passing remain true raw-pointer zero-overhead operations.
2. Use TypePHP's known precise root/field information, to avoid conservative retention.
3. Keep object addresses stable, without introducing handles, pinning, or pointer updates.
4. Full control over C++ field destruction, Zend reentry, and request shutdown ordering.
5. The C static library is small, suitable for the existing CMake, the three main desktop platforms, and the WASI toolchain.
6. GC functionality only affects the `#[Native]` branch, and does not bring a large runtime framework into ordinary TypePHP programs.

BDWGC is kept as an alternative verification baseline. During the implementation phase, the same benchmark can compare the Wren-derived GC with BDWGC typed allocation; if the Wren-derived implementation fails the reliability, long-running, or performance thresholds, it can fall back to BDWGC, rather than jumping directly to Oilpan/MMTk.

Oilpan is not adopted, mainly because its object layout, `Member<T>` write barrier, conservative stack, and V8 platform/build dependencies conflict with the current design. MMTk is not adopted for now, mainly because the VM binding and Rust runtime integration scale far exceed the first version's needs; when the Native Heap reaches multiple GB, full mark pause becomes an actual bottleneck, and the project can afford a dedicated GC team, MMTk MarkSweep/Immix can be re-evaluated.

A stable GC adapter API should be defined before coding, but the first version only implements and ships the Wren backend:

```cpp
namespace php::native_gc {

void *allocate(
    size_t size,
    size_t alignment,
    const NativeTypeDescriptor *type
);
void addRoot(NativeRootFrame *frame);
void removeRoot(NativeRootFrame *frame);
void collect();
void shutdown();

} // namespace php::native_gc
```

No runtime function tables, virtual functions, or backend objects are used here. Generated code only calls fixed symbols, is statically linked to the Wren adapter, and the allocation entry can be inlined by LTO. If benchmarks need to switch to BDWGC, a separate build target can link an adapter implementing the same API; the production artifact does not bear the runtime abstraction cost of multiple backends.

Wren GC is determined as the first-version algorithm and code upstream of the Native Heap. Wren uses the MIT License, but its GC implementation is currently coupled with the Wren VM object model and is not a standalone GC library that can be linked directly. Therefore TypePHP should extract the minimal collector subset from a fixed Wren upstream commit and maintain it as an independent third-party derived library, rather than linking the complete Wren VM into the program.

Suggested directory:

```text
phpx/thirdparty/wren-gc/
├── include/
│   └── wren_gc.h
├── src/
│   └── wren_gc.c
├── LICENSE
├── UPSTREAM.md
└── CHANGES.md
```

Third-party library requirements:

- `LICENSE` preserves the complete Wren MIT License and original copyright notice.
- `UPSTREAM.md` records the Wren repository URL, extracted files, and the fixed commit hash.
- `CHANGES.md` records the modifications adapting from the Wren Object/VM model to TypePHP's `NativeTypeDescriptor`/root frame.
- Upstream code is separated from the TypePHP adapter, to avoid writing compiler logic into third-party files.
- Generate an independent static library, e.g. `libwren_gc.a`, with build and linking consistent with third-party dependencies such as GMP, MPFR, and libmpdecimal.
- Programs that do not use `#[Native]` do not need to initialize the Native Heap; whether the static library is still uniformly linked is decided by the final build plan.
- Do not import the Wren parser, bytecode VM, object system, standard library, or other irrelevant modules.

PHPX's Native GC adapter is responsible for type descriptors, root frames, C++ destruction callbacks, Zend reentry protection, and a stable C++ API for generated code. The TypePHP compiler is only responsible for generating descriptors, trace/finalize/destroy functions, root frame operations, and call code. The third-party Wren GC is only responsible for object registration, mark worklist, sweep, thresholds, and heap page/free-list management.

### 8.9 First-Version Algorithm Selection

The first version deterministically adopts the Wren-style precise, non-moving, stop-the-world mark-sweep:

- Native pointer assignment performs no reference counting.
- Native pointer property writes require no write barrier.
- Ordinary argument passing is just copying one pointer.
- GC runs only at Native allocation, explicit collection, and shutdown safe points.
- Each collection traverses the complete Native object graph starting from precise roots.
- Circular objects and ordinary unreachable objects are reclaimed with the same algorithm.
- After collection completes, the live byte count is used to compute the next threshold.

The first version adopts the following fixed defaults:

- First collection threshold: 16 MiB.
- Minimum post-collection threshold: 1 MiB.
- Live-heap growth ratio: 50%.
- Next collection threshold: `max(1 MiB, liveBytes + liveBytes * 50%)`.

The adaptive growth strategy follows Wren's mature design; TypePHP sets the first threshold to a rounder 16 MiB.
The threshold automatically scales with the actual live byte count after each collection cycle,
but does not read PHP `memory_limit`, host physical memory, or container memory. PHP `memory_limit` targets
Zend request memory, and the common 128 MiB default cannot represent the Native Heap budget of a long-running TypePHP program;
scaling the threshold proportionally to host memory would also make the same program behave unstably on different machines.

16 MiB only represents the cumulative Native allocation allowed before the first full collection is triggered, and is not a reservation or an immediate request for 16 MiB. The 1 MiB lower bound prevents a small live set from repeatedly triggering stop-the-world collections; the 50% headroom is a more conservative compromise between scan CPU and extra memory than Go's default 100%, because the first-version collector is a single-threaded stop-the-world collector, not a concurrent collector. These internal constants can only be adjusted later based on TypePHP's real allocation rate, survival rate, pause time, and peak-memory benchmarks; no language-level GC tuning interface is exposed.

The main reason for choosing stop-the-world over incremental GC is that object passing, field assignment, and reference updates in TypePHP programs can be extremely dense. Incremental tri-color GC must maintain the color invariant during marking, adding a write barrier to the Native pointer field write path. Even if the barrier's normal path is only one branch, it still affects the most important high-frequency path.

### 8.10 Later Low-Pause Mode

If benchmarks prove the full mark phase pause is unacceptable, an optional incremental mode can be added referencing mruby/Lua, without changing the default fast path:

```cpp
object->child = value;

if (UNLIKELY(native_heap.is_incremental_marking())) {
    native_heap.write_barrier(object, value);
}
```

In actual generation, the GC phase should be checked first and the barrier executed only during the marking phase. In normal mode the compiler can generate no barrier at all; only when incremental mode is enabled is the `UNLIKELY` branch added.

Generational GC requires a remembered set and makes old-to-young pointer writes permanently carry a barrier, and should not enter the first version. It is only re-evaluated when real applications prove that a large number of Native Objects "die young" and full mark cost becomes significant.

## 9. Parameter Passing

Ordinary object parameters are passed by pointer value:

```php
function move(Point $point, float $x): void
{
    $point->x = $x;
}
```

Approximately generated as:

```cpp
void php_move(php_app__point *point, php::Float x);
```

This simultaneously satisfies:

- No copying of the object entity.
- No reference count increment.
- Property modifications are visible to the caller.
- Reassigning `$point` inside the function does not affect the caller's variable.

PHP reference symbols are neither needed nor allowed here:

```php
function replace(Point &$point): void; // FatalError
$alias =& $point;                       // FatalError
refval($point);                         // FatalError
$point->toRef();                        // FatalError
```

Ordinary `$alias = $point` already only copies the typed pointer; both point to and modify the same object.

Returning a Native Object returns a pointer:

```cpp
php_app__point *php_create_point();
```

Non-nullable class parameters perform one null-pointer check at function entry. Definitely-non-null member accesses should not be checked repeatedly.
nullable classes must use `?Point`, using the same pointer representation, with `nullptr` denoting `null`.
The implicit nullable declaration `Point $value = null` is not supported; it must be written as `?Point $value = null`.
`Point|null`, other unions/intersections, Native variadic parameters, and Native reference returns are not supported.
Parameters and return values must explicitly declare a concrete Native Class (or its nullable form), and cannot be passed through a `mixed`, `object`, or Interface carrier.

For example:

```php
function bar(Point $point): void
{
    // The entry null check has already run; after entering the function body, $point always points to a Point object.
    echo $point->x;
}

function maybeBar(?Point $point): void
{
    // $point may be null; it must be narrowed first, or a null check generated by member access, before use.
    if ($point !== null) {
        echo $point->x;
    }
}
```

Both use the `php_app__point *` C++ ABI, but the contracts differ: `bar()` rejects `nullptr` before executing the first user statement; `maybeBar()` accepts `nullptr`. This entry guarantee only constrains the incoming value; the function may still reassign its own local pointer slot to `null` internally, without changing the caller's variable slot.

## 10. ZendVM Boundary

Native Object has no corresponding `zval` representation, so it can only be passed to parameters that explicitly accept the same Native Class or its Native base class. Interface is only used to validate Native Class declaration contracts and cannot serve as a carrier for Native Object parameters, properties, variables, or return values.

A Native Class field can hold `php::Var`, `php::Array`, or `php::Object`, but this does not give the outer Native Object a `zval` representation. Allowing "Zend values to enter Native fields" is not the same as allowing "Native Object to enter the ZendVM".

The first version forbids a Native Object from being:

- Assigned to `mixed` or an ordinary `object`.
- Converted to `php::Var` or `php::Object`.
- Passed to unknown PHP functions, PHP extension functions, or dynamic methods.
- Called with variable method names such as `$nativeObject->$expr()` or `$nativeObject->{$expr}()`.
- Placed in an ordinary PHP `array`.
- Captured into a closure that must be registered as a Zend Closure.
- Used as a parameter, `this`, local variable, return type, or `yield` value of a TypePHP Generator. A Generator
  is represented by a Zend Closure/Fiber state machine, and the Native pointer does not enter that Zend state.
- Used as a Fiber API input value, resume value, or Closure capture. An ordinary TypePHP function can hold a Native Object in its own
  C++ local slot and cross `Fiber::suspend()`; the Native Root Frame uses a thread-local doubly-linked intrusive list
  that can be removed in O(1), and the GC scans the valid frames of both running and suspended Fibers,
  without depending on cross-Fiber LIFO destruction order.
- Used as the receiver of dynamic callbacks such as `call_user_func()`.
- Saved into ZendVM global variables or object properties.

Box cannot hold Native Objects. Std Containers cannot be Native Class properties, but local `std::array`, `std::vector`, `std::map`, and `std::ordered_map` can use a concrete `NativeClass::class` as the value type and hold that class or its Native subclasses. Ordinary PHP arrays still cannot hold Native Objects.

TypePHP's current Std Containers themselves are only allowed as local variables inside functions, not as global/static, so there is no long-term container ownership that needs separate design for Native elements. A Native-element Std Container further requires it to be a top-level local variable of the function. The compiler generates a `NativeContainerRootFrame` matching its lexical lifetime for that local container; therefore it cannot be saved to global/static, Zend or Native properties, PHP arrays, and cannot be returned, taken by reference, captured into a Closure/arrow function, or converted via `toArray()`/`toAny()`. All of the above would make the raw-pointer-holding `StdContainerBox` outlive the root frame, and must be uniformly rejected at compile time. Reading or writing a single typed Native element still stays within the Native pointer model and does not constitute container escape.

Any behavior crossing the ZendVM boundary should throw a FatalError at compile time. The compiler must not silently box or degrade, because that makes the performance model unpredictable.

Native Object must always remain a typed object. It cannot be erased into `var`, `mixed`, an ordinary `object`, or an untyped callback receiver. Even if the compiler can constant-fold `$expr = 'run'`, the variable method name syntax is still not supported; only `$nativeObject->run()` written explicitly in source enters Native method resolution.

## 11. Property Access

Properties without Hooks access fields directly:

```php
$point->x = 1.0;
echo $point->x;
```

Approximately generated as:

```cpp
point->x = 1.0;
echo(point->x);
```

All physical properties must explicitly declare a type. The first version does not support dynamic properties, string property names, `__get()`, or `__set()`.

Visibility is checked only at compile time; no runtime access-control metadata is generated.

### 11.1 Visibility

Access permissions of `public`, `protected`, and `private` are checked entirely statically by the Native Class compiler. No visibility flags are stored at runtime, and no scope switching or permission judgment is performed.

All fields in the generated C++ `struct` remain public:

```cpp
struct php_app__user final {
    php::Str name;
    php::Int age;
};
```

`private string $name` in PHP source does not generate C++ `private:`. This is because methods are `php_*` free functions, and a C++ private field would prevent the corresponding method function from directly accessing the field, forcing the implementation to introduce friend, member methods, or additional accessors.

The compiler must perform static permission checks at the following locations:

- Direct property reads and writes.
- Getter, Setter, and Property Hook lowering.
- Method calls and static method calls.
- clone field copying.
- Access after Trait AST injection.
- Compiler-generated helper code.

Native Object cannot enter dynamic calls, Reflection, or the ZendVM, so there is no legitimate runtime entry to bypass visibility. Handwritten C++ code that directly accesses fields is outside the TypePHP language compatibility scope.

## 12. Getter, Setter, and Generator Annotations

Pure compile-time generator annotations such as Getter and Setter can be supported. They should first expand into ordinary AST, and then the Native Class branch generates `php_*` free functions.

```php
#[Native]
final class User
{
    #[Getter]
    #[Setter]
    private string $name;
}
```

Approximately generated as:

```cpp
struct php_app__user final {
    php::Str name;
};

php::Str php_app__user__getname(php_app__user &this_) {
    return this_.name;
}

void php_app__user__setname(php_app__user &this_, php::Str value) {
    this_.name = value;
}
```

Simple Getter/Setter should allow the C++ compiler to fully inline them. Native Class does not register annotations or generate Reflection metadata.

In principle, all generator annotations that only modify AST and do not depend on the ZendVM can be supported. The concrete support list needs to be confirmed item by item before implementation.

### 12.1 Trait AST Injection

Native Class supports Trait. A Trait does not establish an independent Native runtime type or generate an object entity; the existing compile-time AST injection mechanism of TypePHP continues to be reused.

The processing order is fixed as:

1. Parse the class and Trait, and complete `use`, `insteadof`, `as`, and conflict checks.
2. During the convert phase, inject the Trait's properties, constants, methods, and Property Hook AST into the target class.
3. Preserve the node's Trait origin, Trait namespace/use context, and `__TRAIT__` information.
4. Run Native Class type, visibility, inheritance, Interface, and boundary checks on the complete injected class AST.
5. Generate fields and `php_*` methods for injected members just like ordinary class members.

The same Trait can be used by both ordinary TypePHP classes and Native Classes; which object model is ultimately adopted is decided by the target class. Properties injected by a Trait must still have a legal explicit type, and methods must still satisfy the Native Class ZendVM boundary restrictions.

### 12.2 Interface

Interface does not accept `#[Native]`. It is still an ordinary PHP/TypePHP Interface, registered with the ZendVM as usual; the behavior of ordinary PHP classes implementing the Interface is unchanged. Native Class supports `implements`, but its relationship with the Interface exists only at TypePHP compile time:

- Use PHP-consistent rules to check whether required methods and hooked properties exist, and whether visibility, static/reference/variadic, parameter and return types, and property read/write constraints are compatible.
- Project Interfaces use the complete declarations obtained from preprocessing; built-in PHP Interfaces use the formal signatures obtained from Reflection. Tentative return types keep PHP 8.4's non-fatal semantics and are not arbitrarily upgraded to FatalError.
- Checks happen after Trait AST injection and inherited-member merging, so methods provided by a Trait or parent class can satisfy the Interface.
- Interface inheritance and multiple `implements` declarations are supported.
- No Interface vtable, runtime interface id, `zend_class_entry`, or Reflection metadata is generated for the Native Class; the ZendVM never sees that the Native Class is an implementor of the Interface.
- When the receiver's concrete Native Class is known at compile time, `$native instanceof SomeInterface` folds directly to `true` or `false` based on the complete `implements` relationship.
- Dynamic Interface casts are not supported, and a Native Object cannot be handed to a ZendVM Interface parameter or queried through Reflection about its implementation relationships.

An Interface type cannot become a type-erasure carrier for Native Objects. Even if the call site knows the concrete Native Class, it is forbidden to pass a Native Object to an Interface-typed parameter, or assign/return it as an Interface type. Interface-typed parameters and properties can still hold Zend Objects normally, but cannot also hold Native Objects. The compiler must not generate `reinterpret_cast`, `void *` conversion, or temporary Zend Objects for this; an incorrect conversion would break object layout and may cause a crash, so a FatalError must be thrown before C++ code generation.

The first version explicitly does not provide call-site static specialization, fat pointers, or interface tables. Use Trait when Native method implementations need to be shared; use Native base classes with real C++ inheritance relationships for polymorphic parameter passing. If runtime dynamic dispatch between multiple implementations without a common Native base class is truly needed in the future, it should be designed separately as a new object representation, and must neither silently box Native Objects into Zend Objects nor change the current raw-pointer Native Call hot path.

### 12.3 `Iterator` and `IteratorAggregate`

Native Class is allowed as the iterable of `foreach` only when it explicitly implements `Iterator` or `IteratorAggregate`. The compiler does not fall back to "iterating the object properties visible in the current scope" as the ZendVM does; a Native Object without an iteration interface reports an error at compile time.

`Iterator` is fully lowered to determined Native Method Calls, with the same call order as PHP:

```text
rewind() → valid() → current() → key() → loop body → next()
```

`key()` is not called when no key variable is bound. The compiler evaluates the iterable only once at loop entry and keeps it in an independent precise GC root; therefore reassigning the original variable in the loop body does not change the iterator being executed.
`continue` calls `next()` through the C++ `for` iteration expression; `break` does not. The null check for the Native iterator is performed only once at loop entry; the protocol methods' hot path does not repeat it.

`IteratorAggregate::getIterator()` is called only once:

- When it returns a concrete Native Class that implements `Iterator`, continue with the fully-Native path above;
- When it returns an ordinary PHP `Traversable`, only the returned object enters the existing PHPX `ForeachIterator`;
- Other return types are rejected at compile time.

`current()` may declare a concrete Native Class return, and the foreach value variable is inferred as the corresponding typed Native pointer. Native `foreach` does not support `&$value`, to avoid references and indirect modification entering the iteration protocol.

### 12.4 `instanceof`

Native Class has no `zend_class_entry` or runtime class-name lookup, so it only supports `instanceof` where the target class can be resolved at compile time. The compiler folds it directly to `true` or `false` based on the Native static type and inheritance relationship, while still preserving side effects such as construction and function calls in the left operand:

```php
$object instanceof NativeClass;
```

TypePHP does not add special `instanceof` syntax for `NativeClass::class`. The following runtime class operand is not supported:

```php
$class = NativeClass::class;
$object instanceof $class; // FatalError
```

If the variable's static type is a Native parent class and the target is its subclass, the result depends on the object's runtime dynamic type; the compiler likewise throws a FatalError, rather than fabricating an incorrect boolean result.

## 13. Property Hook

Property Hook can be compiled into determined C++ getters/setters:

```php
#[Native]
final class User
{
    public string $name {
        get => strtoupper($this->name);
        set => trim($value);
    }
}
```

Approximately generated as:

```cpp
struct php_app__user final {
    php::Str name_storage;
};

php::Str php_app__user__get_name(php_app__user &this_);
void php_app__user__set_name(php_app__user &this_, php::Str value);
```

Reads and assignments generate respectively:

```cpp
php_app__user__get_name(*user);
php_app__user__set_name(*user, value);
```

To keep the Native branch simple and free of implicit runtime dispatch, the first version only supports direct reads and direct assignments:

```php
$value = $user->count;
$user->count = getValue();
```

Hooked properties forbid:

- Compound writes such as `+=`, `.=`.
- `++`, `--`.
- Indirect writes such as `$object->hookedArray[] = ...`, element assignment, or element `unset()`.
- `isset()`, `empty()`.
- Taking references.
- Reference returns.
- Returning the underlying property slot.
- Using reference optimizations such as `int_ref`, `float_ref`.
- Bypassing the Hook to write directly to the backing field.

Native Property Hook has only compile-time semantics and generates no Zend Property Hook metadata.

## 14. Clone

`clone` can be supported, but the compiler must generate field-level shallow copy and cannot unconditionally rely on the C++ default copy constructor.

When the receiver's static type may hold a Native subclass, the inheritance hierarchy generates an internal covariant virtual clone thunk, and the dynamic subclass performs the correctly-sized field copy and calls its `__clone()`; it must not copy by the static base class, otherwise C++ object slicing occurs. Classes without Native inheritance relationships continue to use the static clone path and do not add a vptr.

```php
$copy = clone $source;
```

Approximately generated as:

```cpp
auto *copy = native_heap.make<php_app__user>();
copy->name = source->name;
copy->profile = source->profile;
php_app__user____clone(*copy);
```

Copy rules:

- Scalar fields are copied by value.
- String, PHP Array, Zend Object, Stream, and mixed follow their respective PHPX/C++ type copy semantics.
- PHP Array keeps PHP's copy-on-write behavior, without unconditional deep copy.
- Zend Object fields copy the object handle, continuing to point to the same Zend object.
- Native Object fields copy the pointer, continuing to point to the same object, preserving shallow-copy semantics.
- After field copying completes, the optional `__clone()` is called; when the subclass does not redeclare it, the inherited `__clone()` is resolved and called, and when the subclass redeclares it, the parent implementation is not implicitly called again, consistent with ordinary method override rules.
- The public/protected/private visibility of `__clone()` is checked in the compile-time scope where the clone expression appears, and cannot be bypassed through a direct Native Call.
- The clone operand can be a typed variable, Native function/method call, or Native property expression; non-variable operands are first materialized as a precisely-rooted temporary raw pointer.

A Native Class containing non-copyable fields must explicitly forbid clone; using `clone` on it reports an error at compile time.

## 15. Construction and Destruction

### 15.1 Construction

`new` creates the structure in the Native Heap, then directly calls the `php_*` free function corresponding to the constructor:

```cpp
auto *object = native_heap.make<php_app__user>();
php_app__user____construct(*object, args...);
```

When the constructor throws an exception, the already-initialized fields must be destroyed, and the object must be removed from the Native Heap's active object set.

Consistent with other TypePHP classes, `__construct()` can only be triggered by `new`. Explicitly calling `$object->__construct()` reports an error at compile time, to avoid re-initializing an already-alive Native Object.

### 15.2 Destruction

`__destruct()` conflicts with PHP's precise destruction timing. A tracing GC can only guarantee resource cleanup after the object becomes unreachable and a GC completes; it cannot guarantee immediate execution when the last variable leaves scope.

Native Class must support user-defined `__destruct()`, but adopts the tracing GC lifetime semantics:

- `__destruct()` is called when the object is confirmed unreachable in a GC, or at Native Heap shutdown.
- The user destruction logic is called at most once per object.
- User code cannot explicitly call `$object->__destruct()`, `self::__destruct()`, or `parent::__destruct()`; these are reported as FatalError at compile time.
- Within the same reclamation batch, the destruction order between different objects is not guaranteed to match PHP.
- Destruction along the inheritance chain executes automatically from the most-derived class to the base class; explicit user calls to the parent destructor are neither required nor allowed.

User `__destruct()` cannot directly serve as the body of the actual C++ destructor. The reason is that TypePHP methods may throw exceptions, call the ZendVM, or allocate Native Objects again; letting these behaviors happen from a C++ destructor, especially during exception stack unwinding, may trigger `std::terminate()` and cannot safely handle object resurrection.

Therefore two clearly separated phases are used:

```cpp
struct NativeTypeDescriptor {
    void (*trace)(void *object, NativeMarker &marker);
    void (*finalize)(void *object); // calls the php_* __destruct chain
    void (*destroy)(void *object);  // C++ destructor + storage release
};
```

1. GC removes unreachable objects from the active set and marks them `finalizing`.
2. The dynamic type descriptor's `finalize()` is invoked outside the GC mark/sweep critical section.
3. `finalize()` automatically calls the `php_*__destruct` free functions declared at each level, in derived-to-base order.
4. After user destruction completes, roots are rechecked; if the object was re-saved to a Native root during destruction, the object is kept but marked `finalized`, and the user destructor is never called again.
5. Non-resurrected objects call `destroy()`; the actual C++ destructor only handles field RAII and base-class subobject cleanup, keeping `noexcept`. The descriptor already records the most-derived type, so it does not depend on executing `delete` through a base-class pointer, nor does it require adding a vtable to every inheritance hierarchy merely for destruction.
6. When the finalizer throws an exception, GC must first restore internal state and guarantee the object can eventually be cleaned up, then propagate the exception to the current TypePHP exception boundary; the shutdown phase follows a separate no-throw policy.

Request shutdown clears registered global/static Native roots once before and once after finalization.
This is necessary: `__destruct()` may write `$this` back into some global slot during finalization, but the request
heap will still be destroyed as a whole afterward; the second clear prevents dangling pointers from entering the next request.

This design preserves the resource-cleanup capability of `__destruct()` while avoiding letting complex user code pass through the C++ destructor. Its main difference from PHP is that the call timing is decided by the Native GC, rather than the moment the reference count drops to zero.

### 15.3 `unset()` and Destruction Timing

A Native local variable is a `native_struct *` slot tracked by a root frame. Ordinary assignment only copies the pointer, so multiple variables can reference the same object.

`unset($object)` and `$object = null` only set the current pointer slot to `nullptr`; they neither zero out object properties nor affect other aliases. Only when the object has no other Native root or Native field references and is confirmed unreachable in the next GC or shutdown does it enter finalization. This preserves PHP's object identity and aliasing semantics, but does not guarantee PHP's immediate destruction timing when the reference count reaches zero.

Null checks for method calls should be decided by the compiler's nullability analysis: `new`, values that have completed the entry check for non-nullable parameters, and Native `this_` can be dereferenced directly; only nullable/global/static values, or values whose non-nullness cannot be proven after control-flow merging, generate a runtime `UNEXPECTED(ptr == nullptr)` check.

### 15.4 Keyword Conversion Methods

Native Class supports TypePHP keyword conversion methods such as `toArray()`, `toString()`, `toInt()`, `toFloat()`, and `toBool()`, but does not enter PHPX's dynamic conversion helper. The compiler requires the Native Class to actually declare the corresponding zero-argument method and lowers the call directly to a Native Call.

`toObject()` follows the same rule: if a Native Class declares `toObject(): object`, the keyword call points directly to
this determined Native method; when it is not declared, declares parameters, or has a non-object return value, all are reported at compile time. The
`toObject()` here is a user-defined data conversion method, not handing the Native pointer to the generic PHPX
`php::toObject()` helper, nor establishing an implicit Zend carrier for the Native Object.

Method return types must exactly match the keyword type. For example `toArray(): array`, `toInt(): int`, `toString(): string`; a missing method, parameters, by-reference return, or a different return type are all compile-time FatalErrors.

Object conditionals and explicit conversion are two separate semantics. `if ($object)`, `!$object`, `$left && $right`, and
`$left || $right` only determine whether the Native pointer is `nullptr`, and do not call `toBool()`; this is consistent with
PHP's "existence is true" semantics for ordinary objects, and also lets a nullable Native pointer be used directly as a
condition. Only the explicit `(bool) $object` or `$object->toBool()` resolves to a Native `toBool(): bool`
call; when no such method is defined, an error is reported at compile time. Even if a class-defined `toBool()` returns `false`, a non-null
object is still `true` in `if ($object)`.

`__toString(): string` is a compatible alias for `toString(): string`. When using `toString()`, `strval($object)`, `(string) $object`, string concatenation, or `echo` on a Native Object, the compiler prefers the actually-declared `toString()`, and uses `__toString()` if it does not exist.

Consistent with PHP, a Native Class that declares a legal `__toString()` implicitly satisfies `Stringable` at compile time;
`$native instanceof Stringable` folds to `true`, but this still does not allow the Native Object to be converted or
passed as a `Stringable` Interface value.

### 15.5 `count()` and `Countable`

When the compiler can statically determine that a Native Class implements `Countable`, `count($nativeObject)` is equivalent to
`$nativeObject->count()`, and is directly lowered to the same Native Call. The Native Object does not construct a
Zend Object for this, nor enter `php::fn::count()`.

Merely declaring a method named `count()` is not enough; the Native Class must explicitly `implements Countable`, and the
implementation passes the internal Interface signature validation. The first version only supports the single-argument form `count($nativeObject)`; the form with `$mode` does not enter this Native specialization path.

### 15.6 Nullsafe Operator

When the Native root and every intermediate receiver are Native pointers, `?->` uses a dedicated short-circuit
lowering. Each level performs only one `nullptr` check, and method arguments are evaluated only after the receiver is non-null. When the final result
is a Native Object, a nullable typed pointer is returned; when the final result is a PHP scalar or PHPX value,
because the PHP semantics are `T|null`, it is only boxed into `php::Var` at the result boundary.

A Native nullsafe chain cannot continue after switching to a Zend Object in the middle; this mixed object-model chain is rejected at compile time, and the user should split it into two statements. A Native Property Hook can serve as the final direct read;
`isset()/empty()` does not support Hook properties.

### 15.7 `json_encode()`

Native Object has no `zval` representation and cannot be an argument of `json_encode()` or other PHP/ZendVM functions. The compiler adds no special lowering for `json_encode()` and does not implicitly construct a temporary Zend Object or DTO; `json_encode($nativeObject)` reports an error directly at compile time.

When JSON is needed, the Native Class should explicitly provide `toArray(): array` returning a PHP array, and the user then calls:

```php
$json = json_encode($nativeObject->toArray());
```

Explicit conversion makes the allocation cost and the object-graph conversion boundary clearly visible in source, and also maintains the uniform rule that "Native Object does not cross the ZendVM boundary".

## 16. First-Version Support Boundary

| Feature | First-version recommendation |
|---|---|
| Fixed typed properties | Supported |
| string/array/object/Stream/mixed properties | Supported, using the corresponding PHPX RAII fields |
| Untyped properties | Not supported, compile-time FatalError |
| Direct property read/write | Supported |
| Ordinary member methods | Supported |
| Native Object parameters/returns | Must explicitly declare a concrete Native Class; passed by pointer value, no object copy |
| Non-null Native parameters | `NativeClass $value` uniformly rejects `nullptr` at function entry; guaranteed non-null after entering the function body |
| Nullable Native parameters/returns | Supports `?NativeClass`, denoted by `nullptr`; member access must check or first prove non-null |
| `&` on Native parameters/returns | Not supported; compile-time FatalError |
| Taking references to Native Object variables | Not supported; ordinary assignment already shares object identity |
| Taking references to Native properties | Only fields explicitly declared `any` are supported; all other fields including `mixed` are compile-time FatalError |
| Native variadic, union/intersection | Not supported; compile-time FatalError |
| `__construct()` | Supported |
| `clone` / `__clone()` | Supported |
| Getter/Setter annotations | Supported |
| Property Hook | Direct get/set supported; indirect writes, compound writes, references, isset/empty not supported |
| Trait AST injection | Supported; after injection compiled as ordinary Native members |
| `readonly` | Not supported, compile-time FatalError; PHP readonly is a runtime mechanism depending on Zend property initialization state, incompatible with the Native fixed raw-field model |
| Keyword conversions such as `toArray()`/`toInt()`/`toObject()` | Supported, requiring the Native Class to declare zero-argument methods with exactly matching return types; directly generates Native Call |
| `toString()` / `__toString()` | Supports determined Native Call; string casts, `strval()`, concatenation, and `echo` use the same rule |
| `count($nativeObject)` | Supports determined Native Call; requires the Native Class to implement `Countable`, first version limited to the single-argument form |
| `isset()` / `empty()` | Supports raw pointer slots and pure Native named property chains, short-circuiting level by level, not entering the ZendVM |
| `is_null()` | Supports Native typed pointer, comparing directly with `nullptr` |
| Nullsafe `?->` | Supports pure Native receiver chains; Native returns keep typed pointer, scalar returns boxed as `T|null` |
| `__invoke()` | Supports determined Native Call |
| `__destruct()` | Supported, triggered by GC finalization and at most once per object |
| Native Class single inheritance | Supported; mutual inheritance with ordinary ZendVM classes forbidden |
| Native abstract class / abstract method | Supported; generates pure virtual thunk, concrete subclass implementation check at compile time |
| override method | Supported; same-name instance methods in the inheritance chain generate virtual dispatch thunks |
| Same-name method overloading by parameter signature | Not supported; PHP source does not allow redeclaring a method with the same name in the same class |
| Interface | Ordinary Interfaces registered with ZendVM; Native `implements` only performs compile-time contract validation, Native Object cannot be converted to an Interface value |
| `foreach` / `Iterator` | When implementing `Iterator`, directly generates `rewind/valid/current/key/next` Native Calls; iterable evaluated only once, supports `continue`/`break` |
| `IteratorAggregate` | `getIterator()` called only once; a concrete Native Iterator continues on the Native path, PHP `Traversable` uses the PHPX iterator |
| Native `foreach` by-reference iteration | `foreach ($native as &$value)` not supported; compile-time FatalError |
| Native Object without an iteration interface | Public properties are not enumerated; compile-time FatalError when used in `foreach` |
| `instanceof` | Supports compile-time-resolvable Native classes and Interfaces, folding directly; variable class not supported |
| `===` / `!==` | Supports Native pointer identity and strict comparison with `null` |
| `match` on Native conditions | Supported, using the same pointer identity rule as `===` |
| `==` / `!=`, ordering, and arithmetic/bitwise operations | Not supported, compile-time FatalError; value equality should use an explicit Native method |
| Unary arithmetic/bitwise, `++`/`--`, compound arithmetic assignment, `switch` | Not supported, compile-time FatalError |
| Dynamic properties | Not supported |
| `$nativeObject->$expr()` | Not supported, only named method calls allowed |
| `__call()` / `__callStatic()` | Not supported; Native Call must resolve to a determined symbol at compile time |
| `__get()` / `__set()` / `__isset()` / `__unset()` | Not supported, replaced by named properties and Property Hook |
| `__sleep()` / `__wakeup()` / `__serialize()` / `__unserialize()` | Not supported; Native Object does not enter the Zend serialization system |
| `__set_state()` / `__debugInfo()` | Not supported; Native Object has no corresponding Zend object handler |
| Reflection | Not supported |
| TypePHP Generator holding or yielding Native Object | Not supported; compile-time FatalError |
| Ordinary-function Native local variable crossing `Fiber::suspend()` | Supported; the Root Frame registry allows non-LIFO Fiber lifetimes |
| `get_class()` / `get_parent_class()` / `get_called_class()` | Native runtime introspection not supported; use `self::class`, `parent::class`, or concrete class names |
| WeakReference | Not supported |
| PHP serialize | Not supported |
| PHP `json_encode()` | Does not support passing a Native Object directly; explicitly call `toArray()` first |
| Dynamic callback | Not supported |
| Dynamic PHP/eval usage | Not supported |
| Ordinary PHP array holding Native Object | Not supported |
| Native Object as PHP array key | Not supported; compile-time FatalError |
| Native Object as `[]` receiver | When implementing `ArrayAccess`, supports direct read/write, append, `isset`, `empty`, `??`, and `unset`; directly generates Native `offset*()` calls |
| Native `ArrayAccess` element indirect modification | `++/--`, compound assignment, `??=`, nested writes, property writes, and taking references not supported; compile-time FatalError |
| Box/Std Container properties | Not supported |
| Box holding Native Object | Not supported |
| Local Std Container holding Native Object | Only supports function top-level local variables and concrete Native class value types; the container Root Frame participates in GC tracing |
| Native-element Std Container converted to PHP array/mixed or used as a PHP parameter | Not supported; raw pointers must not cross the ZendVM value boundary |
| Native Class property circular references | Supported, pointer fields plus Native tracing GC |
| TypePHP global/static local | Supported; ZTS uses thread-local request roots, RSHUTDOWN cleanup |
| `$GLOBALS` access to Native global | Literal or compile-time-evaluable string constants map to the same C++ slot; dynamic keys do not support Native Object |
| global/static local types | First Native assignment fixes the C++ slot type; later writes may use its Native subclass or null, but not a base/unrelated class |
| Native Class property circular types | Supported; field zero value is `nullptr`, the type graph uses C++ forward declarations |
| late static binding / `new static()` | Not supported; Native Class has no runtime `zend_class_entry`, use `self::`, `parent::`, or concrete class names |

## 17. Actual Directory and Isolation

The current implementation concentrates the object model's main rules in the following locations:

```text
src/NativeClass/
├── NativeClassSupportTrait.php       # declaration, layout, method, boundary, and codegen policy
├── NativeGlobalDiscovery.php         # project-level Native global slot pre-discovery
└── NativeGlobalTypeResolver.php      # read-only symbol query boundary for the pre-discovery analyzer

src/Transform/NativeClassAttributeLowering.php
src/TypeSystem/NativeTypeCompatibilityTrait.php

phpx/include/phpx_native_gc.h
phpx/src/core/native_gc.cc
phpx/thirdparty/wren-gc/
```

Only narrow hooks needed to enter the Native strategy remain in the ordinary parser, call generator, property resolver, and control-flow lowering. Native-specific diagnostics, type mapping, field generation, virtual thunks, trace,
clone, and finalizer rules are concentrated in `NativeClassSupportTrait`; the project-level pre-analysis is placed in a separate
analyzer in the same directory. This allows reusing TypePHP's existing AST, symbol table, and evaluation-order infrastructure, without duplicating a
parallel compiler prone to semantic drift.

Isolation constraints are as follows:

1. The ordinary object path must not generate Native pointers or depend on the Native Heap.
2. Common hooks must first check the determined Native type; when there is no match, keep the original path.
3. When there is no Native class in the project, the global pre-pass returns immediately before scanning source.
4. Native Object must not enter `php::Var`, Zend Object, or dynamic calls through fallback.
5. GC runtime is in separate PHPX header and source files; third-party Wren-derived code retains the origin and MIT
   license files.
6. Native positive tests and compile-time rejection tests are concentrated respectively in:

```text
tests/compiler/native-class/
phpunit/src/NativeClass/NativeClassValidationTest.php
```

Tests for ordinary classes must not be mixed with Native Class tests, to keep the semantic boundary between the two object models clear.

## 18. Diagnostic Principles

All unsupported behaviors must produce a clear error at compile time; they must not crash at runtime or silently fall back to the ZendVM.

Examples:

```text
Fatal error: Native class object App\Point cannot be passed to parameter $value of type mixed
```

```text
Fatal error: Native class App\Point cannot be used with ReflectionClass
```

```text
Fatal error: Native class objects cannot be stored in a PHP array
```

Diagnostics need to point out the specific ZendVM boundary and the available alternatives.

## 19. Performance Principles

The main path of Native Class must satisfy:

- The object variable is a raw pointer.
- Ordinary parameter passing is one pointer copy.
- Property access is equivalent to C++ field access.
- Non-native PHP fields access the corresponding PHPX RAII object through fixed offsets, without looking up property names.
- Determined method calls are equivalent to ordinary C++ function calls.
- No atomic operations.
- No hash-table lookup of properties or methods.
- No temporary Zend Object creation.
- No runtime class-name comparison.
- No hidden fallback inserted for dynamic capability compatibility.

If a PHP feature cannot satisfy these requirements, it should be forbidden first, rather than degrading the performance of all Native Classes.

## 20. Implementation Status

Completed implementation phases:

1. `#[Native] class` syntax, typed pointer rules, and compile-time diagnostic boundaries.
2. C++ struct, fixed fields, descriptor, trace, and `php_*` method generation.
3. Wren-style Native Heap, precise root frames, circular reclamation, exception recovery, and request shutdown.
4. Construction, destruction, property types, PHPX fields, ordinary methods, and typed pointer parameters/returns.
5. Trait AST injection, Getter/Setter, and keyword method direct calls.
6. Single inheritance, abstract, override virtual thunks, signature variance, and Interface compile-time contracts.
7. Property Hook direct getter/setter lowering, rejecting all indirect and compound writes.
8. clone, dynamic subclass clone, circular types, lifetime failure, and object resurrection handling.
9. Std Container local Native values, Fiber roots, global/static request roots, and cross-file slot
   ABI pre-discovery.

`json_encode()` is confirmed as not supporting Native Object directly; use the explicit `toArray()` boundary.
Stack allocation and escape analysis remain separate later performance optimizations, not part of the current object model's correctness.
Every implemented capability has corresponding PHPT, PHPUnit, or PHPX C++ tests; see the acceptance matrix for the detailed correspondence.

## 21. Parameters Determined but Still Requiring Performance Validation

- Native Object can never be converted or assigned to an Interface type; `implements` only provides compile-time contract
  validation, and the first version provides no call-site specialization, fat pointers, or interface tables.
- The Native Heap uses a 16 MiB first threshold, 1 MiB minimum threshold, and 50% live-heap headroom.

These conventions are already fixed. Later benchmarks may adjust the GC's internal values, but must not change the basic design that Native Object has no Interface type erasure, no Zend representation, and a raw-pointer Native Call hot path.

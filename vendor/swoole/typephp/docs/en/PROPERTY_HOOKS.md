# PHP 8.4 Property Hook Integration Design

This document records how the TypePHP compiler and PHPX implement PHP 8.4 Property Hooks, focusing on Zend metadata registration, object introspection, memory lifetime, and version compatibility boundaries. This is an internal maintenance document; user-facing syntax documentation belongs in the external documentation repository.

A Property Hook without an implementation body in an Interface is an abstract property contract and does not go through the concrete-class lowering flow described here. For its model, variance checking, and Zend metadata registration, see [Interface Property Hook Implementation Plan](INTERFACE_PROPERTY_HOOKS.md).

## 1. Background

TypePHP compiles Property Hook bodies into hidden AOT getters/setters. Doing only this step satisfies property reads/writes that the compiler explicitly identifies, but ZendVM does not know that these hidden methods represent Property Hooks, so the following dynamic capabilities diverge from PHP 8.4:

- `ReflectionProperty::hasHooks()`, `getHooks()`, and `isVirtual()`;
- `get_object_vars()`, `json_encode()`, and `var_export()`;
- `foreach` traversal of objects;
- the storage difference between backed properties and virtual properties;
- dynamic property reads/writes initiated by ZendVM.

TypePHP does not emulate these PHP behaviors separately. The compiler preserves Hook metadata after lowering, and PHPX wires the AOT methods into the native PHP 8.4 Property Hook structures when the class is registered during MINIT. After that, Reflection and object introspection reuse the standard ZendVM implementation.

## 2. Compilation Flow

### 2.1 AST lowering

`PropertyHookLowering` converts each Hook into a hidden class method and records on the property AST:

- the hidden method names corresponding to the getter/setter;
- whether the Hook accesses its own backing storage;
- whether the property is a virtual property.

For example:

```php
public string $name {
    get => strtoupper($this->name);
    set => $this->name = trim($value);
}
```

This produces equivalent hidden getters/setters internally. `$this->name` inside the Hook is marked as backing access to avoid recursion by calling the Hook again.

If the Hook does not access backing storage, the property is marked virtual. This conclusion must be obtained during the lowering stage, because the generated Zend property declaration needs it to decide whether to allocate a property slot.

### 2.2 Class registration code

After `gen_stub.php` declares the property and obtains the `zend_property_info *`, it generates:

```cpp
typephp_register_property_hooks(
    class_entry,
    property_info,
    getter_method_name,
    setter_method_name
);
```

The call happens during the class's persistent registration stage, not on the request hot path.

## 3. PHPX Registration Flow

PHPX's `typephp_register_property_hooks()` is implemented only for PHP 8.4 and above, and lives in a TypePHP-specific helper.

### 3.1 Locating the AOT implementation method

PHPX finds the hidden method produced by lowering from the class method table:

```cpp
zend_hash_str_find_ptr(&ce->function_table, method_name.data(), method_name.size());
```

This method is an already-registered `zend_internal_function` whose handler ultimately enters the TypePHP-generated C++ getter/setter. The lookup happens only once; property reads/writes do not re-query the function table.

### 3.2 Creating the Hook function descriptor

The hidden function object in the class method table cannot be directly modified or reused. Zend Property Hooks require an independent function identity and property association:

```cpp
hook->function_name = "$name::get"; // or "$name::set"
hook->prop_info = property_info;
```

PHPX therefore copies a `zend_internal_function` descriptor and replaces the Hook-specific fields. The copy does not produce a second C++ implementation; the handler, argument info, and other persistent data still come from the original AOT method.

An independent function descriptor avoids breaking the class method table's key, reflection name, or ownership relationships when the hidden method is modified, and lets Reflection correctly report `$name::get` and `$name::set`.

### 3.3 Mounting the property Hook

PHP 8.4 added a Hook table to `zend_property_info`:

```cpp
property_info->hooks[ZEND_PROPERTY_HOOK_GET] = getter;
property_info->hooks[ZEND_PROPERTY_HOOK_SET] = setter;
```

The following must also be updated:

```cpp
ce->num_hooked_props++;
```

Zend's Reflection, object property construction, and inheritance checks all read this metadata. Registering only the hidden method without filling in `property_info->hooks` will not be recognized by Zend as a true Property Hook.

### 3.4 Installing the Hook object iterator

When the class has no custom iterator, PHPX sets:

```cpp
ce->get_iterator = zend_hooked_object_get_iterator;
```

`zend_hooked_object_get_iterator()` is the `ZEND_API` exported by PHP 8.4 in `zend_property_hooks.h`. PHP itself installs this iterator when compiling classes that contain Property Hooks.

The ordinary object iterator mainly traverses physical property slots, while the Hook iterator is also responsible for:

- calling getters for backed and virtual properties;
- skipping virtual properties without a getter;
- enforcing property visibility rules;
- rejecting unsupported by-reference traversal;
- merging dynamic properties.

Therefore PHPX should not duplicate a traversal implementation. Reusing Zend's exported implementation keeps `foreach` behavior consistent and reduces maintenance cost.

## 4. Virtual property

PHP 8.4 uses a special offset to represent a virtual property:

```cpp
#define ZEND_VIRTUAL_PROPERTY_OFFSET ((uint32_t) -1)
```

When Zend declares a property, it needs `IS_UNDEF` as the declaration value to establish a virtual offset for a property with `ZEND_ACC_VIRTUAL`. Therefore the generated code uses:

```cpp
zval default_value;
ZVAL_UNDEF(&default_value);
```

It must not be replaced with `null` or an ordinary default value, otherwise Zend may allocate a backing slot and `ReflectionProperty::isVirtual()` will return the wrong result.

## 5. Object introspection and serialization

When `ce->num_hooked_props` is nonzero, Zend's `zend_std_get_properties_for()` calls `zend_hooked_object_build_properties()` in scenarios such as JSON, `get_object_vars()`, and `var_export()`. That function reads the Hooked public property values.

Serialization uses different semantics:

- a virtual property has no persistent state and does not appear in the serialization result;
- a backed property serializes the backing value, not the value computed by the getter;
- private storage properties are still serialized according to PHP's property-name mangling rules.

This difference is existing PHP 8.4 behavior and must not be overridden just to make JSON and serialization output identical.

## 6. Lifetime and thread safety

TypePHP AOT classes are registered as persistent internal classes. The Hook table, Hook function descriptors, and function names must share the same process-level lifetime, so PHPX uses:

```cpp
pemalloc(size, true);
zend_string_init(data, length, true);
```

Request memory must not be used; otherwise the class entry would keep dangling pointers after RSHUTDOWN and the next request could crash when accessing properties or Reflection.

Registration happens only in MINIT:

- during request execution, Hook metadata is read-only;
- it does not need to be rebuilt on every request;
- it does not need to look up hidden methods on every property access;
- NTS has no locking overhead;
- under ZTS, registration completes before worker threads process requests, so the class entry is not concurrently modified.

## 7. PHP version boundary

The minimum version of both TypePHP and PHPX is PHP 8.4, so the Property Hook implementation directly uses the following PHP 8.4 ABI:

- `zend_property_info::hooks`;
- `zend_class_entry::num_hooked_props`;
- `ZEND_PROPERTY_HOOK_*`;
- `ZEND_PROPERTY_HOOK_STRUCT_SIZE`;
- `ZEND_VIRTUAL_PROPERTY_OFFSET`;
- `zend_hooked_object_get_iterator()`.

PHPX headers and CMake configuration reject headers/`php-config` below PHP 8.4. PHP 8.4 and 8.5 still build separate PHPX binaries; `--php-version` only controls source syntax and does not require exact minor-version parity with `libphp.so`, but both must be no lower than 8.4.

## 8. ABI risk and upgrade checks

`zend_hooked_object_get_iterator()` is an exported Zend API, but Property Hooks overall remain a version-dependent low-level Zend ABI. PHP 8.4 does not provide a complete high-level `zend_declare_property_hook()` extension API, so the current implementation must fill in Zend metadata.

The rationale for this approach is:

1. TypePHP and PHPX are version-locked and recompiled against specific PHP versions;
2. the registration flow matches the steps Zend's compiler performs for native Property Hooks;
3. only Zend's exported iterator is reused, without duplicating its complex implementation;
4. versions below PHP 8.4 are uniformly rejected at the build entry point;
5. all registration completes in MINIT, adding no name lookup to the request hot path.

When upgrading the PHP version, the following must be checked:

1. whether the Hook fields and ownership of `zend_property_info` changed;
2. whether `ZEND_PROPERTY_HOOK_COUNT` and Hook kinds increased;
3. whether virtual property declaration conditions and offsets changed;
4. whether `zend_hooked_object_get_iterator()` is still an exported API;
5. whether class linking, inheritance, variance, and Reflection added new required metadata;
6. whether the destruction and inheritance-copy rules for persistent internal functions changed.

If Zend later provides an official extension registration API, migration to that API should be prioritized to reduce direct dependence on internal structure layout.

## 9. Test requirements

Property Hook changes must at least cover:

- direct getter/setter and backing access;
- Reflection differences between virtual properties and backed properties;
- `hasHooks()`, `getHooks()`, Hook names, and final status;
- `get_object_vars()`, JSON, and object `foreach`;
- serialization containing only real stored state;
- dynamic Zend property reads/writes;
- inheritance and property visibility;
- PHP 8.4 and PHP 8.5 builds.

Current core regression tests are located at:

- `tests/compiler/object_property/property-hooks.phpt`;
- `tests/compiler/object_property/property-hooks-operations.phpt`;
- `tests/compiler/object_property/property-hooks-reflection.phpt`;
- `tests/compiler/object_property/property-hooks-introspection.phpt`.

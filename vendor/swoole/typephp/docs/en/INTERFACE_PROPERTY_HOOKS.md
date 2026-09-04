# Interface Property Hooks Implementation Plan

This document records the design and implementation plan for TP-AOT-010. The goal is to support the PHP 8.4 Interface Property Hook contract while preserving the zero-cost abstraction of TypePHP Native calls, and to give the PHP 8.4 ZendVM complete metadata for Reflection, dynamic class linking, and inheritance checks.

## Current status (2026-08-14)

The first stage has landed: the Interface contract model, AOT implementation checks, get/set direction variance, PHPX abstract Hook metadata, Reflection, dynamic PHP implementation classes, and regression tests are all wired up. Explicit setter parameter types are still rejected at compile time per the convention below; they will be opened up once the independent write-type model is completed.

## 1. Design conclusion

A Hooked Property in an Interface only represents a property contract:

```php
interface Named
{
    public string $name { get; set; }
}
```

- The Interface holds no property slot, generates no getter/setter implementation, and produces no contract check at access time.
- TypePHP verifies at compile time whether known AOT classes satisfy the property's visibility, type, and `get`/`set` capabilities.
- The PHP 8.4 target registers native Zend Hook metadata in MINIT so Reflection and dynamic PHP classes obtain the same contract.
- The minimum version of TypePHP, PHPX, and the final target runtime is PHP 8.4; no downgrade path is provided for older versions.

## 2. Syntax and diagnostics

Three kinds of contracts are supported:

```php
public string $readable { get; }
public string $writable { set; }
public string $readWrite { get; set; }
```

An Interface Property Hook must be `public`, non-`static`, have no default value, and its Hooks must not contain function bodies. Ordinary Interface properties, `private`/`protected`, `readonly`, duplicate or unknown Hooks, and Hooks with implementation bodies all throw a FatalError at TypePHP compile time. Error messages should match PHP 8.4 as closely as possible.

The first stage only accepts the implicit setter parameter:

```php
public string $name { set; }
```

PHP 8.4 also allows explicit, contravariant setter parameters such as `set(string|Stringable $value)`. That syntax requires the compile-time contract model and the Zend Hook `arg_info` to simultaneously store a write type independent of the property read type; until this part is complete, TypePHP reports a clear compile-time error and does not generate potentially incorrect runtime metadata.

## 3. Compiler model

An Interface Property Hook must not be disguised as an ordinary property or as an ordinary method after lowering. A separate contract model is established, storing at least:

- the property name and declaration node;
- the resolved TypePHP type and class type;
- whether `get` is required;
- whether `set` is required;
- visibility and other flags used for diagnostics.

The contract is stored in `InterfaceDef`. The AST/preprocessing stage only collects and validates declarations; it does not allocate property slots for Interfaces, does not run the `PropertyHookLowering` used by concrete classes, and does not generate hidden methods.

Contract linking is performed after all types finish preprocessing: parent Interface contracts are expanded, then the properties provided by the implementing class itself or its parent are checked. An ordinary public backed property satisfies both the read and write contracts; a Hooked Property is judged by its actual Hook capabilities. get-only types are covariant in the read direction, set-only types are contravariant in the write direction, and types containing both get and set remain invariant.

## 4. PHPX and Zend metadata

The existing `typephp_register_property_hooks()` is for concrete classes with real AOT getters/setters and cannot be reused for abstract Interface Hooks.

PHPX adds a separate helper:

```cpp
typephp_register_abstract_property_hooks(
    zend_class_entry *interface_ce,
    zend_property_info *property_info,
    bool readable,
    bool writable
);
```

TypePHP/PHPX already uniformly require PHP 8.4+, so this helper directly accesses the PHP 8.4 ABI and is responsible for:

- persistently allocating `zend_property_info::hooks`;
- creating abstract `get`/`set` `zend_internal_function` metadata without handlers;
- setting `ZEND_ACC_PUBLIC | ZEND_ACC_ABSTRACT`, the correct parameter/return types, and `common.prop_info`;
- updating `num_hooked_props` so Zend inheritance and Reflection recognize the contract;
- ensuring all strings, Hook tables, and function descriptors have MINIT-level persistent lifetimes.

The generated code first registers the Interface, then declares the property with `IS_UNDEF` and `ZEND_ACC_PUBLIC | ZEND_ACC_ABSTRACT | ZEND_ACC_VIRTUAL` and mounts the abstract Hooks, and finally registers and links the implementing classes.

## 5. PHP version boundary

TypePHP distinguishes the source language version from the linked runtime:

- `--php-version` allows only `8.4` or `8.5` and is used to parse syntax and handle project conditions;
- PHPX headers, `libphp`, and the final runtime must be PHP 8.4 or higher;
- `--php-version` and `libphp.so` are not required to have exactly matching minor versions — for example, when using the 8.5 syntax mode and linking PHP 8.4, whether the final build succeeds is still determined by the Zend APIs actually used;
- environments below PHP 8.4 are rejected directly at the TypePHP/PHPX build entry point.

## 6. TDD coverage

Add failing tests before implementation, covering:

1. get-only, set-only, and get/set Interface contracts;
2. ordinary backed properties, Hooked Properties, and inherited properties satisfying the contract;
3. compile errors for missing properties, missing get/set, non-public, and incompatible types;
4. Interface inheritance, merging of multiple contracts, and conflicts;
5. Reflection abstract, virtual, hasHook/getHook metadata;
6. success and failure linking of PHP 8.4 dynamic PHP classes;
7. consistent O0/O3 results, with Interfaces generating no property slots or Native Hook implementations;
8. lifetime and ABI regression of the PHPX helper under NTS/ZTS and PHP 8.4/8.5.

## 7. Implementation order

1. Add TP-AOT-010 normal-scenario and syntax-error PHPT and confirm the current failures.
2. Add the Interface Property Contract model and preprocessing collection logic.
3. Implement Interface inheritance and compile-time contract checking for implementing classes.
4. Add the abstract Hook metadata helper in PHPX.
5. Modify stub generation and class registration order to wire into the PHP 8.4 Zend metadata.
6. Add Reflection, dynamic class linking, target version, and generated-code tests.
7. Run the Interface, Property Hook, Reflection, and full compiler regression.

After completion, runtime property access still goes directly into the implementing class's ordinary properties or Native Hooks; the Interface contract itself exists only in the compile-time model and MINIT metadata, and does not enter the request hot path.

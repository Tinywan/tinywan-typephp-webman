# AOT Compiler Core Refactoring Plan

> For the next-phase OOA/OOD/OOP refactoring of `Translator`, `CompilerBase`, and `Preprocessor`, please use [CORE_OOA_OOD_OOP_REFACTORING_PLAN.md](CORE_OOA_OOD_OOP_REFACTORING_PLAN.md) as the implementation baseline. This document retains the historical plan of the earlier modularization refactoring.

## Background

The current core classes of the AOT compiler carry too many responsibilities. In particular, classes such as `CompilerBase` and `Translator` simultaneously contain AST dispatch, type inference, property access resolution, call resolution, code generation, diagnostics, and context state maintenance. As functionality continues to grow, this structure causes the following problems:

- Insufficient encapsulation: modifying one semantic point easily affects multiple code paths.
- Insufficient code reuse: similar logic is repeatedly implemented across normal properties, static properties, nullsafe, assignment, isset/empty/refval, and other paths.
- Compile-time checks are prone to bypass paths: for example, some dynamic fallbacks do not reuse the static resolver.
- Individual classes are too large, and review, test localization, and long-term maintenance costs keep rising.
- Design boundaries are unclear: the type system, symbol resolution, property access, and call generation are too deeply coupled.

This plan guides the subsequent incremental refactoring, aiming to improve architectural quality while avoiding the behavior regression risk of a large-scale one-shot rewrite.

## Core Principles

1. Incremental refactoring; a one-shot rewrite of the core compilation flow is forbidden.
2. Prioritize extracting pure logic with no or low state dependency, then logic that depends on the compilation context.
3. Each refactoring step should keep behavior unchanged as much as possible; behavior changes must be explained separately and covered by tests.
4. New modules should be modeled around a stable domain, not mechanically split by AST node.
5. Prefer small services/helpers, explicit DTOs, resolvers, and emitters; introduce patterns such as Visitor and Strategy only after boundaries stabilize.
6. All error messages remain PHP-style, do not expose implementation details, and do not use expressions like "AOT forbids".
7. Each phase must have phpunit or phpt regression verification, especially for high-risk paths such as properties, types, calls, inheritance, and exceptions.
8. Compiler interfaces exposed to resolver/emitter should preferably remain read-only. Read-only query interfaces can be made public as needed, but write operations must be handled with extra care to avoid bypassing unified state management.

## Target Architecture Direction

### CompilerBase

It should ultimately converge into the compilation context, common utilities, AST dispatch entry point, and cross-module collaboration layer, no longer directly carrying a large amount of domain logic.

Responsibilities to retain:

- Current file, function, class, and method context management.
- Temporary variable, local variable, and scope state maintenance.
- Top-level AST dispatch entry point.
- Unified entry point for fatal/warning.
- Collaboration with lower-level resolver/emitter.

Responsibilities to gradually move out:

- Type declaration parsing and type compatibility determination.
- Property visibility, property offset, and typed property checks.
- Function and method call resolution.
- Complex expression code generation.
- Union/intersection/nullable runtime typecheck generation.

### Translator

Retain file-level, class-level, and function-level compilation flow control, and gradually reduce specific semantic checking and expression generation logic.

Responsibilities to retain:

- File scanning and translation entry point.
- Class, function, and method code block generation flow.
- class/function/constant/property metadata registration.
- Compilation artifact organization.

Responsibilities to gradually move out:

- Detail logic of inheritance compatibility checks.
- Trait property conflict check details.
- Parameter, return value, and property type compatibility determination.

## Module Splitting Plan

### 1. TypeSystem

Responsibilities:

- Type declaration parsing.
- Mapping from PHP types to AOT internal types.
- Nullable, union, and intersection type expansion.
- Type compatibility determination for parameters, return values, properties, and constants.
- Boundary definition between static types and runtime typecheck.
- Type string formatting.

Recommended submodules:

- `TypeResolver`
- `TypeCompatibility`
- `TypeCheckEmitter`
- `TypeStringFormatter`

Design requirements:

- union, intersection, and nullable can still be treated as mixed/any in the static phase, but runtime typecheck information must be preserved.
- The resolution rules for special type names such as `self`, `parent`, and `static` must be centralized to avoid inconsistent implementation across multiple paths.
- Type error messages must include necessary context such as function, method, parameter, and property.

### 2. SymbolResolver

Responsibilities:

- Namespace resolution.
- use alias resolution.
- `self`, `parent`, `static` resolution.
- Class, interface, trait, function, and constant name normalization.
- Boundary determination between dynamic symbols and statically resolvable symbols.

Recommended interfaces:

- `resolveClassName(NodeAbstract $node): SymbolResolution`
- `resolveFunctionName(NodeAbstract $node): SymbolResolution`
- `resolveMethodScope(NodeAbstract $node): SymbolResolution`
- `resolveClassConstScope(NodeAbstract $node): SymbolResolution`

Design requirements:

- Different call paths must not reimplement the `self/parent/static` rules.
- For symbols that cannot be statically determined, explicitly return a dynamic state instead of silently degrading to string concatenation.

### 3. PropertyAccessResolver

Responsibilities:

- Object property access resolution.
- Static property access resolution.
- Nullsafe property access static checking.
- private/protected/public visibility checking.
- Native property offset lookup.
- Entry point for typed property write typecheck information generation.
- Static-vs-instance property misuse checking.

Recommended interfaces:

- `resolveInstancePropertyAccess(PropertyAccessRequest $request): PropertyAccessResult`
- `resolveStaticPropertyAccess(StaticPropertyAccessRequest $request): PropertyAccessResult`
- `assertReadable(PropertyAccessResult $result): void`
- `assertWritable(PropertyAccessResult $result): void`
- `emitRead(PropertyAccessResult $result): string`
- `emitWrite(PropertyAccessResult $result, string $value): string`

Paths that need unified coverage:

- `$obj->prop`
- `$obj?->prop`
- `Class::$prop`
- `self::$prop`
- `parent::$prop`
- `static::$prop`
- `isset($obj->prop)`
- `empty($obj->prop)`
- `refval($obj->prop)`
- normal assignment, compound assignment, increment/decrement, unset.

It is recommended to start from this module as the first priority, because recent problems are concentrated in property access and visibility bypass, and the test boundaries are relatively clear.

### 4. CallResolver

Responsibilities:

- Function call resolution.
- Object method call resolution.
- Static method call resolution.
- Native call vs. dynamic call selection.
- Named args, unpack, and by-ref parameter handling.
- Closure and dynamic callable degradation rules.

Recommended interfaces:

- `resolveFunctionCall(CallRequest $request): CallResolution`
- `resolveMethodCall(MethodCallRequest $request): CallResolution`
- `resolveStaticCall(StaticCallRequest $request): CallResolution`
- `emitCall(CallResolution $resolution): string`

Design requirements:

- When the parameter information of a static function or built-in function is clear, references can be automatically converted.
- For dynamic calls, closures, and cases where by-ref parameter information cannot be obtained at compile time, an explicit `refval()` must be required.
- When using unpack with trailing named args appended, it should degrade to a dynamic call and must not go through a native call.

### 5. ExpressionEmitter

Responsibilities:

- Expression-level code generation.
- Gradually split the large `parseExpr()` dispatch logic.
- Reuse resolver results to generate C++ code.

It is recommended to split by domain rather than starting with a large number of AST visitors:

- `AssignmentEmitter`
- `PropertyEmitter`
- `CallEmitter`
- `ArrayEmitter`
- `ControlExprEmitter`
- `ObjectEmitter`

Design requirements:

- Keep the existing `parseExpr()` as the dispatch entry point for now.
- Migrate only one group of expressions at a time, and run the corresponding test group after each migration.
- Handle expression side effects, evaluation order, and temporary variable generation conservatively.

### 6. Diagnostic

Responsibilities:

- Unified fatal/warning construction.
- Provide context enhancement capability.
- Ensure error message style is close to PHP.

Recommended capabilities:

- Current function/method name.
- Parameter name.
- Property name and class name.
- Source location.
- Declaration location and usage location.

Design requirements:

- Error messages must not use "AOT" as the actor.
- For user-fixable problems, accurate symbol names should be included.
- Problems discoverable at compile time should preferably be compile-time fatal, and should not rely on runtime typecheck exceptions as a fallback.

## Phase Plan

### Phase 1: Property Access Resolution Modularization

Objectives:

- Extract property access resolution logic such as `findNativeProperty()`, `findNativeStaticProperty()`, `canAccessProtectedProperty()`.
- Keep generated code essentially unchanged.
- Establish a unified `PropertyAccessResult` carrying information such as property declaration, declaring class, accessing class, whether native, offset, and whether dynamic.

Scope:

- Normal object property reads.
- Static property reads.
- Nullsafe property static checking.
- Property visibility checking.

Current progress:

- Established `PropertyAccessResolver` and `PropertyAccessResult` as the first abstraction layer for property access resolution.
- Instance property reads now complete native property lookup, static-vs-instance checking, and visibility checking through the resolver's explicit `resolveNativeInstanceProperty()` interface.
- `findNativeStaticProperty()` now completes static property checking through the resolver's explicit `resolveNativeStaticProperty()` interface.
- Nullsafe property chain checking now completes class-name advancement and visibility checking through the resolver's explicit `resolveNullsafePropertyChain()` interface.
- The old generic `CompilerBase::findNativeProperty()` entry point has been removed to prevent further spread of access patterns carrying the `$static` boolean parameter.
- The old `findNativeStaticProperty(..., &$class)` by-ref protocol has been removed; static property reads now use explicit DTOs `StaticPropertyFetchTarget` and `StaticPropertyFetchResolution`.
- Target class resolution for instance property reads has been extracted to `InstancePropertyFetchTarget`; `getPropertyIdentifier()` no longer mixes target resolution, resolver calls, and dynamic fallback branches.
- The `nativeProperty`, `nativePropertyDef`, and `nativeClassDef` previously scattered on AST attributes have been merged into the `NativePropertyAccess` metadata to avoid inconsistent state among the three.
- Direct reads and writes of `nativePropertyVar`, `nativePropertyValueSource`, `objectProps`, and `staticPropRefs` have been converged into helper methods; business paths no longer determine property access semantics by string content.
- Typed instance property hoist and typed static property ref registration have been extracted into independent helpers, currently still keeping the original generated code structure.
- `CompilerBase::isSameClassName()`, `isSameOrSubclassOf()`, and `canAccessProtectedProperty()` have been delegated to the resolver to avoid further rule spread.
- Added `prepare/convert/idle` compilation phase states; `PropertyAccessResolver` can only be created and used in the convert phase to avoid misusing incomplete class table state in the preprocessing phase.
- `PropertyAccessResolver` has been changed to depend on the read-only `PropertyAccessContext` interface instead of fully depending on the large `CompilerBase` class.
- Established `PropertyAssignTypeInfo`, extracting the pure metadata computation for typed property writes, including fixed-type property determination, default values, the runtime typecheck list, and type strings.
- The current migration keeps generated code unchanged; read/write emitters will be unified in subsequent phases.

Status:

- Phase 1 is essentially wrapped up. Unless property read resolver bypass or behavior regression is found later, the scope of Phase 1 will not be further expanded.
- Phase 2 has begun; assignment, compound assignment, inc/dec, unset, and refval paths related to property writes still need to be further unified.

Verification:

- `phpunit/src/NativePropertyTest.php`
- `phpunit/src/InheritanceErrorTest.php`
- object property related phpt.
- static property related phpt.
- nullsafe related phpt.

### Phase 2: Property Write Path Unification

Objectives:

- All property write paths resolve first, then emit.
- Typed property runtime typecheck converges from scattered logic into the property write module.
- Eliminate the problem where assignment, compound assignment, inc/dec, and unset each implement property access rules independently.

Scope:

- `$obj->prop = $value`
- `$obj->prop += $value`
- `$obj->prop++`
- `unset($obj->prop)`
- `Class::$prop = $value`
- `??=` related property paths.

Verification:

- typed property related phpunit/phpt.
- object property optimization related phpt.
- nullsafe write context error tests.
- private/protected/static property error tests.

Current progress:

- Phase 2 has begun.
- Established `PropertyWriteTarget` as the minimal target DTO for the property write path.
- Normal assignment and `??=` have been connected to `preparePropertyWriteTarget()`, uniformly completing property target preparation before writes, and executing static checks and runtime typecheck wrapping through `assertCanAssignPropertyWrite()` and `wrapPropertyWriteTypeCheck()`.
- `getProperty()` / `setProperty()` generation for dynamic object properties has been converged into the `emitDynamicPropertyRead()` / `emitDynamicPropertyWrite()` helpers; normal dynamic property assignment, compound assignment, and increment/decrement now reuse this entry point.
- The dynamic property path of compound assignment has been connected to `preparePropertyWriteTarget()`, uniformly completing property write target preparation and static checks first.
- `PropertyWriteTarget` has begun carrying the object/property expressions of safe dynamic property write targets; normal dynamic property assignment, compound assignment, and increment/decrement now prefer emitting code through target-level read/write helpers.
- Dynamic property `unset`, property array dimension writes, and safe object property reference paths in reference arguments/refval/reference assignment have begun reusing target-level unset/ref helpers.
- Target/ref generation for object property reference expressions has been converged into `emitDynamicPropertyFetchRef()`; the unused old static property assignment entry point has been deleted, and static property assignment continues through the unified assignment target path.
- The dynamic object/property fields of `PropertyWriteTarget` have been encapsulated as getters; property array dimension writes have been connected to target-level append/update emitters.
- Established the `emitDynamicPropertyFetchRead/Write/Unset/AppendArray/UpdateArray()` wrapper layer; callers only pass in the property access AST and an optional target, and `CompilerBase` uniformly selects the target path or the old fallback path.
- Normal assignment, compound assignment, increment/decrement, unset, property array dimension writes, and reference assignment have removed the direct branch determination of dynamic targets in the Parser trait, instead reusing the unified emitter wrappers.
- To avoid changing the evaluation order of complex expressions, currently only dynamic property writes whose object part is a variable have their target object/property fields populated; complex object expressions still retain the old path.
- The current step keeps generation logic compatible for valid code, but will route more property write paths into unified static checking; continue converging static/native property write emitters and `??=` property write result generation.

### Phase 3: Type System Modularization

Objectives:

- Extract type declaration parsing, type compatibility, and runtime typecheck generation.
- Clarify the responsibility boundary between static type inference and runtime typecheck.
- Reduce the repeated handling of type rules across parameters, return values, properties, and constants.

Scope:

- `parseTypeDecl()`.
- `buildTypeCheckFromNode()`.
- Parameter typecheck.
- Return value typecheck.
- Property typecheck.
- Constant type handling.

Verification:

- union, intersection, nullable type tests.
- Parameter, return value, property typecheck tests.
- namespace constant tests.
- constructor, void expression related tests.

### Phase 4: Call Resolution Modularization

Objectives:

- Unify function, method, and static method call resolution.
- Clarify degradation rules for native calls, dynamic calls, and closure calls.
- Centrally handle named args, unpack, and by-ref parameters.

Scope:

- `parseFuncCall()`.
- `parseMethodCall()`.
- `parseStaticCall()`.
- call args parsing.
- native/internal/user function call paths.

Verification:

- named args, unpack related phpt.
- by-ref parameter related phpt.
- closure related phpt.
- parent/self/static call related phpt.

### Phase 5: Expression Generator Splitting

Objectives:

- Migrate the large amount of expression generation logic behind `parseExpr()` to domain emitters.
- `CompilerBase` retains dispatch and shared context capabilities.
- Reduce the code size of individual files and classes.

Scope:

- AssignmentEmitter.
- PropertyEmitter.
- CallEmitter.
- ArrayEmitter.
- ControlExprEmitter.

Verification:

- Run the corresponding test group after each emitter migration.
- Finally run the core phpunit and selected phpt regression set.

### Phase 6: Translator Convergence

Objectives:

- Extract inheritance compatibility, trait merging, and class member validation logic into dedicated checkers.
- `Translator` focuses on compilation flow organization.

Recommended submodules:

- `InheritanceChecker`
- `TraitCompositionChecker`
- `ClassMemberValidator`
- `FunctionSignatureChecker`

Verification:

- inheritance error phpunit.
- trait related phpt.
- interface/abstract/final/readonly related phpt.

## Test Gate

Each refactoring PR or phase must at least satisfy:

- Relevant phpunit must pass.
- Relevant phpt must pass.
- If C++/phpx is modified, gtest must be supplemented and the corresponding tests must pass.
- If a new compile-time error is introduced, a fixed fixture must be added to `phpunit/code` or a new phpt must be added.
- Do not use `file_put_contents()` to temporarily generate source code as a new test method.

It is recommended to maintain a minimal regression set per module:

- Property module: `NativePropertyTest`, `InheritanceErrorTest`, object property, static property, nullsafe.
- Type module: type_decl, type_hits, typed property, union/intersection/nullable.
- Call module: function call, method call, parent_call, closure, named args, unpack, by-ref.
- Control flow and expressions: ternary, match, goto, loop, array, coalesce, void expression.

## Risk Control

- Do not perform large-scale file movement and behavior changes in the same change.
- Supplement tests for current behavior before each migration, especially the paths corresponding to historical bugs.
- Keep old entry points for a period, calling new modules through adapters to reduce switching risk.
- Remain conservative about dynamic PHP semantics; do not over-optimize when it cannot be statically determined.
- For places where AOT is clearly incompatible with PHP's historical baggage, express them as language rules in documentation and error messages, not as implementation limitations.

## Recommended Next Step

Start from `PropertyAccessResolver`.

Reasons:

- Recent bugs are mostly concentrated in property access, visibility, typed property, nullsafe, and static-vs-instance paths.
- Existing tests are relatively easy to extend.
- Property access is a relatively independent domain outside the type system, optimizer, and call generation, making it suitable for establishing the resolver/result pattern first.
- After completion, it can directly reduce the complex branches in `CompilerBase` and lay the foundation for subsequent ExpressionEmitter splitting.

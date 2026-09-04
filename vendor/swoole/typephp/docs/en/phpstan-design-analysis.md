# PHPStan Design Analysis: Patterns and Modules Portable to the AOT Compiler

This document analyzes the architecture of the PHPStan project (`projects/phpstan-src/`) and identifies design patterns and modules that can be introduced into the AOT compiler.

---

## Overview: Migratable Designs Across Four Priorities

| Priority | Design / Module | Implementation Size | Prerequisites | Benefit |
|--------|----------|---------|---------|------|
| P1 | Type object hierarchy (#1) | ~1000 lines | None | Qualitative leap in type inference precision |
| P1 | TrinaryLogic (#2) | ~80 lines | None | Type queries no longer return wrong answers |
| P1 | TypeCombinator (#3) | ~300-400 lines | #1 | Eliminate scattered type string concatenation |
| P2 | Rule system (#4) | Interface + Registry ~80 lines | Mostly independent | Architectural modularity, easy to test and extend |
| P2 | Collector two-phase analysis (#5) | ~200 lines | #4 | Cross-file global optimization |
| P2 | Extension registration mechanism (#6) | ~100 lines | #4 | Framework-level plugin system |
| P3 | TypeSpecifier / type narrowing (#7) | ~200 lines | #1, #2 | if/else branch type refinement |
| P3 | Immutable Scope (#8) | ~300 lines | #1, #2 | Expression-level type tracking |
| P4 | PHPDoc Pipeline (#9) | Reuse phpstan/phpdoc-parser | #1 | Leverage existing mature parser |
| P4 | NeverType (#10) | Contained in #1 | #1 | Dead code elimination, contradictory type detection |

---

## 1. Object-Oriented Type System (Replacing String-Based Type Representation)

### Current State

The AOT compiler represents types with string constants:

```php
const TYPE_INT = 'int';
const TYPE_FLOAT = 'float';
const TYPE_STR = 'string';
// Compound types use string concatenation
// 'int|string', 'string|null'
```

This forces type operations (merging, comparison, querying) to manually parse and concatenate strings.

### PHPStan's Approach

PHPStan has a `Type` interface defining roughly 100 methods. Each type is a class:

```
Type (interface)
├── StringType
├── IntegerType
├── FloatType
├── BooleanType
├── NullType
├── MixedType        (top type, with subtracted type support)
├── NeverType        (bottom type, no possible values)
├── VoidType
├── ArrayType
├── ObjectType
├── CallableType
├── UnionType        (A|B|C, holding a flat list<Type>)
├── IntersectionType (A&B&C)
├── ConstantStringType, ConstantIntegerType, ...
├── IntegerRangeType
└── Accessory*Type   (for refinement inside IntersectionType)
```

**Core design principle: never use `instanceof` to determine type identity.** You must use `is*()` methods:

```php
// Wrong — misses UnionType and IntersectionType
$type instanceof StringType

// Correct — UnionType delegates to inner types and combines the results
$type->isString()->yes()
```

**Key sub-interfaces:**

- `CompoundType`: marks types that need two-way type comparison (UnionType, IntersectionType). Adds `isAcceptedBy()`, `isSubTypeOf()` methods, implementing a double-dispatch protocol.
- `SubtractableType`: supports set difference operations (e.g. `mixed~null`).

### The isSuperTypeOf / accepts Double-Dispatch Protocol

This is the most central design pattern of PHPStan's type system:

```
Simple type (StringType)::isSuperTypeOf(Type $otherType):
  if $otherType is a CompoundType:
    return $otherType->isSubTypeOf($this)   // reverse delegation
  // own logic ...
  return No

Compound type (UnionType)::isSubTypeOf(Type $otherType):
  foreach innerType in $this->types:
    results[] = $otherType->isSuperTypeOf(innerType)
  return extremeIdentity(results)  // ALL must be subtype
```

**Key point:** simple types do not need to understand compound type semantics. Adding a new compound type does not require modifying any simple type's comparison logic.

### AOT Adoption Recommendation

The AOT compiler does not need PHPStan's full complexity. The first version only needs these concrete types:

```
IntegerType, FloatType, StringType, BoolType,
NullType, MixedType, NeverType, ArrayType, ObjectType, UnionType
```

Not needed: IntersectionType, AccessoryType, TemplateType, Constant*Type, IntegerRangeType, EnumType.

---

## 2. TrinaryLogic (Three-Valued Logic)

### Current State

The AOT compiler uses boolean to determine type properties. But the `mixed` type means "could be string, could be int", and boolean cannot express this uncertainty.

### PHPStan's Approach

```php
class TrinaryLogic {
    public function yes(): bool;
    public function no(): bool;
    public function maybe(): bool;

    public static function createYes(): self;
    public static function createNo(): self;
    public static function createMaybe(): self;

    public function and(self ...$others): self;
    public function or(self ...$others): self;
    public function extremeIdentity(self ...$others): self; // ALL yes → yes; ALL no → no
    public function maxMin(self ...$others): self;         // ANY yes → yes; ALL no → no
}
```

Usage example:

```php
// MixedType::isString() → maybe (mixed may be string)
// IntegerType::isString() → no
// UnionType(int|string)::isString() → maybe

$type->isString()->yes()   // definitely a string
$type->isString()->no()    // definitely not a string
$type->isString()->maybe() // uncertain
```

### AOT Adoption Recommendation

Port directly, about 80 lines of code, no external dependencies. Replace boolean with TrinaryLogic for all type query methods.

---

## 3. TypeCombinator — The Type Normalization Engine

### Problem to Solve

Forbid directly calling `new UnionType(...)`. All union/intersect/remove operations must go through TypeCombinator to ensure the type representation is always normalized.

### Three Core Operations

#### `union(Type ...$types): Type`

**Algorithm flow:**

1. **Fast path**: 0 arguments → `NeverType`; 1 argument → return directly; 2 arguments check `never`/`mixed`/identical objects
2. **Flattening**: `union(A, union(B, C), D)` → expands to `[A, B, C, D]`
3. **Filter NeverType**: `union(int, never, string)` → `[int, string]`
4. **Category extraction**: divide types into scalar/array/enum/integerRange/generic five categories, process in batches
5. **Scalar resolution**: `ConstantIntegerType(3) | IntegerType` → `IntegerType`; `true | false` → `BooleanType`
6. **Pairwise comparison**:
   - Adjacent `IntegerRangeType` intervals merge: `int<0,5> | int<3,10>` → `int<0,10>`
   - Subtype/supertype elimination: `Foo extends Bar` ⇒ `Foo | Bar` = `Bar`
   - `int[] | string[]` → `(int|string)[]`
7. **Wrap-up**: 0 → NeverType; 1 → return directly; otherwise `new UnionType(array_values($types), true)`

#### `intersect(Type ...$types): Type`

**Algorithm flow:**

1. If there is a `NeverType` → return never directly
2. **Distributive expansion**: `A & (B|C)` → `(A&B) | (A&C)`, then recurse on each term
3. **Flattening**: `A & (B & C)` → expands to `[A, B, C]`
4. **Pairwise two-way comparison**:
   - `IntegerType & ConstantIntegerType(5)` → `ConstantIntegerType(5)` (Child & Parent = Child)
   - `int & string` → `NeverType` (contradiction)
   - SubtractableType difference handoff
5. **Contradiction detection**: `isSuperTypeOf` returns `no` → `NeverType`

#### `remove(Type $fromType, Type $typeToRemove): Type`

```
remove(int|string, string)  = int
remove(int|string|null, null) = int|string
remove(int, string)         = int          // what to remove is not present
remove(string, string)      = never        // fully removed
remove(mixed, Foo)          = mixed~Foo    // difference type
```

### AOT Adoption Recommendation

A simplified TypeCombinator (~300-400 lines) covering union/intersect/remove for the basic types AOT needs. PHPStan's complex array shape handling, accessory type propagation, IntegerRange merging, etc. are not needed.

### Benefits

| Scenario | Current | After TypeCombinator |
|------|------|-------------------|
| Variable assignment of `int\|int` | String `'int\|int'` | Automatically simplified to `IntegerType` |
| Return value of `mixed\|string` | Does not know how to simplify | Automatically simplified to `MixedType` |
| Merging two branch types | Manual concatenation | `union()` automatically dedupes and removes subtypes |
| `int & string` intersection | Cannot detect | Returns `NeverType` (compile error) |

---

## 4. Rule-Based Analysis Pass System

### Core Interface

```php
/**
 * @template TNodeType of Node
 */
interface Rule
{
    /** @return class-string<TNodeType> */
    public function getNodeType(): string;

    /** @param TNodeType $node */
    public function processNode(Node $node, Scope $scope): array;
}
```

Each Rule declares which AST node type it cares about and what to do when that node is found.

### Registration Mechanism

Declare level via PHP 8 Attribute:

```php
#[RegisteredRule(level: 0)]
final class CallMethodsRule implements Rule
{
    public function getNodeType(): string { return MethodCall::class; }
    public function processNode(Node $node, Scope $scope): array { ... }
}
```

### Registry Implementation

`LazyRegistry` collects all services tagged `phpstan.rules.rule` from the DI container and indexes them by the `getNodeType()` return value.

**Key design:** `getRules($nodeType)` matches not only the exact class name, but also all parent classes and interfaces:

```php
public function getRules(string $nodeType): array
{
    // $nodeType = MethodCall::class
    // parentNodeTypes = [MethodCall, Expr, NodeAbstract, Node, ...]
    // match all Rules registered on these parent classes/interfaces
    $parentNodeTypes = [$nodeType] + class_parents($nodeType) + class_implements($nodeType);
    // ...
}
```

This means a rule registered for `Node\Expr` matches **all** expression types.

### Runtime Dispatch

At every node during AST traversal:

```php
$nodeType = get_class($node);
foreach ($this->ruleRegistry->getRules($nodeType) as $rule) {
    $ruleErrors = $rule->processNode($node, $scope);
    // transform and collect errors ...
}
```

Extremely concise — no switch, no if-else chains.

### Collector Two-Phase Analysis

The Collector interface is almost identical to Rule, but returns collected data (instead of errors):

```php
interface Collector
{
    public function getNodeType(): string;
    /** @return TValue|null */
    public function processNode(Node $node, Scope $scope);
}
```

**Phase 1 (per-file):** as each file is analyzed, the collector gathers data.

**Phase 2 (global):** after all files are analyzed, create a `CollectedDataNode` wrapping all data and run the rules registered on it:

```php
$node = new CollectedDataNode($analyserResult->getCollectedData(), $onlyFiles);
foreach ($this->ruleRegistry->getRules(CollectedDataNode::class) as $rule) {
    $ruleErrors = $rule->processNode($node, $scope);
}
```

`CollectedDataNode::get(string $collectorType): array<string, list<TValue>>` indexes collected data by file path.

### Concrete Application in the AOT Compiler

| Rule | Trigger Node | Purpose |
|------|----------|------|
| `BinaryOpCodegenRule` | `Expr\BinaryOp` | Generate C++ operation code |
| `MethodCallCodegenRule` | `Expr\MethodCall` | Method call code generation, virtual/direct call determination |
| `TypeCheckInsertRule` | `Param` / `Return_` | Insert runtime type checks at function entry/exit |
| `DeadCodeEliminateRule` | `CollectedDataNode` | Cross-file analysis, remove uncalled functions |
| `DevirtualizeRule` | `CollectedDataNode` | Single-implementation virtual methods → direct calls |
| `InlineDecisionRule` | `CollectedDataNode` | Decide inlining based on call frequency and function size |
| `ConstantFoldRule` | `Expr\BinaryOp` | Compile-time constant folding |
| `BoxOptimizationRule` | `Expr\Assign` | Box escape analysis for std containers |

**O0/O1/O2 tiers:**

```php
#[RegisteredRule(level: 0)]  // basic code generation, always required
class BinaryOpCodegenRule implements Rule { ... }

#[RegisteredRule(level: 1)]  // O1 optimization
class ConstantFoldRule implements Rule { ... }

#[RegisteredRule(level: 2)]  // O2 aggressive optimization
class InlineDecisionRule implements Rule { ... }
```

### AOT Adoption Recommendation

Progressive migration, no need to rewrite the entire compiler at once:

1. Define the `Rule` interface and `Registry` (about 80 lines of code)
2. Extract one standalone function of `parseStmts()` as the first Rule
3. Migrate the remaining switch branches step by step
4. Introduce `Collector` + `CollectedDataNode` for cross-file optimization

The Rule system can coexist with the existing switch dispatch — let Rules first handle the nodes they can, and fall back to the original logic for the rest.

---

## 5. Extension Registration Mechanism

### PHPStan's Approach

PHPStan has dozens of extension interfaces, registered through the DI container's service tag mechanism:

```
DynamicMethodReturnTypeExtension → tag: phpstan.broker.dynamicMethodReturnTypeExtension
FunctionTypeSpecifyingExtension  → tag: phpstan.typeSpecifier.functionTypeSpecifyingExtension
TypeNodeResolverExtension        → tag: phpstan.phpdoc.typeNodeResolverExtension
MethodsClassReflectionExtension  → tag: phpstan.broker.methodsClassReflectionExtension
PropertiesClassReflectionExtension → tag: phpstan.broker.propertiesClassReflectionExtension
...
```

Extensions are called before the core logic and get a chance to override the default behavior:

```php
public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
{
    foreach ($this->extensions as $extension) {
        $type = $extension->resolve($typeNode, $nameScope);
        if ($type !== null) {
            return $type;  // extension handled it, short-circuit core logic
        }
    }
    // core logic ...
}
```

### AOT Application

Framework-specific compilation optimizations can be provided through extension plugins instead of modifying the compiler core:

```php
interface MethodCallOptimizationExtension
{
    /** Returns optimized C++ code, or null to indicate no handling */
    public function optimize(MethodCall $call, Scope $scope): ?string;
}
```

---

## 6. TypeSpecifier — The Type Narrowing Engine

### Problem Solved

When the AOT compiler encounters `if ($x instanceof Foo)`, inside the branch `$x`'s type should be narrowed to `Foo`. This enables generating more efficient C++ code (directly calling Foo's methods without going through the vtable).

### PHPStan's Approach

`TypeSpecifier` analyzes conditional expressions and decides how to narrow types in the truthy/falsy branches:

| Condition | Truthy narrows to | Falsey narrows to |
|------|-------------|--------------|
| `$x instanceof Foo` | `Foo` | Remove `Foo` |
| `$x === null` | `NullType` | Remove `NullType` |
| `is_array($x)` | `ArrayType` | Remove `ArrayType` |
| `$x` (truthy) | Remove falsey types | Keep only falsey types |
| `$x > 0` | `int<1, max>` | `int<min, 0>` |

### Type Narrowing Pipeline

```
Conditional expression
  → TypeSpecifier::specifyTypesInCondition(scope, expr, context)
  → SpecifiedTypes { sureTypes[], sureNotTypes[] }
  → MutatingScope::filterBySpecifiedTypes(types)
  → new scope (types already narrowed)
```

### AOT Application

A simplified TypeSpecifier (~200 lines) handling:
- `instanceof` narrowing
- `=== null` / `!== null` narrowing
- `is_array()`, `is_string()`, `is_int()` and other function narrowing
- `BooleanAnd`/`BooleanOr` chained narrowing

---

## 7. Immutable Scope

### Current State

AOT's `FunctionContext` uses mutable public arrays and tracks types only by variable name:

```php
class FunctionContext {
    public array $localVars = [];          // only variable name → type
    public int $scopeLevel = 0;            // simple nesting counter
    public array $scopeLayouts = [];       // ScopeContext is an empty class
    public bool $inLoop = false;
    public bool $inClosure = false;
}
```

### PHPStan's Approach

`MutatingScope` is an **immutable** persistent data structure. Every change returns a new instance:

```
scope.assignVariable('x', stringType)        → new scope
scope.filterByTruthyValue(instanceofExpr)     → new scope (narrowed types)
scope.filterByFalseyValue(instanceofExpr)     → new scope (removed types)
scope.mergeWith(elseScope)                    → new scope (intersection)
```

Tracks types by **expression string**, not just by variable name:

```
'$a'         → ExpressionTypeHolder($a, IntegerType, Yes)
'$a[0]'      → ExpressionTypeHolder($a[0], StringType, Yes)
'$a->prop'   → ExpressionTypeHolder($a->prop, FooType, Yes)
'strlen($a)' → ExpressionTypeHolder(strlen($a), IntegerType, Yes)
```

This means assigning to `$a` invalidates the type caches for `$a[0]` and `$a->prop`.

### AOT Adoption Recommendation

A simplified version (~300 lines) with core capabilities:

- Immutable scope supporting snapshots and merging
- Type tracking by expression key (at least variables and array elements)
- `TrinaryLogic` certainty tracking (the maybe state after branch merging)

---

## 8. PHPDoc Pipeline

### PHPStan's Approach

A three-stage pipeline:

```
PHPDoc comment string
  → Lexer + Parser (phpstan/phpdoc-parser)
  → PhpDocNode (raw AST)
  → TypeNodeResolver::resolve(TypeNode, NameScope)
  → PHPStan Type object
```

`TypeNodeResolver` dispatches via a `switch` to 30+ identifier types:

```
'int' / 'integer'    → IntegerType
'positive-int'       → IntegerRangeType(1, null)
'non-empty-string'   → IntersectionType[StringType, AccessoryNonEmptyStringType]
'class-string'       → ClassStringType
'array'              → ArrayType(MixedType, MixedType)
'list'               → IntersectionType[ArrayType(int), AccessoryArrayListType]
'mixed'              → MixedType(true)
'never'              → NonAcceptingNeverType
...
```

### AOT Application

AOT can reuse `phpstan/phpdoc-parser` to parse `@param`, `@return`, `@var` annotations, then map the type AST nodes to AOT's own Type system. `NameScope` (tracking namespace + use imports) is also a directly reusable concept.

---

## 9. NeverType (Bottom Type)

Represents a type that "cannot have any value", i.e. the empty set:

```
union(int, never)    = int       // never is the identity element of union
intersect(string, never) = never // never is the absorbing element of intersect
```

### AOT Application

- Dead code elimination: an expression narrowed to NeverType → that path is unreachable
- Error propagation: contradictory type combinations produce NeverType
- `void` function returns: essentially NeverType in the return position

---

## Recommended Adoption Order

```
Phase 1 (foundation): Type interface + concrete types + TypeCombinator + TrinaryLogic
  └── Replace string-based type representation, expected to reduce scattered type string operations

Phase 2 (modularization): Rule interface + Registry + Attribute registration
  └── Break down the huge switch of parseStmts/parseExpr

Phase 3 (analysis): TypeSpecifier + basic Immutable Scope
  └── Enable if/else branch type narrowing

Phase 4 (optimization): Collector + CollectedDataNode
  └── Enable cross-file global optimization (devirtualization, dead code elimination, inline decisions)

Phase 5 (ecosystem): Extension registration + PHPDoc Pipeline
  └── Enable framework plugins and annotation-driven type information
```

Each layer builds on the previous one, and each layer can deliver value independently.

---

## PHPStan Features That Should NOT Be Introduced

| Feature | Reason |
|------|------|
| Generics / `@template T` | Extremely complex, AOT does not currently need it |
| IntersectionType + Accessory types | `non-empty-string` = `string & non-empty` is an over-engineered design |
| BetterReflection (static reflection) | AOT already loads files, no need to avoid runtime side effects |
| Full MutatingScope (5000+ lines) | The immutable scope pattern is worth borrowing, but 300 lines is enough |
| ConditionalExpressionHolder | Lazy narrowing of compound conditions; do basic TypeSpecifier first |
| Enum / ConstantArrayType shape | Niche, not needed initially |

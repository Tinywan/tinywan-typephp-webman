# TypePHP Core Class OOA / OOD / OOP Refactoring Plan

## 1. Document Purpose

This document guides the subsequent architectural refactoring of `Translator`, `CompilerBase`, and `Preprocessor`. Implementation should proceed phase by phase, without a one-shot rewrite.

Current baseline:

| Class | Lines | Methods | Current Role |
|---|---:|---:|---|
| `Translator` | 3717 | 126 | CLI, project configuration, code generation, build coordination |
| `CompilerBase` | 3843 | 208 | Compilation state, AST dispatch, Resolver, Emitter |
| `Preprocessor` | 973 | 25 | Declaration collection, AST lowering, dependency and semantic validation |

Current inheritance structure:

```text
Translator
    extends Preprocessor
        extends CompilerBase
```

Main problems:

- The three classes form an inheritance-based God Object, where high-level flows can access all low-level mutable state.
- A large number of Traits only achieve physical splitting, and still implicitly depend on all `$this` fields of the host.
- Frontend analysis, name resolution, semantic validation, code generation, and native build lack clear boundaries.
- Arrays, AST attributes, and `string|false` are used as implicit protocols between modules.
- Manual state switches such as `resetFile()`, `resetClass()`, `resetFunction()` are easy to forget to restore.

## 2. Refactoring Principles

1. Behavior preservation takes priority; separate architecture refactoring from semantic changes into separate commits.
2. Establish object boundaries first, then remove old entry points; during migration, old and new implementations may coexist.
3. Prefer composition, interfaces, and immutable value objects; business Traits only as a transitional measure.
4. Handler Registry indexes directly by node class name, avoiding linear responsibility chains that degrade compilation performance.
5. Compilation state must be passed explicitly through Context or Session.
6. Resolver is responsible for decisions; Generator/Emitter is responsible for code generation; the two must not be mixed.
7. Each phase must have independent PHPUnit and corresponding PHPT regression evidence.

## 3. OOA: Domain Object Analysis

### 3.1 Compilation Session Domain

Responsible for the state of one compilation lifecycle:

```text
CompilationSession
CompilerConfiguration
ScopeStack
ScopeFrame
FileContext
ClassContext
FunctionContext
```

### 3.2 Frontend Analysis Domain

Responsible for PHP source code to validated AST/model:

```text
SourceParser
FrontendPipeline
DeclarationCollector
DependencyAnalyzer
SemanticAnalyzer
AstLoweringPass
```

### 3.3 Resolution Domain

Responsible for symbol and language semantic decisions:

```text
NameResolver
TypeResolver
MethodCallResolver
PropertyResolver
ConstantResolver
AccessPolicy
SymbolRepository
InheritanceGraph
```

### 3.4 Code Generation Domain

Responsible for AST/entity model to C++:

```text
ExpressionCompiler
StatementCompiler
ClassCodeGenerator
FunctionCodeGenerator
WrapperGenerator
ExtensionModuleGenerator
```

### 3.5 Build Domain

Responsible for generated files to final artifacts:

```text
SourcePipeline
NativeBuilder
ResourceCompiler
BuildModeStrategy
CompileOptions
LinkOptions
BuildResult
```

### 3.6 Application Entry Domain

Responsible for user input and top-level flow:

```text
CompilerApplication
CompileCommand
CompilerFacade
ProjectYamlLoader
CommandLineInput
```

## 4. OOD: Target Architecture

```text
CompilerApplication
    └─ CompilerFacade
       ├─ ProjectLoader
       ├─ SourcePipeline
       ├─ FrontendPipeline
       │  ├─ DeclarationCollector
       │  ├─ AstLoweringPass[]
       │  ├─ DependencyAnalyzer
       │  └─ SemanticAnalyzer
       ├─ TranslationCodeGenerator
       │  ├─ ExpressionCompiler
       │  ├─ StatementCompiler
       │  ├─ ClassCodeGenerator
       │  └─ FunctionCodeGenerator
       └─ NativeBuilder
```

### 4.1 Translator's Target

`Translator` ultimately acts as a Facade/Coordinator, only organizing the flow:

```php
final class Translator
{
    public function translate(ProjectInput $input): BuildResult;
}
```

It is forbidden to continue assuming:

- CLI argument parsing;
- AST node semantic determination;
- C++ template concatenation for classes, functions, and wrappers;
- shell command execution;
- preprocessor internal state.

### 4.2 Preprocessor's Target

`Preprocessor` becomes an independent Frontend Service, no longer extending `CompilerBase`:

```php
final class Preprocessor
{
    public function process(SourceUnit $source, CompilationSession $session): PreprocessResult;
}
```

Organize Passes using the Pipeline pattern:

```text
ParseSourcePass
→ NameResolutionPass
→ PropertyHookLoweringPass
→ DeclarationCollectionPass
→ TraitExpansionPass
→ InheritanceValidationPass
→ TypeValidationPass
→ DependencyCollectionPass
```

### 4.3 CompilerBase's Target

`CompilerBase` is eventually replaced by the following objects:

- `CompilationSession`: compilation lifecycle state;
- `ExpressionCompiler`: expression Handler dispatch;
- `StatementCompiler`: statement Handler dispatch;
- `CompilerServices`: the Resolver and Generator collections;
- `CodeGenerationContext`: generation-phase context.

After the migration is complete, delete `CompilerBase`, or keep only a short-term compatibility Facade.

## 5. Design Pattern Application

### Facade

`CompilerFacade` and the final `Translator` provide a stable top-level entry point, hiding the details of Frontend, Generator, and Builder.

### Pipeline

`FrontendPipeline` explicitly maintains the order of frontend Passes, and each Pass can be tested independently.

### Handler Registry

Expressions and statements are dispatched in O(1) by AST class name:

```php
$handlers[Expr\MethodCall::class] = $methodCallHandler;
```

### Strategy

Build modes are implemented by the following strategies:

- `BinaryBuildStrategy`
- `ExtensionBuildStrategy`
- `LibraryBuildStrategy`
- `EmbedBuildStrategy`

### Chain of Responsibility

Method resolution order:

```text
DeclaredMethodResolver
→ ObjectExtensionMethodResolver
→ UniversalMethodResolver
→ MagicCallResolver
→ DynamicCallResolver
```

Property resolution order:

```text
BackingSlotResolver
→ PropertyHookResolver
→ DeclaredPropertyResolver
→ NativePropertyResolver
→ DynamicPropertyResolver
```

### Repository

`SymbolRepository` uniformly manages functions, classes, interfaces, constants, and inheritance relationships; callers no longer handle Repository keys themselves.

### State / Scope Stack

Use `ScopeStack` and `ScopeGuard` to replace the reset series of methods, ensuring state restoration on exceptions, `Skip`, and `Redo`.

### Value Object / Result Object

Gradually introduce:

- `SourceUnit`
- `SourceLocation`
- `GeneratedExpression`
- `GeneratedStatement`
- `ResolvedCall`
- `ResolvedPropertyAccess`
- `PreprocessResult`
- `TranslationResult`
- `BuildResult`

## 6. OOP Incremental Implementation Phases

### Phase 0: Architecture Protection Tests

Tasks:

- Establish an expression and statement node coverage checklist;
- Add frontend Pass order tests;
- Add Scope exception recovery tests;
- Add SymbolRepository name normalization tests;
- Fix the Property Hook, extension methods, inheritance, and exception test sets;
- Establish snapshots or structural assertions for key generated C++.

Acceptance:

- PHPUnit passes in full;
- Core PHPT all pass;
- Subsequent phases can identify Handler omissions and evaluation order changes.

### Phase 1: CompilationSession and ScopeStack

Tasks:

1. Create `CompilationSession`, `CompilerConfiguration`, `ScopeStack`.
2. Move in current file, namespace, class, method, function, PHP version, and phase state.
3. `CompilerBase`'s old properties first proxy to the Session.
4. Replace the reset series of methods with `enter/leave` and `try/finally`.
5. Delete the proxy properties.

Acceptance:

- `CompilerBase` no longer directly owns scope state;
- Exceptions, `Skip`, `Redo` do not pollute the next scope;
- ScopeStack has independent unit tests.

### Phase 2: Preprocessor Pipeline

Tasks:

1. Create `FrontendPass` and `FrontendPipeline`.
2. First migrate Property Hook lowering.
3. Migrate declaration collection and namespace/use handling.
4. Migrate dependency collection and file ordering.
5. Migrate Trait, inheritance, override, and interface implementation validation.
6. Remove `Preprocessor extends CompilerBase`.

Acceptance:

- Each Pass has independent tests;
- Pass order is defined in only one place;
- `Preprocessor.php` is kept within 200–300 lines.

### Phase 3: ExpressionCompiler

Tasks:

1. Create `ExpressionHandlerRegistry` and `GeneratedExpression`.
2. Migrate scalar/const/variable, unary/binary/cast, array/assign in order.
3. Migrate function/method/static calls.
4. Migrate property, nullsafe, isset/empty/ref.
5. Migrate closure, generator, fiber, new, clone, instanceof.
6. Delete the old large `parseExpr()` dispatch.

Acceptance:

- Every supported Expr has a unique Handler;
- Handlers do not depend on `CompilerBase`;
- Registry checks for duplicates and omissions at startup;
- Evaluation order and side effect tests all pass.

### Phase 4: StatementCompiler

Tasks:

1. Create `StatementHandlerRegistry` and `GeneratedStatement`.
2. Migrate return/echo, conditionals, loops, exception control flow.
3. Migrate global/static/namespace/declare.
4. Eliminate the shared `beforeStmtLines`, `afterStmtLines` protocol.

Acceptance:

- Statement Handlers return an explicit Result;
- Control flow generation is removed from `CompilerBase`;
- before/after statements are composed through Result.

### Phase 5: Resolver Chain

Tasks:

1. Establish `MethodCallResolverChain`.
2. Establish `PropertyResolverChain`.
3. Establish `ConstantResolverChain`.
4. Establish a unified `AccessPolicy`.
5. Migrate `MethodCallTrait`, `PropertyAccessTrait`, `UniversalMethodCall`, `MagicMethodDetector` into the Resolver.

Acceptance:

- The priority of normal methods, extension methods, and `__call()` is defined in only one place;
- The priority of backing slot, Property Hook, and normal properties is defined in only one place;
- `private(set)`, `protected(set)` are determined only by AccessPolicy;
- Resolver no longer returns `string|false`.

### Phase 6: Independent Code Generators

Tasks:

- Establish `ClassCodeGenerator`;
- Establish `FunctionCodeGenerator`;
- Establish `WrapperGenerator`;
- Establish `ExtensionModuleGenerator`;
- Move `parseClass()`, `parseFunction()`, wrapper, and registration code out of `Translator`.

Acceptance:

- Generator input is Entity/IR, output is `GeneratedFile`;
- `Translator` no longer directly concatenates concrete C++ templates;
- Key generation results have snapshot tests.

### Phase 7: Translator Facade

Tasks:

1. Move CLI to `CompilerApplication` / `CompileCommand`.
2. `Translator` only injects ProjectLoader, Frontend, CodeGenerator, NativeBuilder.
3. Remove `Translator extends Preprocessor`.
4. Converge the public entry point to `translate(ProjectInput): BuildResult`.

Acceptance:

- `Translator` does not parse CLI;
- does not directly access the AST;
- does not directly execute shell;
- does not depend on Preprocessor internal state;
- the file is kept within 300–500 lines.

### Phase 8: Remove the CompilerBase Inheritance Hierarchy

Tasks:

1. Clear remaining compatibility proxies and business Traits.
2. Move public query interfaces into explicit Services/Contexts.
3. Delete the `Translator → Preprocessor → CompilerBase` inheritance chain.
4. Delete uncalled legacy methods and fields.

Acceptance:

- Core components collaborate only through interfaces and DTOs;
- No business Trait implicitly accesses all host state;
- No core class exceeds about 800 lines;
- PHPUnit, core PHPT, and multi-PHP-version builds all pass.

## 7. Per-Phase Execution Template

Each phase is implemented following these steps:

1. List the methods, fields, and call sites to migrate.
2. Add protection tests first.
3. Create the new interfaces, DTOs, and implementation.
4. Change old entry points to delegate to the new implementation.
5. Migrate call sites in batches.
6. Delete old implementations and proxy fields.
7. Run syntax checks, PHPUnit, and corresponding PHPT.
8. Check `git diff --check` and untracked build artifacts.
9. Update the phase status and actual deviations in this document.

## 8. Recommended Directory Layout

```text
src/
├─ Application/
├─ Compiler/
│  └─ Scope/
├─ Frontend/
│  ├─ Pass/
│  └─ Result/
├─ CodeGeneration/
│  ├─ Expression/
│  └─ Statement/
├─ Resolver/
│  ├─ Call/
│  ├─ Property/
│  ├─ Constant/
│  └─ Access/
├─ Symbol/
├─ Build/
│  ├─ BuildMode/
│  └─ Options/
└─ Diagnostics/
```

## 9. Phase Status

| Phase | Status |
|---|---|
| 0. Architecture protection tests | Not started |
| 1. CompilationSession / ScopeStack | Not started |
| 2. Preprocessor Pipeline | Not started |
| 3. ExpressionCompiler | Not started |
| 4. StatementCompiler | Not started |
| 5. Resolver Chain | Not started |
| 6. Independent code generators | Not started |
| 7. Translator Facade | Not started |
| 8. Remove CompilerBase inheritance hierarchy | Not started |

This table should be continuously updated during implementation; a phase must not be declared complete based solely on file line count.

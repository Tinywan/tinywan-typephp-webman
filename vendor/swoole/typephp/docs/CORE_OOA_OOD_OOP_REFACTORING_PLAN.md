# TypePHP 核心类 OOA / OOD / OOP 重构计划

## 1. 文档目的

本文档用于指导 `Translator`、`CompilerBase`、`Preprocessor` 的后续架构重构。实施时应逐阶段完成，不进行一次性重写。

当前基线：

| 类 | 行数 | 方法数 | 当前角色 |
|---|---:|---:|---|
| `Translator` | 3717 | 126 | CLI、项目配置、代码生成、构建协调 |
| `CompilerBase` | 3843 | 208 | 编译状态、AST 分派、Resolver、Emitter |
| `Preprocessor` | 973 | 25 | 声明收集、AST lowering、依赖和语义验证 |

当前继承结构：

```text
Translator
    extends Preprocessor
        extends CompilerBase
```

主要问题：

- 三个类形成继承式 God Object，高层流程可以访问全部底层可变状态。
- 大量 Trait 只完成了物理拆分，仍隐式依赖宿主的全部 `$this` 字段。
- 前端分析、名称解析、语义验证、代码生成和 native build 缺少明确边界。
- 数组、AST attribute 和 `string|false` 被用作模块间隐式协议。
- `resetFile()`、`resetClass()`、`resetFunction()` 等手工状态切换容易遗漏恢复。

## 2. 重构原则

1. 行为保持优先，架构重构和语义修改分开提交。
2. 先建立对象边界，再删除旧入口；迁移期允许新旧实现并存。
3. 优先使用组合、接口、不可变值对象；业务 Trait 仅作为过渡方案。
4. Handler Registry 使用节点类名直接索引，避免线性责任链造成编译性能下降。
5. 编译状态必须通过 Context 或 Session 显式传递。
6. Resolver 负责决策，Generator/Emitter 负责生成代码，两者不得混合。
7. 每个阶段必须具备独立 PHPUnit 和对应 PHPT 回归证据。

## 3. OOA：领域对象分析

### 3.1 编译会话域

负责一次编译生命周期的状态：

```text
CompilationSession
CompilerConfiguration
ScopeStack
ScopeFrame
FileContext
ClassContext
FunctionContext
```

### 3.2 前端分析域

负责 PHP 源码到已验证 AST/模型：

```text
SourceParser
FrontendPipeline
DeclarationCollector
DependencyAnalyzer
SemanticAnalyzer
AstLoweringPass
```

### 3.3 解析域

负责符号和语言语义决策：

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

### 3.4 代码生成域

负责 AST/实体模型到 C++：

```text
ExpressionCompiler
StatementCompiler
ClassCodeGenerator
FunctionCodeGenerator
WrapperGenerator
ExtensionModuleGenerator
```

### 3.5 构建域

负责生成文件到最终产物：

```text
SourcePipeline
NativeBuilder
ResourceCompiler
BuildModeStrategy
CompileOptions
LinkOptions
BuildResult
```

### 3.6 应用入口域

负责用户输入和顶层流程：

```text
CompilerApplication
CompileCommand
CompilerFacade
ProjectYamlLoader
CommandLineInput
```

## 4. OOD：目标架构

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

### 4.1 Translator 的目标

`Translator` 最终作为 Facade/Coordinator，仅组织流程：

```php
final class Translator
{
    public function translate(ProjectInput $input): BuildResult;
}
```

禁止继续承担：

- CLI 参数解析；
- AST 节点语义判断；
- 类、函数和 wrapper 的 C++ 模板拼接；
- shell 命令执行；
- 前处理器内部状态。

### 4.2 Preprocessor 的目标

`Preprocessor` 改为独立 Frontend Service，不再继承 `CompilerBase`：

```php
final class Preprocessor
{
    public function process(SourceUnit $source, CompilationSession $session): PreprocessResult;
}
```

使用 Pipeline 模式组织 Pass：

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

### 4.3 CompilerBase 的目标

`CompilerBase` 最终被以下对象替代：

- `CompilationSession`：编译生命周期状态；
- `ExpressionCompiler`：表达式 Handler 分派；
- `StatementCompiler`：语句 Handler 分派；
- `CompilerServices`：Resolver 和 Generator 集合；
- `CodeGenerationContext`：生成期上下文。

完成迁移后删除 `CompilerBase`，或仅保留短期兼容 Facade。

## 5. 设计模式应用

### Facade

`CompilerFacade` 和最终的 `Translator` 提供稳定顶层入口，隐藏 Frontend、Generator、Builder 细节。

### Pipeline

`FrontendPipeline` 显式维护前处理 Pass 顺序，每个 Pass 可独立测试。

### Handler Registry

表达式和语句以 AST 类名进行 O(1) 分派：

```php
$handlers[Expr\MethodCall::class] = $methodCallHandler;
```

### Strategy

构建模式由以下策略实现：

- `BinaryBuildStrategy`
- `ExtensionBuildStrategy`
- `LibraryBuildStrategy`
- `EmbedBuildStrategy`

### Chain of Responsibility

方法解析顺序：

```text
DeclaredMethodResolver
→ ObjectExtensionMethodResolver
→ UniversalMethodResolver
→ MagicCallResolver
→ DynamicCallResolver
```

属性解析顺序：

```text
BackingSlotResolver
→ PropertyHookResolver
→ DeclaredPropertyResolver
→ NativePropertyResolver
→ DynamicPropertyResolver
```

### Repository

`SymbolRepository` 统一管理函数、类、接口、常量和继承关系；调用方不再自行处理 Repository key。

### State / Scope Stack

使用 `ScopeStack` 和 `ScopeGuard` 替代 reset 系列方法，确保异常、`Skip`、`Redo` 时恢复状态。

### Value Object / Result Object

逐步引入：

- `SourceUnit`
- `SourceLocation`
- `GeneratedExpression`
- `GeneratedStatement`
- `ResolvedCall`
- `ResolvedPropertyAccess`
- `PreprocessResult`
- `TranslationResult`
- `BuildResult`

## 6. OOP 渐进实施阶段

### 阶段 0：架构保护测试

任务：

- 建立表达式和语句节点覆盖清单；
- 增加 Frontend Pass 顺序测试；
- 增加 Scope 异常恢复测试；
- 增加 SymbolRepository 名称规范化测试；
- 固定 Property Hook、扩展方法、继承和异常测试集合；
- 对关键生成 C++ 建立快照或结构断言。

验收：

- PHPUnit 全量通过；
- 核心 PHPT 全部通过；
- 后续阶段可以识别 Handler 遗漏和求值顺序变化。

### 阶段 1：CompilationSession 与 ScopeStack

任务：

1. 新建 `CompilationSession`、`CompilerConfiguration`、`ScopeStack`。
2. 移入当前文件、namespace、class、method、function、PHP 版本和 phase 状态。
3. `CompilerBase` 旧属性先代理到 Session。
4. 将 reset 系列方法替换为 `enter/leave` 和 `try/finally`。
5. 删除代理属性。

验收：

- `CompilerBase` 不再直接拥有作用域状态；
- 异常、`Skip`、`Redo` 不会污染下一作用域；
- ScopeStack 有独立单测。

### 阶段 2：Preprocessor Pipeline

任务：

1. 新建 `FrontendPass` 和 `FrontendPipeline`。
2. 首先迁移 Property Hook lowering。
3. 迁移声明收集和 namespace/use 处理。
4. 迁移依赖收集与文件排序。
5. 迁移 Trait、继承、override 和接口实现验证。
6. 解除 `Preprocessor extends CompilerBase`。

验收：

- 每个 Pass 有独立测试；
- Pass 顺序只有一处定义；
- `Preprocessor.php` 控制在 200～300 行。

### 阶段 3：ExpressionCompiler

任务：

1. 新建 `ExpressionHandlerRegistry` 和 `GeneratedExpression`。
2. 按顺序迁移 scalar/const/variable、unary/binary/cast、array/assign。
3. 迁移 function/method/static call。
4. 迁移 property、nullsafe、isset/empty/ref。
5. 迁移 closure、generator、fiber、new、clone、instanceof。
6. 删除旧 `parseExpr()` 大型分派。

验收：

- 每种已支持 Expr 都有唯一 Handler；
- Handler 不依赖 `CompilerBase`；
- Registry 启动时检查重复和遗漏；
- 求值顺序和副作用测试全部通过。

### 阶段 4：StatementCompiler

任务：

1. 新建 `StatementHandlerRegistry` 和 `GeneratedStatement`。
2. 迁移 return/echo、条件、循环、异常控制流。
3. 迁移 global/static/namespace/declare。
4. 消除共享 `beforeStmtLines`、`afterStmtLines` 协议。

验收：

- Statement Handler 返回显式 Result；
- 控制流生成从 `CompilerBase` 移除；
- before/after 语句通过 Result 组合。

### 阶段 5：Resolver Chain

任务：

1. 建立 `MethodCallResolverChain`。
2. 建立 `PropertyResolverChain`。
3. 建立 `ConstantResolverChain`。
4. 建立统一 `AccessPolicy`。
5. 将 `MethodCallTrait`、`PropertyAccessTrait`、`UniversalMethodCall`、`MagicMethodDetector` 迁入 Resolver。

验收：

- 普通方法、扩展方法、`__call()` 优先级只有一处定义；
- backing slot、Property Hook 和普通属性优先级只有一处定义；
- `private(set)`、`protected(set)` 只由 AccessPolicy 判断；
- Resolver 不再返回 `string|false`。

### 阶段 6：独立代码生成器

任务：

- 建立 `ClassCodeGenerator`；
- 建立 `FunctionCodeGenerator`；
- 建立 `WrapperGenerator`；
- 建立 `ExtensionModuleGenerator`；
- 将 `parseClass()`、`parseFunction()`、wrapper 和注册代码移出 `Translator`。

验收：

- Generator 输入为 Entity/IR，输出为 `GeneratedFile`；
- `Translator` 不再直接拼接具体 C++ 模板；
- 关键生成结果具备快照测试。

### 阶段 7：Translator Facade

任务：

1. 将 CLI 移到 `CompilerApplication` / `CompileCommand`。
2. `Translator` 仅注入 ProjectLoader、Frontend、CodeGenerator、NativeBuilder。
3. 解除 `Translator extends Preprocessor`。
4. 将公共入口收敛为 `translate(ProjectInput): BuildResult`。

验收：

- `Translator` 不解析 CLI；
- 不直接访问 AST；
- 不直接执行 shell；
- 不依赖 Preprocessor 内部状态；
- 文件控制在 300～500 行。

### 阶段 8：删除 CompilerBase 继承体系

任务：

1. 清除剩余兼容代理和业务 Trait。
2. 将公共查询接口放入明确 Service/Context。
3. 删除 `Translator → Preprocessor → CompilerBase` 继承链。
4. 删除无调用的旧方法和字段。

验收：

- 核心组件仅通过接口和 DTO 协作；
- 无业务 Trait 隐式访问宿主全部状态；
- 不存在超过约 800 行的核心类；
- PHPUnit、核心 PHPT 和多 PHP 版本构建全部通过。

## 7. 每阶段执行模板

每个阶段都按照以下步骤实施：

1. 列出迁移方法、字段和调用方。
2. 先补保护测试。
3. 新建接口、DTO 和实现。
4. 旧入口改为委托新实现。
5. 批量迁移调用方。
6. 删除旧实现和代理字段。
7. 运行语法检查、PHPUnit 和对应 PHPT。
8. 检查 `git diff --check` 和未跟踪构建产物。
9. 更新本文档中的阶段状态和实际偏差。

## 8. 建议目录

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

## 9. 阶段状态

| 阶段 | 状态 |
|---|---|
| 0. 架构保护测试 | 待开始 |
| 1. CompilationSession / ScopeStack | 待开始 |
| 2. Preprocessor Pipeline | 待开始 |
| 3. ExpressionCompiler | 待开始 |
| 4. StatementCompiler | 待开始 |
| 5. Resolver Chain | 待开始 |
| 6. 独立代码生成器 | 待开始 |
| 7. Translator Facade | 待开始 |
| 8. 删除 CompilerBase 继承体系 | 待开始 |

实施过程中应持续更新此表，不允许仅根据文件行数宣布阶段完成。

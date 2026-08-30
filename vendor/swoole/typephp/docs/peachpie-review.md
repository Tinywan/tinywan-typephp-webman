# PeachPie 编译器 Review：Roslyn 集成、跨语言互操作与类型系统

> 2026-06-11 · 基于 /home/swoole/workspace/cpp/peachpie 源码审查

---

## 一、架构概览

PeachPie 是一个**基于 Roslyn（Microsoft .NET 编译器平台）的 PHP-to-.NET 编译器**，约 710 个 C# 源文件、272k 行代码。

### 编译管道

```
PHP 源码
  → PhpSyntaxTree (Roslyn SyntaxTree for PHP — Syntax/)
  → SemanticModel + Symbols (Roslyn symbol system — Semantics/, Symbols/)
  → BoundControlFlowGraph (CFG with typed IR — Semantics/Graph/, FlowAnalysis/)
  → CIL Bytecode (EMIT — CodeGen/, Emitter/)
  → .NET Assembly (.dll / .exe)
```

| 阶段 | 组件 | 职责 |
|------|------|------|
| 语法解析 | `Syntax/` (PhpSyntaxTree, NodesFactory) | PHP → Roslyn SyntaxTree |
| 语义绑定 | `Semantics/` (SemanticsBinder, BoundExpression) | 名称解析、方法绑定、类型推断 |
| 符号系统 | `Symbols/` (SourceTypeSymbol, PEMethodSymbol...) | 类型/方法/属性的 Roslyn 符号表 |
| 数据流分析 | `FlowAnalysis/` (FlowState, TypeRefMask, ExpressionAnalysis) | 类型推断、不可达代码检测、条件收窄 |
| CFG 优化 | `FlowAnalysis/Passes/` (TransformationRewriter) | CFG 重写、常量化、死代码消除 |
| 代码生成 | `CodeGen/` (CodeGenerator, GhostMethodBuilder) | IR → CIL 指令 |
| 程序集输出 | `Emitter/` (PEModuleBuilder) | CIL → PE 文件 (.dll/.exe) |

### 核心文件

| 文件 | 行数 | 用途 |
|------|------|------|
| `CodeGen/Graph/BoundExpression.cs` | 5,742 | 核心 IR 节点定义与 CIL 发射 |
| `CodeGen/CodeGenerator.Emit.cs` | 4,396 | CIL 指令生成 |
| `FlowAnalysis/ExpressionAnalysis.cs` | 2,911 | 表达式级类型分析 |
| `Semantics/BoundExpression.cs` | 2,721 | 语义绑定表达式 |
| `Runtime/Operators.cs` | 2,573 | PHP 运算符运行时实现 |
| `Runtime/PhpString.cs` | 2,047 | PHP 字符串值类型 |
| `CodeGen/VariableReference.cs` | 1,863 | 变量引用与地址分析 |
| `Symbols/Source/SourceTypeSymbol.cs` | 1,795 | 源码类型符号 |
| `Runtime/Conversions.cs` | 1,603 | 类型转换运行时 |

### 与 AOT Compiler 对比

| 维度 | PeachPie | AOT Compiler |
|------|----------|-------------|
| 编译目标 | PHP → CIL → .NET Assembly | PHP → C++ → 二进制 |
| 编译器框架 | Roslyn (C# compiler-as-a-library) | 自研 PHP AST → C++ string |
| IR 形式 | BoundControlFlowGraph (Roslyn 模式) | PHP-Parser AST 节点 |
| 类型推理 | FlowState + TypeRefMask bitset | SSA + 手动类型注释 |
| 符号系统 | Roslyn Symbol hierarchy (完整) | 简化版 ClassDef/FunctionDef |
| 输出 | .NET PE 文件 (cross-platform) | 原生二进制 (Linux/Mac/Windows) |
| 运行时 | Peachpie.Runtime (PhpValue, PhpArray, PhpString) | phpx (C++ RAII 包装 Zend API) |
| 跨语言互操作 | 一等公民 — PHP ⇄ C# 双向调用 | 仅 FFI (swoole_cc/cpp 扩展加载) |
| 并行编译 | Parallel.ForEach 函数级并行 | 串行执行 |
| 代码量 | ~272k 行 C# (含运行时) | ~15k 行 PHP |
| MSBuild 集成 | 完整 SDK (`dotnet build`) | 无 |

---

## 二、可借鉴的创新设计

### 2.1 基于 Roslyn 的编译器架构

**文件**: `Peachpie.CodeAnalysis/` 全部

PeachPie 最核心的设计决策是**完全基于 Microsoft Roslyn 编译器平台**构建。这意味着：

- **复用 Roslyn 的符号系统**：`TypeSymbol`, `MethodSymbol`, `NamedTypeSymbol` 等标准 Roslyn 类型
- **复用 Roslyn 的元数据发射**：`PEModuleBuilder`, `PEAssemblyBuilder` 直接生成 PE 文件
- **复用 Roslyn 的诊断体系**：`DiagnosticBag`, 标准化的 Error/Warning 机制
- **MSBuild 原生集成**：PHP 项目是标准的 .NET 项目（`.csproj` 风格），`dotnet build` 即可编译

**AOT 借鉴优先级: P2**

当前 AOT 从零搭建了所有编译器基础设施。PeachPie 的思路可以作为参考，但不适合直接移植（AOT 的目标是生成 C++ 而非 CIL）。可以借鉴的是：

- 将编译器分解为 **Syntax → Semantic → IR → CodeGen** 的标准阶段，每阶段有清晰的接口
- Preprocessor（符号收集）分离为独立的 Analyzer 阶段，类似 Roslyn 的 Compilation 概念
- 统一定义 `Diagnostic` 类型，而非散布在各处的 `fatalError()` / `SyntaxError`

---

### 2.2 TypeRefMask：64-bit 类型 Bitset

**文件**: `FlowAnalysis/TypeRef/TypeRefMask.cs`

PeachPie 使用 `ulong` (64-bit) 作为类型掩码：

```csharp
public struct TypeRefMask {
    ulong _mask;
    // bits 0-61:  类型索引（最多 62 种不同类型）
    // bit 62:     IncludesSubclasses（类型可能包含子类）
    // bit 63:     IsRef（值是引用/别名）
}
```

特性：
- **O(1) 类型比较**: `(mask & type_bit) != 0` 即可检测类型
- **联合类型**: `mask1 | mask2` = 包含两种类型
- **类型收窄**: `mask & ~excluded_type_bit` = 排除某种类型
- **IsRef 标记**: 追踪值是否被引用赋值，影响别名分析
- **IncludesSubclasses**: 区分 `exactly Class` vs `Class or subclass`

每个 `TypeRefContext` 维护一个类型注册表，将具体的 .NET 类型 (如 `System.Int64`, `Pchp.Core.PhpString`) 映射到 bit index。

**AOT 借鉴优先级: P0**

这与 HHVM 的 trep 思路一致——使用 bitset 表示类型。AOT 的 `TYPE_INT`, `TYPE_STRING` 等离散常量可以被替换为 bitset：

```php
class TypeMask {
    const BINT     = 1 << 0;
    const BFLOAT   = 1 << 1;
    const BSTRING  = 1 << 2;
    const BBOOL    = 1 << 3;
    const BARRAY   = 1 << 4;
    const BOBJECT  = 1 << 5;
    // 扩展标记
    const BEMPTY      = 1 << 60;  // 空值标记
    const BSUBCLASS   = 1 << 61;  // 允许子类
    const BREFERENCE  = 1 << 62;  // 引用标记
}
```

优势：`int|string` = `BINT|BSTRING`，类型收窄 = `& ~excluded_bits`。

---

### 2.3 FlowState + Worklist 数据流分析

**文件**: `FlowAnalysis/FlowState.cs`, `FlowAnalysis/Worklist.cs`

PeachPie 使用经典的**数据流 worklist 算法**进行类型推断：

```csharp
class FlowState {
    TypeRefMask[] _varsType;     // 每个变量的类型掩码
    ulong _initializedMask;      // 变量是否被初始化
    HashSet<NoteData> _notes;    // 附加信息（如函数返回点）
}
```

**Merge 操作**: 当两条 CFG 路径汇合时，`FlowState(state1, state2)` 构造函数计算：
- 类型掩码的 **union**（所有可能类型）
- 初始化掩码的 **union**（任一分支初始化就算初始化）
- Notes 的 **intersection**（两条路径都有的信息才保留）

Worklist 按拓扑顺序处理 block，遇到状态变化时重新入队。

**AOT 借鉴优先级: P1**

当前 AOT 的 SSA 分析做了一定程度的类型推导，但缺少：
- **结构化 FlowState**：统一的变量类型状态表示
- **标准 merge 操作**：join point 处的类型合并
- **Worklist 迭代**：达到不动点的迭代分析框架

---

### 2.4 ConditionBranch 感知的类型收窄

**文件**: `FlowAnalysis/ConditionBranch.cs`, `FlowAnalysis/AnalysisFacts.cs`

PeachPie 的类型分析**感知当前上下文的条件分支**：

```csharp
enum ConditionBranch {
    AnyResult = 0,   // 普通求值
    ToTrue = +1,     // 表达式结果为 true 的分支
    ToFalse = -1,    // 表达式结果为 false 的分支
}
```

在条件表达式中，分析器携带分支方向传播类型信息：

```csharp
// if ($x instanceof MyClass) { ... }
// 在 ToTrue 分支中:
//   $x 的类型收窄，排除不可能是 MyClass 的类型
// 在 ToFalse 分支中:
//   $x 的类型收窄，排除 MyClass

// if (is_int($x)) { ... }
// 在 ToTrue 分支中:
//   $x 的类型收窄为 int
```

`AnalysisFacts.HandleSpecialFunctionCall()` 注册了 `is_int`, `is_string`, `is_array`, `is_callable`, `function_exists`, `class_exists` 等类型检查函数，在分支中自动收窄变量类型。

**AOT 借鉴优先级: P1**

当前 `SsaTypeOptimizer` 做了一定程度的 instanceof 收窄，但：
- 不支持 `is_int()` / `is_string()` 等内置类型检查函数收窄
- 不支持 `class_exists()` / `function_exists()` 等存在性检查常量折叠
- 可以直接借鉴 `AnalysisFacts` 的 "已知类型检查函数注册表" 模式

---

### 2.5 PhpValue Tagged Union 设计

**文件**: `Peachpie.Runtime/PhpValue.cs`

PeachPie 的运行时值类型使用精妙的 C# tagged union：

```csharp
[StructLayout(LayoutKind.Sequential)]
public readonly partial struct PhpValue {
    readonly PhpTypeCode _type;   // 1 byte 类型标签

    // 显式布局联合：两个字段占用同一内存
    [StructLayout(LayoutKind.Explicit)]
    struct ValueField {
        [FieldOffset(0)] public bool @bool;
        [FieldOffset(0)] public long @long;
        [FieldOffset(0)] public double @double;
    }

    [StructLayout(LayoutKind.Explicit)]
    struct ObjectField {
        [FieldOffset(0)] public object @object;
        [FieldOffset(0)] public string @string;
        [FieldOffset(0)] public PhpString.Blob blob;
        [FieldOffset(0)] public PhpArray array;
        [FieldOffset(0)] public PhpAlias alias;
    }

    readonly ValueField _value;    // 值类型存储
    readonly ObjectField _obj;     // 引用类型存储
}
```

内存布局：`PhpValue` = `PhpTypeCode` (1 byte) + padding + `ValueField` (8 bytes) + `ObjectField` (8 bytes，指针) ≈ 24 bytes。

这种设计的优势：
- **readonly struct** — 无 GC 开销，可在栈上分配
- **显式联合** — 值类型和引用类型共享空间，紧凑
- **PhpAlias 机制** — 通过 `PhpAlias` 抽象实现 PHP 引用（写时复制），而非直接复制值
- **MutableString** — 区分不可变 string 和可写 MutableString（用于字符串拼接优化）

**AOT 借鉴优先级: P3**

当前 AOT 使用 `Variant`（基于 Zend `zval`）作为动态类型。可以借鉴：
- PhpString 的 MutableString 分离（string builder pattern）
- PhpAlias 的引用语义抽象
- 但整体替换 `Variant` 工程量巨大，优先级低

---

### 2.6 GhostMethodBuilder：PHP 方法 ⇄ C# 方法适配

**文件**: `CodeGen/GhostMethodBuilder.cs`

PeachPie 最独特的功能是**自动生成 ghost stub 方法**，使 PHP 方法可被 C# 直接调用：

```csharp
// 为 PHP 方法生成 C# 可调用的包装器：
// - 处理参数类型转换（PhpValue → CLR type）
// - 处理返回类型转换（CLR type → PhpValue）
// - 构建 PhpContext 传递
// - 支持 explicit interface override
static MethodSymbol CreateGhostOverload(
    MethodSymbol original, NamedTypeSymbol containingtype,
    PEModuleBuilder module, DiagnosticBag diagnostic,
    TypeSymbol ghostreturn, ImmutableArray<ParameterSymbol> ghostparams,
    bool phphidden = false, MethodSymbol explicitOverride = null)
```

Ghost 方法使得：
- C# 调用 PHP 方法时自动获得类型安全的接口
- PHP 实现 C# 接口（IMethod, INotifyPropertyChanged 等）
- PHP 类可以作为 .NET 泛型参数

**AOT 借鉴优先级: P4**

当前 AOT 不支持 PHP 调用 C++（反之亦然）。如果未来需要双向互操作层，ghost stub 模式值得参考。

---

### 2.7 DelayedTransformations：并行安全的延迟变换

**文件**: `FlowAnalysis/Passes/DelayedTransformations.cs`

在并行分析阶段，某些变换（如标记不可达函数、将条件函数升级为无条件函数）不能直接修改共享状态。PeachPie 使用**延迟变换**模式：

```csharp
class DelayedTransformations {
    ConcurrentBag<SourceRoutineSymbol> UnreachableRoutines;
    ConcurrentBag<SourceTypeSymbol> UnreachableTypes;
    ConcurrentBag<SourceFunctionSymbol> FunctionsMarkedAsUnconditional;
    // 并行分析期间线程安全地收集
    // 分析完成后串行 Apply()
}
```

分析线程只将待变换的对象放入 `ConcurrentBag`，分析结束后由单线程调用 `Apply()`。

**AOT 借鉴优先级: P2**

AOT 目前是串行的，不需要这个。但如果将来引入并行编译（参见 KPHP review 2.3），延迟变换是线程安全的基础模式。

---

### 2.8 MSBuild 原生集成（Peachpie.NET.Sdk）

**文件**: `Peachpie.NET.Sdk/`

PeachPie 不只是一个编译器——它是一个**完整的 .NET SDK**：

```
dotnet new classlibrary -o MyPhpLib  # 创建 PHP 类库项目
dotnet build                           # 编译 PHP → .NET DLL
dotnet run                             # 运行编译后的程序
dotnet publish                         # 发布为独立应用
```

通过 MSBuild targets/props 实现：
- `build/peachpie.targets` — 定义编译任务
- `Peachpie.NET.Sdk.nuspec` — NuGet 包定义
- `BuildTask.cs` — MSBuild 编译任务

这意味着 PHP 项目可以无缝使用 .NET 生态：NuGet 包引用、项目引用、条件编译、多目标框架等。

**AOT 借鉴优先级: P2**

AOT 目前使用 `php bin/tpc.php <project>` 命令行。可以借鉴：
- 为 AOT 编译器创建一个 Composer plugin 或 CLI phar 包
- 定义 `project.yml` 的 JSON Schema（类似 `.csproj`）
- 支持 `composer build` 或 `php-aot build` 统一入口

---

### 2.9 可延迟求值的 AnalysisFacts

**文件**: `FlowAnalysis/AnalysisFacts.cs`

PeachPie 在编译期对大量 PHP 运行时函数进行常量求值：

| 函数 | 求值策略 |
|------|---------|
| `function_exists(X)` | 检查 PE assembly 中是否存在符号 X → 折叠为 `true` |
| `class_exists(X)` | 检查 PE assembly 中是否存在类型 X → 折叠为 `true`/`false` |
| `method_exists(X, M)` | 检查类型 X 中是否存在方法 M → 折叠为 `true`/`false` |
| `defined(CONST)` | 检查常量是否存在 → 折叠为 `true`/`false` |
| `is_callable(F)` | 检查 F 是否无条件声明 → 折叠为 `true` |
| `dirname(__FILE__)` | 编译期路径运算 → `__DIR__` |
| `basename(__FILE__)` | 编译期文件名提取 → 字符串常量 |

这些求值利用了 PeachPie 的 "PE assembly" 概念——已经编译好的 .NET 程序集包含了完整的类型/方法/常量元数据，可以**在编译时查询**。

**AOT 借鉴优先级: P1**

当前 `FuncCallOptimizer` 只做最基础的常量折叠（`strlen("abc")` → `3`）。可以扩展为对 `function_exists`, `class_exists`, `defined` 等反射型函数的编译期求值——前提是在 Preprocessor 中建立了完整的符号表。

---

### 2.10 条件声明检测与不可达代码消除

**文件**: `FlowAnalysis/Passes/DelayedTransformations.cs`, `FlowAnalysis/Passes/TransformationRewriter.cs`

PeachPie 能检测**条件声明**（`if (condition) { function foo() {} }`）并优化：

- 如果分析证明条件恒为 true → 函数标记为无条件声明
- 如果分析证明条件恒为 false → 函数/类标记为不可达（Unreachable），不编译

这允许在 PHP 中写出类似 C 条件编译的模式：

```php
if (PHP_VERSION_ID >= 80000) {
    function newFeature() { ... }  // 低版本下不编译
}
```

**AOT 借鉴优先级: P2**

当前 AOT 编译所有扫描到的函数，无论是否可达。对于存在多个 PHP 版本兼容代码的项目，条件声明消除可以减少编译产物大小。

---

## 三、工具链分析

### 3.1 测试基础设施

PeachPie 有 529 个测试文件（PHP 文件），分布在功能目录中：

| 目录 | 内容 |
|------|------|
| `tests/arrays/` | 数组操作测试（含 `lazy_copy` 子目录） |
| `tests/classes/` | 类/对象测试 |
| `tests/functions/` | 函数调用测试 |
| `tests/generators/` | Generator/yield 测试 |
| `tests/strings/` | 字符串操作测试 |
| `tests/operators/` | 运算符测试 |
| `tests/transformations/` | 编译器转换/优化测试 |
| `tests/constants/` | 常量测试 |
| `tests/constructs/` | 语言结构测试 |
| `tests/traits/` | Trait 测试 |
| `tests/reflection/` | Reflection 测试 |
| `tests/spl/` | SPL 测试 |
| `tests/bcmath/` `tests/hash/` `tests/pcre/` | 扩展测试 |
| `tests/pdo/` `tests/ftp/` `tests/openssl/` | 数据库/网络扩展 |
| `tests/gd/` `tests/xml/` `tests/zip/` | 图形/XML/ZIP 扩展 |
| `tests/web/` `tests/scripting/` | Web/脚本集成测试 |

测试运行方式：通过 .NET 测试框架（xUnit/NUnit）执行编译后的程序集。

**AOT 借鉴:**
- `tests/transformations/` 目录专门测试编译器优化——AOT 可以建立类似的优化正确性测试
- `tests/arrays/lazy_copy/` 专门测试写时复制行为——AOT 也可以建立 COW 相关的专项测试

### 3.2 Visual Studio 深度集成

PeachPie 提供完整的 IDE 体验：
- **Visual Studio Extension** — 项目管理、智能感知、调试、性能分析
- **VS Code / Rider 支持** — 通过 OmniSharp / LSP
- **NuGet 包管理** — PHP 库可以作为 NuGet 包发布和引用

**AOT 借鉴:**
- 可以提供 VSCode 插件（Task 集成 + 项目模板）
- 当前 AOT 的 `project.yml` 可以用 JSON Schema 提供 IDE 自动补全

### 3.3 命令行工具链

```bash
# PeachPie 的 CLI 体验
dotnet peach build              # 编译 PHP 项目
dotnet peach run                # 运行编译后程序
dotnet peach publish            # 独立发布
dotnet peach add <package>      # 添加依赖
```

**AOT 借鉴:**
```bash
# 可以设计类似的 CLI
php-aot build                    # 编译项目
php-aot run                      # 编译并运行
php-aot new <name>               # 创建新项目
```

---

## 四、类型系统与互操作分析

### 4.1 PeachPie 类型映射

| PHP 类型 | PeachPie 运行时类型 | .NET CLR 类型 |
|----------|-------------------|---------------|
| null | `PhpTypeCode.Null` | `null` (任何引用类型) |
| bool | `PhpTypeCode.Boolean` | `bool` |
| int | `PhpTypeCode.Long` | `long` |
| float | `PhpTypeCode.Double` | `double` |
| string | `PhpTypeCode.String` / `MutableString` | `string` / `PhpString` |
| array | `PhpTypeCode.PhpArray` | `PhpArray` |
| object | `PhpTypeCode.Object` | `object` (具体类) |
| reference | `PhpTypeCode.Alias` | `PhpAlias` |

### 4.2 PHP ⇄ C# 互操作

PeachPie 的双向互操作是其最突出的差异化优势：

**C# 调用 PHP:**
```csharp
// PHP 类编译后变成 .NET 类，C# 可以直接 new 和调用
var phpObj = new MyPhpClass(ctx);
phpObj.someMethod(arg1, arg2);
```

**PHP 调用 C#:**
```php
// PHP 中可以直接使用 .NET 类型
$list = new \System\Collections\Generic\List<int>;
$list->Add(42);
```

互操作的实现依赖于：
- `GhostMethodBuilder` 生成适配方法
- `ConversionsExtensions` 处理 PhpValue ↔ CLR type 自动转换
- `DynamicOperationFactory` 处理动态方法调用转发

### 4.3 与 AOT 编译器的语法差异

| 特性 | PeachPie | AOT Compiler |
|------|----------|-------------|
| 基础 PHP 版本 | 8.0+ (目标) | 8.2+ |
| 类型标注 | 可选（逐渐丰富） | 可选 (phpstan 注解) |
| 命名空间 | 标准 PHP | 标准 PHP |
| 泛型 | 无 | 无 |
| C# 互操作 | 完整（一等公民） | 仅 FFI 扩展 |
| .NET 生态系统 | 完全兼容 | 不相关 |
| MSBuild 集成 | 完整 | 无 |
| 反射 | 部分支持 | 不支持 |
| yield/generator | 支持 | 支持 |

---

## 五、总结与优先级建议

| 优先级 | 技术 | 难度 | 收益 | 说明 |
|--------|------|------|------|------|
| **P0** | TypeRefMask bitset 类型系统 | 中 | 极高 | 联合类型、类型收窄、不可空标记——所有优化 pass 的基础 |
| **P1** | ConditionBranch 类型收窄 | 低 | 高 | `is_int()`/`is_string()` 等检查函数自动收窄，当前 SSA 未覆盖 |
| **P1** | AnalysisFacts 编译期求值 | 中 | 高 | `function_exists`/`class_exists`/`defined` 编译期折叠 |
| **P1** | FlowState worklist 分析框架 | 中 | 高 | 结构化变量类型状态、标准 merge 操作 |
| **P2** | Roslyn 式编译器分层 | 高 | 中 | Syntax→Semantic→IR→CodeGen 清晰分层，当前 Preprocessor/CompilerBase 职责混杂 |
| **P2** | 条件声明检测 | 低 | 中 | 消除不可达函数/类，减小编译产物 |
| **P2** | MSBuild 集成 / CLI 工具统一 | 低 | 中 | 标准化项目配置、CI 友好 |
| **P3** | PhpValue tagged union | 高 | 中 | 紧凑内存布局，但替换 Variant/zval 工程量大 |
| **P3** | DelayedTransformations | 低 | 低 | 仅在并行编译时有意义 |
| **P4** | GhostMethodBuilder 互操作 | 极高 | 低 | 需要 .NET 或类似 FFI 运行时 |
| **P4** | IDE 深度集成 | 高 | 低 | VSCode 插件投资回报有限 |

### 关键认识

1. **PeachPie 最大的优势在于 .NET 生态集成**——这是架构选择带来的天然优势，而非单项技术创新。AOT 可以借鉴其 "编译器 SDK + 标准化 CLI" 的思路，但不需要移植具体技术。

2. **TypeRefMask bitset 是最直接可移植的设计**——用 64-bit 位集表示类型，同时支持联合类型、子类标记、引用标记。这是 HHBBC 的 trep 和 KPHP 的类型分析的共同特征，说明 bitset 类型格是 PHP 编译器的最佳实践。

3. **ConditionBranch 模式是低成本高收益的增强**——在条件分支分析中携带 "期望结果" 信息，使类型检查函数能自动收窄变量类型。AOT 的 SSA 分析可以立即采用。

4. **GhostMethodBuilder 揭示了互操作的通用模式**——生成 adapter/thunk 方法桥接两种语言的调用约定。虽然 AOT 的目标是 C++ 而非 .NET，但如果有跨语言调用需求（如 PHP 调用 C 扩展），此模式可复用。

5. **PeachPie 约 530 个测试**明显少于 KPHP (75+ 目录) 和 HHVM (14,675)，说明其成熟度相对较低。但其测试按功能模块和优化类型分类的方式值得参考。

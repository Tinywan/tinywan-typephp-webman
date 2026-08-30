# PHPStan 设计分析：可引入 AOT 编译器的模式与模块

本文档分析了 PHPStan 项目 (`projects/phpstan-src/`) 的架构设计，识别出可引入 AOT 编译器的设计模式和模块。

---

## 概览：四大优先级的可迁移设计

| 优先级 | 设计/模块 | 实施量级 | 前置依赖 | 收益 |
|--------|----------|---------|---------|------|
| P1 | Type 对象层次 (#1) | ~1000 行 | 无 | 类型推导精度质的提升 |
| P1 | TrinaryLogic (#2) | ~80 行 | 无 | 类型查询不再给出错误答案 |
| P1 | TypeCombinator (#3) | ~300-400 行 | #1 | 消除散落的类型字符串拼接 |
| P2 | Rule 系统 (#4) | 接口+Registry ~80 行 | 基本独立 | 架构模块化，易测试易扩展 |
| P2 | Collector 双阶段分析 (#5) | ~200 行 | #4 | 跨文件全局优化 |
| P2 | Extension 注册机制 (#6) | ~100 行 | #4 | 框架级插件系统 |
| P3 | TypeSpecifier / 类型窄化 (#7) | ~200 行 | #1, #2 | if/else 分支类型精化 |
| P3 | Immutable Scope (#8) | ~300 行 | #1, #2 | 表达式级类型追踪 |
| P4 | PHPDoc Pipeline (#9) | 可复用 phpstan/phpdoc-parser | #1 | 利用现有成熟解析器 |
| P4 | NeverType (#10) | 含于 #1 | #1 | 死代码消除，矛盾类型检测 |

---

## 1. Object-Oriented Type 系统（替代基于字符串的类型表示）

### 现状

AOT 编译器用字符串常量表示类型：

```php
const TYPE_INT = 'int';
const TYPE_FLOAT = 'float';
const TYPE_STR = 'string';
// 复合类型用字符串拼接
// 'int|string', 'string|null'
```

这导致类型运算（合并、比较、查询）必须手动解析和拼接字符串。

### PHPStan 方案

PHPStan 有一个 `Type` 接口，定义约 100 个方法。每种类型是一个类：

```
Type (interface)
├── StringType
├── IntegerType
├── FloatType
├── BooleanType
├── NullType
├── MixedType        (顶类型，带 subtracted type 支持)
├── NeverType        (底类型，无可能值)
├── VoidType
├── ArrayType
├── ObjectType
├── CallableType
├── UnionType        (A|B|C，持有 flat list<Type>)
├── IntersectionType (A&B&C)
├── ConstantStringType, ConstantIntegerType, ...
├── IntegerRangeType
└── Accessory*Type   (用于 IntersectionType 中的精化)
```

**核心设计原则：永远不要用 `instanceof` 判断类型身份。** 必须使用 `is*()` 方法：

```php
// 错误 —— 漏掉了 UnionType 和 IntersectionType
$type instanceof StringType

// 正确 —— UnionType 会委托给内部类型并组合结果
$type->isString()->yes()
```

**关键子接口：**

- `CompoundType`：标记需要双向类型比较的类型（UnionType, IntersectionType）。添加 `isAcceptedBy()`, `isSubTypeOf()` 方法，实现双分派协议。
- `SubtractableType`：支持差集操作（如 `mixed~null`）。

### isSuperTypeOf / accepts 双分派协议

这是 PHPStan 类型系统最核心的设计模式：

```
简单类型 (StringType)::isSuperTypeOf(Type $otherType):
  if $otherType 是 CompoundType:
    return $otherType->isSubTypeOf($this)   // 反向委托
  // 自身逻辑 ...
  return No

复合类型 (UnionType)::isSubTypeOf(Type $otherType):
  foreach innerType in $this->types:
    results[] = $otherType->isSuperTypeOf(innerType)
  return extremeIdentity(results)  // ALL 必须是 subtype
```

**要点：** 简单类型不需要理解复合类型的语义。新增复合类型时不需要修改任何简单类型的比较逻辑。

### AOT 采用建议

AOT 编译器不需要 PHPStan 全部的复杂度。第一版只需这些具体类型：

```
IntegerType, FloatType, StringType, BoolType,
NullType, MixedType, NeverType, ArrayType, ObjectType, UnionType
```

不需要：IntersectionType, AccessoryType, TemplateType, Constant*Type, IntegerRangeType, EnumType。

---

## 2. TrinaryLogic（三值逻辑）

### 现状

AOT 编译器用 boolean 判断类型属性。但 `mixed` 类型意味着 "可能是 string，也可能是 int"，boolean 无法表达这种不确定性。

### PHPStan 方案

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

使用示例：

```php
// MixedType::isString() → maybe（mixed 可能是 string）
// IntegerType::isString() → no
// UnionType(int|string)::isString() → maybe

$type->isString()->yes()   // 确定是 string
$type->isString()->no()    // 确定不是 string
$type->isString()->maybe() // 不确定
```

### AOT 采用建议

直接移植，约 80 行代码，无外部依赖。所有类型查询方法用 TrinaryLogic 替代 boolean。

---

## 3. TypeCombinator —— 类型规范化引擎

### 要解决的问题

禁止直接 `new UnionType(...)`。所有 union/intersect/remove 操作必须经过 TypeCombinator，确保类型表示始终是规范化的。

### 三个核心操作

#### `union(Type ...$types): Type`

**算法流程：**

1. **Fast path**：0 个参数 → `NeverType`；1 个参数 → 直接返回；2 个参数检查 `never`/`mixed`/相同对象
2. **扁平化**：`union(A, union(B, C), D)` → 展开为 `[A, B, C, D]`
3. **过滤 NeverType**：`union(int, never, string)` → `[int, string]`
4. **分类提取**：将类型分为 scalar/array/enum/integerRange/generic 五类，分批处理
5. **标量消解**：`ConstantIntegerType(3) | IntegerType` → `IntegerType`；`true | false` → `BooleanType`
6. **Pairwise 比较**：
   - `IntegerRangeType` 相邻区间合并：`int<0,5> | int<3,10>` → `int<0,10>`
   - Subtype/supertype 消除：`Foo extends Bar` ⇒ `Foo | Bar` = `Bar`
   - `int[] | string[]` → `(int|string)[]`
7. **收尾**：0 个→NeverType；1 个→直接返回；否则 `new UnionType(array_values($types), true)`

#### `intersect(Type ...$types): Type`

**算法流程：**

1. 有 `NeverType` → 直接返回 never
2. **分配律展开**：`A & (B|C)` → `(A&B) | (A&C)`，然后对每个子项递归
3. **扁平化**：`A & (B & C)` → 展开为 `[A, B, C]`
4. **Pairwise 双向比较**：
   - `IntegerType & ConstantIntegerType(5)` → `ConstantIntegerType(5)`（Child & Parent = Child）
   - `int & string` → `NeverType`（矛盾）
   - SubtractableType 差集交接
5. **矛盾检测**：`isSuperTypeOf` 返回 `no` → `NeverType`

#### `remove(Type $fromType, Type $typeToRemove): Type`

```
remove(int|string, string)  = int
remove(int|string|null, null) = int|string
remove(int, string)         = int          // 要移除的不在里面
remove(string, string)      = never        // 完全移除
remove(mixed, Foo)          = mixed~Foo    // 差集类型
```

### AOT 采用建议

精简版 TypeCombinator（~300-400 行），覆盖 AOT 需要的基础类型的 union/intersect/remove。不需要 PHPStan 复杂的数组 shape 处理、accessory type 传播、IntegerRange 合并等逻辑。

### 收益

| 场景 | 当前 | TypeCombinator 后 |
|------|------|-------------------|
| `int\|int` 的变量赋值 | 字符串 `'int\|int'` | 自动简化为 `IntegerType` |
| `mixed\|string` 的返回值 | 不知道如何简化 | 自动简化为 `MixedType` |
| 两个分支类型合并 | 手动拼接 | `union()` 自动去重去 subtype |
| `int & string` 交集 | 无法检测 | 返回 `NeverType`（编译错误） |

---

## 4. Rule-based 分析 Pass 系统

### 核心接口

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

每个 Rule 声明它关心哪种 AST 节点类型，以及在发现该节点时做什么。

### 注册机制

通过 PHP 8 Attribute 声明级别：

```php
#[RegisteredRule(level: 0)]
final class CallMethodsRule implements Rule
{
    public function getNodeType(): string { return MethodCall::class; }
    public function processNode(Node $node, Scope $scope): array { ... }
}
```

### Registry 实现

`LazyRegistry` 从 DI 容器收集所有带 `phpstan.rules.rule` 标签的服务，按 `getNodeType()` 返回值建立索引。

**关键设计：** `getRules($nodeType)` 不仅匹配精确的类名，还匹配所有父类和接口：

```php
public function getRules(string $nodeType): array
{
    // $nodeType = MethodCall::class
    // parentNodeTypes = [MethodCall, Expr, NodeAbstract, Node, ...]
    // 匹配所有注册在这些父类/接口上的 Rule
    $parentNodeTypes = [$nodeType] + class_parents($nodeType) + class_implements($nodeType);
    // ...
}
```

这意味着注册为 `Node\Expr` 的 rule 会匹配**所有**表达式类型。

### 运行时调度

在 AST 遍历的每个节点上：

```php
$nodeType = get_class($node);
foreach ($this->ruleRegistry->getRules($nodeType) as $rule) {
    $ruleErrors = $rule->processNode($node, $scope);
    // 转换和收集错误 ...
}
```

极其简洁，没有 switch，没有 if-else 链。

### Collector 双阶段分析

Collector 接口与 Rule 几乎相同，但返回收集的数据（而不是错误）：

```php
interface Collector
{
    public function getNodeType(): string;
    /** @return TValue|null */
    public function processNode(Node $node, Scope $scope);
}
```

**阶段 1（per-file）：** 在每个文件被分析时，collector 收集数据。

**阶段 2（global）：** 所有文件分析完后，创建 `CollectedDataNode` 封装所有数据，运行注册在其上的规则：

```php
$node = new CollectedDataNode($analyserResult->getCollectedData(), $onlyFiles);
foreach ($this->ruleRegistry->getRules(CollectedDataNode::class) as $rule) {
    $ruleErrors = $rule->processNode($node, $scope);
}
```

`CollectedDataNode::get(string $collectorType): array<string, list<TValue>>` 按文件路径索引收集到的数据。

### AOT 编译器具体应用

| Rule | 触发节点 | 作用 |
|------|----------|------|
| `BinaryOpCodegenRule` | `Expr\BinaryOp` | 生成 C++ 运算代码 |
| `MethodCallCodegenRule` | `Expr\MethodCall` | 方法调用代码生成，虚调用/直调判断 |
| `TypeCheckInsertRule` | `Param` / `Return_` | 函数入口/出口插入运行时类型检查 |
| `DeadCodeEliminateRule` | `CollectedDataNode` | 跨文件分析，移除未调用函数 |
| `DevirtualizeRule` | `CollectedDataNode` | 单实现虚方法 → 直接调用 |
| `InlineDecisionRule` | `CollectedDataNode` | 基于调用频率和函数大小决定内联 |
| `ConstantFoldRule` | `Expr\BinaryOp` | 编译期常量折叠 |
| `BoxOptimizationRule` | `Expr\Assign` | std container 的 Box 逃逸分析 |

**O0/O1/O2 分级：**

```php
#[RegisteredRule(level: 0)]  // 基础代码生成，永远必须
class BinaryOpCodegenRule implements Rule { ... }

#[RegisteredRule(level: 1)]  // O1 优化
class ConstantFoldRule implements Rule { ... }

#[RegisteredRule(level: 2)]  // O2 激进优化
class InlineDecisionRule implements Rule { ... }
```

### AOT 采用建议

渐进式迁移，不需要一次重写整个编译器：

1. 定义 `Rule` 接口和 `Registry`（约 80 行代码）
2. 把 `parseStmts()` 的一个独立功能提取为第一个 Rule
3. 逐步迁移剩余 switch 分支
4. 引入 `Collector` + `CollectedDataNode` 用于跨文件优化

Rule 系统可以与现有 switch 调度共存——先让 Rule 处理它能处理的节点，其他 fallback 到原有逻辑。

---

## 5. Extension 注册机制

### PHPStan 方案

PHPStan 有数十个扩展接口，通过 DI 容器的 service tag 机制注册：

```
DynamicMethodReturnTypeExtension → tag: phpstan.broker.dynamicMethodReturnTypeExtension
FunctionTypeSpecifyingExtension  → tag: phpstan.typeSpecifier.functionTypeSpecifyingExtension
TypeNodeResolverExtension        → tag: phpstan.phpdoc.typeNodeResolverExtension
MethodsClassReflectionExtension  → tag: phpstan.broker.methodsClassReflectionExtension
PropertiesClassReflectionExtension → tag: phpstan.broker.propertiesClassReflectionExtension
...
```

扩展在核心逻辑之前被调用，有机会覆盖默认行为：

```php
public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
{
    foreach ($this->extensions as $extension) {
        $type = $extension->resolve($typeNode, $nameScope);
        if ($type !== null) {
            return $type;  // 扩展处理了，短路核心逻辑
        }
    }
    // 核心逻辑 ...
}
```

### AOT 应用

框架特定的编译优化可通过扩展插件提供，而不是修改编译器核心：

```php
interface MethodCallOptimizationExtension
{
    /** 返回优化后的 C++ 代码，或 null 表示不处理 */
    public function optimize(MethodCall $call, Scope $scope): ?string;
}
```

---

## 6. TypeSpecifier —— 类型窄化引擎

### 解决的问题

当 AOT 编译器遇到 `if ($x instanceof Foo)` 时，在分支内部 `$x` 的类型应该被精化为 `Foo`。这可以生成更高效的 C++ 代码（直接调用 Foo 的方法，不走虚表）。

### PHPStan 方案

`TypeSpecifier` 分析条件表达式，决定如何在 truthy/falsy 分支中窄化类型：

| 条件 | Truthy 窄化为 | Falsey 窄化为 |
|------|-------------|--------------|
| `$x instanceof Foo` | `Foo` | 移除 `Foo` |
| `$x === null` | `NullType` | 移除 `NullType` |
| `is_array($x)` | `ArrayType` | 移除 `ArrayType` |
| `$x`（truthy） | 移除 falsey 类型 | 仅保留 falsey 类型 |
| `$x > 0` | `int<1, max>` | `int<min, 0>` |

### 类型窄化流水线

```
条件表达式
  → TypeSpecifier::specifyTypesInCondition(scope, expr, context)
  → SpecifiedTypes { sureTypes[], sureNotTypes[] }
  → MutatingScope::filterBySpecifiedTypes(types)
  → 新的 scope（类型已窄化）
```

### AOT 应用

简化版 TypeSpecifier（~200 行），处理：
- `instanceof` 窄化
- `=== null` / `!== null` 窄化
- `is_array()`、`is_string()`、`is_int()` 等函数窄化
- `BooleanAnd`/`BooleanOr` 链式窄化

---

## 7. Immutable Scope（不可变作用域）

### 现状

AOT 的 `FunctionContext` 使用可变公共数组，仅按变量名追踪类型：

```php
class FunctionContext {
    public array $localVars = [];          // 仅变量名 → 类型
    public int $scopeLevel = 0;            // 简单嵌套计数
    public array $scopeLayouts = [];       // ScopeContext 是空类
    public bool $inLoop = false;
    public bool $inClosure = false;
}
```

### PHPStan 方案

`MutatingScope` 是**不可变**的持久化数据结构。每次变更返回新实例：

```
scope.assignVariable('x', stringType)        → new scope
scope.filterByTruthyValue(instanceofExpr)     → new scope (窄化后的类型)
scope.filterByFalseyValue(instanceofExpr)     → new scope (移除后的类型)
scope.mergeWith(elseScope)                    → new scope (交集)
```

按**表达式字符串**追踪类型，而非仅按变量名：

```
'$a'         → ExpressionTypeHolder($a, IntegerType, Yes)
'$a[0]'      → ExpressionTypeHolder($a[0], StringType, Yes)
'$a->prop'   → ExpressionTypeHolder($a->prop, FooType, Yes)
'strlen($a)' → ExpressionTypeHolder(strlen($a), IntegerType, Yes)
```

这意味着给 `$a` 赋值会使 `$a[0]` 和 `$a->prop` 的类型缓存失效。

### AOT 采用建议

精简版（~300 行），核心能力：

- 不可变 scope，支持快照和合并
- 表达式键的类型追踪（至少支持变量和数组元素）
- `TrinaryLogic` 确定度追踪（分支合并后的 maybe 状态）

---

## 8. PHPDoc Pipeline

### PHPStan 方案

三阶段流水线：

```
PHPDoc 注释字符串
  → Lexer + Parser (phpstan/phpdoc-parser)
  → PhpDocNode (原始 AST)
  → TypeNodeResolver::resolve(TypeNode, NameScope)
  → PHPStan Type 对象
```

`TypeNodeResolver` 用一个 `switch` 分发到 30+ 种标识符类型：

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

### AOT 应用

AOT 可以复用 `phpstan/phpdoc-parser` 来解析 `@param`、`@return`、`@var` 注解，然后将类型 AST 节点映射到 AOT 自己的 Type 系统。`NameScope`（追踪 namespace + use imports）也是可直接复用的概念。

---

## 9. NeverType（底类型）

表示"不可能有任何值"的类型，即空集：

```
union(int, never)    = int       // never 是 union 的中性元
intersect(string, never) = never // never 是 intersect 的吸收元
```

### AOT 应用

- 死代码消除：表达式窄化为 NeverType → 该路径不可达
- 错误传播：矛盾的类型组合产生 NeverType
- `void` 函数返回：本质上就是 NeverType 在 return 位置

---

## 推荐采用顺序

```
Phase 1 (基础层): Type 接口 + 具体类型 + TypeCombinator + TrinaryLogic
  └── 替代字符串类型表示，预计减少散落的类型字符串操作

Phase 2 (模块化): Rule 接口 + Registry + Attribute 注册
  └── 分解 parseStmts/parseExpr 的巨大 switch

Phase 3 (分析层): TypeSpecifier + 基础 Immutable Scope
  └── 启用 if/else 分支类型窄化

Phase 4 (优化层): Collector + CollectedDataNode
  └── 启用跨文件全局优化（去虚拟化、死代码消除、内联决策）

Phase 5 (生态层): Extension 注册 + PHPDoc Pipeline
  └── 启用框架插件和注解驱动的类型信息
```

每一层都建立在上一层之上，且每一层都可以独立交付价值。

---

## 不应引入的 PHPStan 特性

| 特性 | 原因 |
|------|------|
| Generics / `@template T` | 复杂度极高，AOT 当前不需要 |
| IntersectionType + Accessory types | `non-empty-string` = `string & non-empty` 这类设计过度工程化 |
| BetterReflection (静态反射) | AOT 已经加载文件，不需要避免运行时副作用 |
| 完整 MutatingScope (5000+ 行) | 不可变 scope 模式值得借鉴，但 300 行足够 |
| ConditionalExpressionHolder | 复合条件的惰性窄化，先做基础 TypeSpecifier |
| Enum / ConstantArrayType shape | 小众，初期不需要 |

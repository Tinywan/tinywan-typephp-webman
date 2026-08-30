# AOT 编译器核心重构计划

> 针对 `Translator`、`CompilerBase`、`Preprocessor` 的下一阶段 OOA/OOD/OOP 重构，请以 [CORE_OOA_OOD_OOP_REFACTORING_PLAN.md](CORE_OOA_OOD_OOP_REFACTORING_PLAN.md) 为实施基线。本文档保留此前模块化重构的历史计划。

## 背景

当前 AOT 编译器核心类承担了过多职责，尤其是 `CompilerBase`、`Translator` 等类同时包含 AST 分发、类型推导、属性访问解析、调用解析、代码生成、诊断信息、上下文状态维护等逻辑。随着功能持续增加，这种结构会带来以下问题：

- 封装性不足，修改一个语义点时容易影响多个代码路径。
- 代码复用不足，同类逻辑在普通属性、静态属性、nullsafe、assignment、isset/empty/refval 等路径中重复实现。
- 编译期检查容易出现绕过路径，例如某些动态 fallback 没有复用静态 resolver。
- 单个类代码量过大，review、测试定位和长期维护成本持续升高。
- 设计边界不清晰，类型系统、符号解析、属性访问、调用生成之间耦合过深。

本计划用于指导后续逐步重构，目标是改善架构质量，同时避免一次性大规模重写带来的行为回归风险。

## 核心原则

1. 渐进式重构，禁止一次性重写核心编译流程。
2. 优先抽离无状态或低状态依赖的纯逻辑，再抽离依赖编译上下文的逻辑。
3. 每一步重构应尽量保持行为不变，行为变更必须单独说明并补测试。
4. 新模块应围绕稳定领域建模，而不是简单按 AST 节点机械拆分。
5. 优先使用小型 service/helper、明确 DTO、resolver、emitter；仅在边界稳定后再引入 Visitor、Strategy 等模式。
6. 所有错误信息保持 PHP 风格，不暴露实现细节，不使用 “AOT 禁止” 这类表述。
7. 每个阶段必须有 phpunit 或 phpt 回归验证，尤其是属性、类型、调用、继承、异常等高风险路径。
8. 面向 resolver/emitter 暴露的编译器接口优先保持只读。只读查询接口可以按需设为 public，写操作必须格外谨慎，避免绕过统一状态管理。

## 目标架构方向

### CompilerBase

最终应收敛为编译上下文、公共工具、AST 调度入口和跨模块协作层，不再直接承载大量领域逻辑。

保留职责：

- 当前文件、函数、类、方法上下文管理。
- 临时变量、局部变量、作用域状态维护。
- AST 顶层分发入口。
- fatal/warning 的统一入口。
- 与下层 resolver/emitter 的协作。

逐步移出职责：

- 类型声明解析和类型兼容判断。
- 属性可见性、属性 offset、typed property 检查。
- 函数和方法调用解析。
- 复杂表达式代码生成。
- union/intersection/nullable runtime typecheck 生成。

### Translator

保留文件级、类级、函数级编译流程控制，逐步减少具体语义检查和表达式生成逻辑。

保留职责：

- 文件扫描和转换入口。
- 类、函数、方法代码块生成流程。
- class/function/constant/property metadata 注册。
- 编译产物组织。

逐步移出职责：

- 继承兼容检查的细节逻辑。
- trait 属性冲突检查细节。
- 参数、返回值、属性类型兼容判断。

## 模块拆分计划

### 1. TypeSystem

职责：

- 类型声明解析。
- PHP 类型到 AOT 内部类型映射。
- nullable、union、intersection 类型展开。
- 参数、返回值、属性、常量的类型兼容判断。
- 静态类型与 runtime typecheck 的边界定义。
- 类型字符串格式化。

建议子模块：

- `TypeResolver`
- `TypeCompatibility`
- `TypeCheckEmitter`
- `TypeStringFormatter`

设计要求：

- union、intersection、nullable 在静态阶段仍可按 mixed/any 处理，但必须保留 runtime typecheck 信息。
- `self`、`parent`、`static` 等特殊类型名的解析规则必须集中，避免多个路径实现不一致。
- 类型错误信息必须包含函数、方法、参数、属性等必要上下文。

### 2. SymbolResolver

职责：

- 命名空间解析。
- use alias 解析。
- `self`、`parent`、`static` 解析。
- 类、接口、trait、函数、常量名称规范化。
- 动态符号和静态可解析符号的边界判断。

建议接口：

- `resolveClassName(NodeAbstract $node): SymbolResolution`
- `resolveFunctionName(NodeAbstract $node): SymbolResolution`
- `resolveMethodScope(NodeAbstract $node): SymbolResolution`
- `resolveClassConstScope(NodeAbstract $node): SymbolResolution`

设计要求：

- 不同调用路径不能重复实现 `self/parent/static` 规则。
- 对无法静态确定的符号，应明确返回 dynamic 状态，而不是默默退化为字符串拼接。

### 3. PropertyAccessResolver

职责：

- 对象属性访问解析。
- 静态属性访问解析。
- nullsafe 属性访问静态检查。
- private/protected/public 可见性检查。
- native property offset 查找。
- typed property 写入 typecheck 信息生成入口。
- static-vs-instance 属性误用检查。

建议接口：

- `resolveInstancePropertyAccess(PropertyAccessRequest $request): PropertyAccessResult`
- `resolveStaticPropertyAccess(StaticPropertyAccessRequest $request): PropertyAccessResult`
- `assertReadable(PropertyAccessResult $result): void`
- `assertWritable(PropertyAccessResult $result): void`
- `emitRead(PropertyAccessResult $result): string`
- `emitWrite(PropertyAccessResult $result, string $value): string`

需要统一覆盖的路径：

- `$obj->prop`
- `$obj?->prop`
- `Class::$prop`
- `self::$prop`
- `parent::$prop`
- `static::$prop`
- `isset($obj->prop)`
- `empty($obj->prop)`
- `refval($obj->prop)`
- 普通赋值、复合赋值、自增自减、unset。

第一优先级建议从本模块开始，因为近期问题集中在属性访问和可见性绕过，测试边界相对清晰。

### 4. CallResolver

职责：

- 函数调用解析。
- 对象方法调用解析。
- 静态方法调用解析。
- native call 与 dynamic call 选择。
- named args、unpack、by-ref 参数处理。
- closure 和动态 callable 退化规则。

建议接口：

- `resolveFunctionCall(CallRequest $request): CallResolution`
- `resolveMethodCall(MethodCallRequest $request): CallResolution`
- `resolveStaticCall(StaticCallRequest $request): CallResolution`
- `emitCall(CallResolution $resolution): string`

设计要求：

- 静态 function 和内置 function 参数信息明确时，可以自动转引用。
- 动态调用、closure、编译期无法获取参数 by-ref 信息时，必须要求显式 `refval()`。
- 使用 unpack 并追加尾部 named args 时，应退化为 dynamic call，不能走 native call。

### 5. ExpressionEmitter

职责：

- 表达式级代码生成。
- 将大型 `parseExpr()` 分发逻辑逐步拆分。
- 复用 resolver 结果生成 C++ 代码。

建议按领域拆分，而不是一开始拆成大量 AST visitor：

- `AssignmentEmitter`
- `PropertyEmitter`
- `CallEmitter`
- `ArrayEmitter`
- `ControlExprEmitter`
- `ObjectEmitter`

设计要求：

- 先保持现有 `parseExpr()` 作为调度入口。
- 每次只迁移一组表达式，迁移后跑对应测试组。
- 对表达式副作用、求值顺序、临时变量生成必须保守处理。

### 6. Diagnostic

职责：

- 统一 fatal/warning 构造。
- 提供上下文增强能力。
- 保证错误消息风格接近 PHP。

建议能力：

- 当前函数/方法名。
- 参数名。
- 属性名和类名。
- 源码位置。
- 声明位置与使用位置。

设计要求：

- 错误信息不出现 “AOT” 作为行为主体。
- 对用户可修复的问题，应包含准确符号名。
- 编译期能发现的问题优先编译期 fatal，不应依赖运行时 typecheck 异常兜底。

## 阶段计划

### 阶段 1：属性访问解析模块化

目标：

- 抽离 `findNativeProperty()`、`findNativeStaticProperty()`、`canAccessProtectedProperty()` 等属性访问解析逻辑。
- 保持生成代码基本不变。
- 建立统一的 `PropertyAccessResult`，承载属性声明、声明类、访问类、是否 native、offset、是否 dynamic 等信息。

范围：

- 普通对象属性读取。
- 静态属性读取。
- nullsafe 属性静态检查。
- 属性可见性检查。

当前进展：

- 已建立 `PropertyAccessResolver` 和 `PropertyAccessResult` 作为属性访问解析的第一层抽象。
- 实例属性读取已通过 resolver 的 `resolveNativeInstanceProperty()` 显式接口完成 native property 查找、static-vs-instance 检查和可见性检查。
- `findNativeStaticProperty()` 已通过 resolver 的 `resolveNativeStaticProperty()` 显式接口完成静态属性检查。
- nullsafe 属性链检查已通过 resolver 的 `resolveNullsafePropertyChain()` 显式接口完成类名推进和可见性检查。
- 旧的 `CompilerBase::findNativeProperty()` 泛型入口已移除，避免后续继续扩散带 `$static` 布尔参数的访问模式。
- 旧的 `findNativeStaticProperty(..., &$class)` by-ref 协议已移除，静态属性读取改为 `StaticPropertyFetchTarget` 和 `StaticPropertyFetchResolution` 显式 DTO。
- 实例属性读取的目标类解析已抽离为 `InstancePropertyFetchTarget`，`getPropertyIdentifier()` 不再混合目标解析、resolver 调用和动态 fallback 分支。
- 原先散落在 AST attribute 上的 `nativeProperty`、`nativePropertyDef`、`nativeClassDef` 已合并为 `NativePropertyAccess` metadata，避免三者状态不一致。
- `nativePropertyVar`、`nativePropertyValueSource`、`objectProps`、`staticPropRefs` 的直接读写已收敛到 helper 方法；业务路径不再通过字符串内容判断属性访问语义。
- typed instance property hoist 和 typed static property ref 注册已提取为独立 helper，当前仍保持原有生成代码结构。
- `CompilerBase::isSameClassName()`、`isSameOrSubclassOf()`、`canAccessProtectedProperty()` 已委托 resolver，避免规则继续扩散。
- 已添加 `prepare/convert/idle` 编译阶段状态；`PropertyAccessResolver` 只能在 convert 阶段创建和使用，避免预处理阶段误用不完整的类表状态。
- `PropertyAccessResolver` 已改为依赖 `PropertyAccessContext` 只读接口，而不是完整依赖 `CompilerBase` 大类。
- 已建立 `PropertyAssignTypeInfo`，抽离 typed property 写入的纯 metadata 计算，包括固定类型属性判断、默认值、runtime typecheck 列表和类型字符串。
- 当前迁移保持生成代码不变，后续阶段再统一 read/write emitter。

状态：

- 阶段 1 已基本收尾。后续除非发现属性读取 resolver 绕过或行为回归，否则不再继续扩大阶段 1 范围。
- 阶段 2 已开始；属性写入相关的 assignment、compound assignment、inc/dec、unset、refval 路径仍需继续统一。

验证：

- `phpunit/src/NativePropertyTest.php`
- `phpunit/src/InheritanceErrorTest.php`
- object property 相关 phpt。
- static property 相关 phpt。
- nullsafe 相关 phpt。

### 阶段 2：属性写入路径统一

目标：

- 所有属性写入路径先 resolve，再 emit。
- typed property runtime typecheck 从散落逻辑收敛到属性写入模块。
- 消除 assignment、compound assignment、inc/dec、unset 各自实现属性访问规则的问题。

范围：

- `$obj->prop = $value`
- `$obj->prop += $value`
- `$obj->prop++`
- `unset($obj->prop)`
- `Class::$prop = $value`
- `??=` 相关属性路径。

验证：

- typed property 相关 phpunit/phpt。
- object property optimization 相关 phpt。
- nullsafe 写上下文错误测试。
- private/protected/static 属性错误测试。

当前进展：

- 阶段 2 已开始。
- 已建立 `PropertyWriteTarget` 作为属性写入路径的最小目标 DTO。
- 普通赋值和 `??=` 已接入 `preparePropertyWriteTarget()`，在写入前统一完成属性 target 准备，并通过 `assertCanAssignPropertyWrite()` 与 `wrapPropertyWriteTypeCheck()` 执行静态检查和 runtime typecheck 包装。
- dynamic object property 的 `getProperty()` / `setProperty()` 生成已收敛到 `emitDynamicPropertyRead()` / `emitDynamicPropertyWrite()` helper；普通动态属性赋值、复合赋值、自增自减已复用该入口。
- 复合赋值的动态属性路径已接入 `preparePropertyWriteTarget()`，先统一完成属性写入 target 准备和静态检查。
- `PropertyWriteTarget` 已开始携带安全动态属性写入目标的 object/property 表达式；普通动态属性赋值、复合赋值、自增自减已优先通过 target 级 read/write helper 发射代码。
- 动态属性 `unset`、属性数组维度写入、引用参数/refval/引用赋值中的安全对象属性引用路径已开始复用 target 级 unset/ref helper。
- 对象属性引用表达式的 target/ref 生成已收敛到 `emitDynamicPropertyFetchRef()`；未使用的旧静态属性赋值入口已删除，静态属性赋值继续走统一 assignment target 路径。
- `PropertyWriteTarget` 的动态 object/property 字段已封装为 getter；属性数组维度写入已接入 target 级 append/update emitter。
- 已建立 `emitDynamicPropertyFetchRead/Write/Unset/AppendArray/UpdateArray()` 包装层，调用方只传入属性访问 AST 与可选 target，由 `CompilerBase` 统一选择 target 路径或旧 fallback 路径。
- 普通赋值、复合赋值、自增自减、unset、属性数组维度写入、引用赋值已去除 Parser trait 中对 dynamic target 的直接分支判断，改为复用统一 emitter 包装。
- 为避免改变复杂表达式求值顺序，当前仅对对象部分为变量的动态属性写入填充 target object/property 字段，复杂对象表达式仍保留旧路径。
- 当前步骤对有效代码保持生成逻辑兼容，但会让更多属性写入路径进入统一静态检查；后续继续收敛 static/native property write emitter 与 `??=` 属性写入结果生成。

### 阶段 3：类型系统模块化

目标：

- 抽离类型声明解析、类型兼容、runtime typecheck 生成。
- 明确静态类型推导和运行时 typecheck 的职责边界。
- 减少参数、返回值、属性、常量各自重复处理类型规则。

范围：

- `parseTypeDecl()`。
- `buildTypeCheckFromNode()`。
- 参数 typecheck。
- 返回值 typecheck。
- 属性 typecheck。
- 常量类型处理。

验证：

- union、intersection、nullable 类型测试。
- 参数、返回值、属性 typecheck 测试。
- namespace constant 测试。
- constructor、void expression 相关测试。

### 阶段 4：调用解析模块化

目标：

- 统一函数、方法、静态方法调用解析。
- 明确 native call、dynamic call、closure call 的退化规则。
- 集中处理 named args、unpack、by-ref 参数。

范围：

- `parseFuncCall()`。
- `parseMethodCall()`。
- `parseStaticCall()`。
- call args 解析。
- native/internal/user function 调用路径。

验证：

- named args、unpack 相关 phpt。
- by-ref 参数相关 phpt。
- closure 相关 phpt。
- parent/self/static call 相关 phpt。

### 阶段 5：表达式生成器拆分

目标：

- 将 `parseExpr()` 背后的大量表达式生成逻辑迁移到领域 emitter。
- `CompilerBase` 保持调度和共享上下文能力。
- 降低单文件和单类代码量。

范围：

- AssignmentEmitter。
- PropertyEmitter。
- CallEmitter。
- ArrayEmitter。
- ControlExprEmitter。

验证：

- 每迁移一个 emitter，跑对应测试组。
- 最后跑核心 phpunit 和选定 phpt 回归集。

### 阶段 6：Translator 收敛

目标：

- 将继承兼容、trait 合并、类成员校验等逻辑抽离成专门 checker。
- `Translator` 聚焦编译流程组织。

建议子模块：

- `InheritanceChecker`
- `TraitCompositionChecker`
- `ClassMemberValidator`
- `FunctionSignatureChecker`

验证：

- 继承错误 phpunit。
- trait 相关 phpt。
- interface/abstract/final/readonly 相关 phpt。

## 测试门禁

每个重构 PR 或阶段至少满足：

- 相关 phpunit 必须通过。
- 相关 phpt 必须通过。
- 如果修改 C++/phpx，需要补 gtest 并通过对应测试。
- 如果引入新编译期错误，需要新增固定 fixture 到 `phpunit/code` 或新增 phpt。
- 不使用 `file_put_contents()` 临时生成源码作为新测试方式。

建议按模块维护最小回归集：

- 属性模块：`NativePropertyTest`、`InheritanceErrorTest`、object property、static property、nullsafe。
- 类型模块：type_decl、type_hits、typed property、union/intersection/nullable。
- 调用模块：function call、method call、parent_call、closure、named args、unpack、by-ref。
- 控制流和表达式：ternary、match、goto、loop、array、coalesce、void expression。

## 风险控制

- 不在同一个变更中同时做大规模文件移动和行为变更。
- 每次迁移前先补当前行为的测试，尤其是历史 bug 对应路径。
- 保留旧入口一段时间，通过 adapter 调用新模块，降低切换风险。
- 对动态 PHP 语义保持保守，无法静态确定时不要过度优化。
- 对 AOT 明确不兼容 PHP 历史包袱的地方，应在文档和错误信息中表达为语言规则，而不是实现限制。

## 推荐下一步

从 `PropertyAccessResolver` 开始。

理由：

- 最近 bug 多集中在属性访问、可见性、typed property、nullsafe、static-vs-instance 路径。
- 现有测试较容易扩展。
- 属性访问是类型系统、优化器、调用生成之外相对独立的领域，适合先建立 resolver/result 模式。
- 完成后可直接减少 `CompilerBase` 中的复杂分支，并为后续 ExpressionEmitter 拆分打基础。

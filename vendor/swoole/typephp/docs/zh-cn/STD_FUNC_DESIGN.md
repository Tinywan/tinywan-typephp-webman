# StdFunc / StdArgInfo 类型注解设计

状态：`StdFunc` / `StdArgInfo` 方案已暂缓，不进入当前实现计划；本文仅保留历史设计记录。其中引用的 StdArray 类型注解已经实现。统一术语及容器类型见 [类型注解统一设计](TYPE_ANNOTATIONS.md)。

## 目标与非目标

`StdFunc` 给 callable 声明完整调用契约，约束参数数量、参数值类型、引用方式和返回值。`StdArgInfo` 专门描述一个参数的类型与修饰规则。

本设计不是箭头函数静态执行优化。普通、可静态验证的 PHP 回调仍可通过 Zend Bridge 执行；只有强类型数组/Native 等边界另有要求时才使用受控 TypePHP 调用路径。不得为了签名约束强制把所有闭包改成 C++ lambda。

## 声明形式

```php
function run(
    #[StdFunc(
        Type::Void,
        [
            Type::Int,
            new StdArgInfo(Type::Float, default: 1.0),
            new StdArgInfo(Type::Str, Type::Optional),
            new StdArgInfo(Type::Int, Type::Variadic),
        ],
    )]
    callable $callback
): void {
    $callback(10);
    $callback(10, 2.5, 'tag', 1, 2);
}
```

拟议接口：`StdArgInfo(type, flags = 0, default = <未设置>)`。简单 `Type::Int` 等价于 `new StdArgInfo(Type::Int)`，即必选、非 Nullable、按值参数。

所有参数的 Nullable、Ref、Optional、Variadic 和默认值集中在 `StdArgInfo`，不再使用独立 `optional:` 列表、`variadic:` 参数或嵌套 `RefType` / `NullableType` 参数描述。

PHP 属性参数不允许普通函数调用，不能写 `StdArgInfo(...)` 或 `std::list(...)`。PHP 8.1 起允许属性实参中的 `new` 表达式。TypePHP 只识别白名单类型描述 AST，不执行描述对象构造函数或任意用户代码。[PHP 属性语法](https://www.php.net/manual/en/language.attributes.syntax.php)、[PHP 8.1 new 初始化表达式](https://www.php.net/manual/en/migration81.new-features.php)

公共 `Type::Void`、`Type::Nullable`、`Type::Ref`、`Type::Optional`、`Type::Variadic` 和 `StdArgInfo` 均待新增；编译器内部已有 VOID 不代表公共 Void 描述已实现。

## 标志与组合验证

标志使用可按位或组合的整数，值类型描述与标志空间分开：

| 标志 | 含义 | 是否允许省略 |
|---|---|---|
| 无 | 必选、按值、非 Nullable | 否 |
| `Nullable` | 值允许为 null | 不改变必选性 |
| `Ref` | 可写引用，输入和输出均受类型约束 | 不改变必选性 |
| `Optional` | 可省略，由契约提供缺省值 | 是 |
| `Variadic` | 零个或多个同类型额外实参 | 是，表示零个元素 |

提供 `default` 自动增加 Optional。显式 `default: null` 也算提供默认值，但只有 Nullable（或类型本身允许 null）时才合法；不能隐式增加 Nullable。

必选参数必须在所有 Optional 参数之前；Variadic 只能有一个且必须位于最后。Nullable/Ref 等标志只能用于有意义的值类型，Void 不能作为参数类型。未知标志、冲突主类型和无意义组合给出编译错误。

## Optional 与默认值

未提供显式 default 的 Optional 参数使用以下类型缺省值：

| 类型 | 缺省值 |
|---|---|
| Int / Float | `0` / `0.0` |
| Bool | `false` |
| Str / String | `''` |
| Array | 新的空数组 |
| StdList / StdDict | 带原契约的新空 PHP 数组 |
| Nullable 类型 | `null`，优先于非空类型的零值 |
| 非 Nullable 类对象 | 无安全缺省值，编译错误 |
| 其他类型 | 必须定义安全初始化规则，否则编译错误 |

不自动创建类对象，不为非 Nullable 对象填 null，也不把普通空数组当作 Box。非 Nullable Box 容器的自动创建与销毁规则尚待专项评估，不因 Optional 自动开放。

显式默认值必须符合值类型。第一阶段建议只接受可静态验证的标量常量、null 和安全数组初始化；对象、Box 及其他复杂默认值需要另外设计，不能执行任意工厂函数求值。

默认值是 `StdFunc` 契约的一部分。相同参数类型但默认值不同的两个契约不能无条件互换：缺省调用的可观察行为不同。可以共用底层函数 ABI 类型，但保留各自调用契约或显式适配，不得在类型传导中丢失默认值。

必须区分“未提供 default”和“显式提供 null”。AST 中记录是否出现该实参；缓存/stub 中使用独立 `hasDefault`。将来描述对象的运行时接口也需要未设置哨兵，不能仅以 `default = null` 判断。

## 缺省参数由调用端补齐

这是已确认的语义，与单纯声明“实际回调有默认参数”不同：

```php
// 契约：Optional Int，没有显式默认值，因此缺省为 0。
// 实现：function handler(int $value = 10): void { ... }
// 通过该 StdFunc 省略参数调用时，handler 接收到的是 0，不是 10。
```

调用步骤：先检查实际实参，按 PHP 求值顺序只求值一次，再为省略的固定参数填充契约缺省值，最后传递变长实参。默认数组及引用存储每次调用独立，不得共享可变默认对象。

回调自身默认值不会参与这些已补齐位置。`func_num_args()`、`func_get_args()`、回调中的回溯参数会看到补齐后的固定参数；这一可观察差异是有意设计。

因为调用端补齐固定参数，目标回调的对应参数可以是必选参数，只要能够接受补齐后的值。例如契约 `(Optional Int = 0) -> Void` 可以绑定 `function handler(int $value): void`。不能继续沿用“契约 Optional 则实现必须 Optional”的旧规则。

## Nullable 与回调本身可空

```php
new StdArgInfo(Type::Int, Type::Nullable | Type::Ref);
new StdArgInfo(Type::Int, Type::Nullable | Type::Optional);
```

第一项是必选的 Nullable int 引用；第二项允许省略，缺省 null。Nullable 与 Optional 是独立概念。

回调本身可空不是参数值 Nullable。拟议 `StdFunc(..., nullable: true)` 可单独表达，PHP 类型可省略或使用兼容 `?callable`；非空契约使用省略类型或 `callable`，不允许显式 mixed/any。调用 Nullable callback 必须证明非 null 或插入运行时非空检查。

返回值 Nullable 的具体声明语法尚未确定；`StdArgInfo` 只描述参数，不为解决返回语法而复用它。引用返回暂不支持。

## 引用参数

Ref 是签名的一部分，契约与实际回调的按值/按引用方式必须一致。引用值类型采用不变规则，不能把 `&Int` 当作 `&Nullable(Int)`，也不能通过参数逆变放宽引用写入类型。

调用使用 PHP 原生 `$callback($value)`，不在实参处写 `&`。第一阶段只支持静态明确的可写局部变量及已有安全引用 ABI；常量、计算结果和普通返回值不能作为引用实参。属性、数组元素、引用返回值及引用逃逸另行验收。

不能采用复制入/复制出的临时变量模拟已有变量的引用，必须保留别名、求值顺序、异常和嵌套调用中的可见修改。

`Optional | Ref` 属于目标模型：参数省略时，每次调用创建独立、正确类型的可写存储，活到调用结束，禁止回调保存该引用。Nullable Optional Ref 的缺省存储保存 null。该组合的存储生命周期和逃逸证明尚未实现；第一阶段可先诊断拒绝，不能静默降低为值传递。

未知动态回调不能凭注解获得可信的引用写入权限。

## Variadic 参数

```php
new StdArgInfo(Type::Str, Type::Variadic);
new StdArgInfo(Type::Int, Type::Nullable | Type::Variadic);
```

描述每个额外实参，不是整个参数集合的类型。零个实参就是空集合，不提供一个默认元素，因此 Variadic 不允许 default。显式 Optional 与 Variadic 的组合可规范化为 Variadic，无需新增行为。

固定参数（含 Optional）先占用对应位置，剩余实参进入 Variadic。不能通过位置调用跳过一个 Optional，直接填 Variadic。契约允许无限额外实参时，目标也必须有兼容变长能力。

第一阶段暂不支持 `Variadic | Ref`、命名调用和无法静态证明数量及逐项类型的动态解包。不能利用 PHP 用户函数可能容忍多余实参的行为绕过契约检查。

## 容器作为参数类型

```php
#[StdFunc(
    Type::Void,
    [
        new StdArgInfo(new StdList(MyUser::class), Type::Ref),
        new StdDict(Type::Str, Type::Int),
        new StdVector(Type::Int),
        new StdArray(Type::Int, [100, 200, 8]),
    ],
)]
```

`new StdList` / `new StdDict` / `new StdVector` / `new StdMap` / `new StdOrderedMap` / `new StdArray` 是嵌套类型描述，并复用独立类型注解的规范化契约。StdArray 使用单长度或外到内的维度数组，不使用递归描述对象；`new StdArgInfo(new StdArray(Type::Int, 100))` 可描述一维参数，完整维度必须匹配。

StdArray 是容器类型注解中的特殊形式：第二个实参描述固定形状而非类型，其结构性嵌套不能按 map 的 key/value 或 vector 的单元素类型规则解析。嵌入 StdArgInfo 不改变这一特殊性，详见统一设计中的 StdArray 专节。

种类、键类型和值类型必须匹配；普通 array、var/any 不能自动获得可信契约。list 与整数键 dict 不互换。按值 PHP 数组保留 COW，按引用传递保留别名；Box 仍保留自身共享生命周期模型。

强类型 PHP 数组只能传递给可静态验证并保留同一约束的 TypePHP 回调。现有直接调用采用 `php::Array` / `php::Array &`，而普通 Zend callable 路径可能丢失契约或不支持该 ABI，必须提供受控调用或拒绝，不能仅检查外层 `isCallable()`。

StdList/StdDict Ref 不能逃逸到动态 PHP。StdVector 等的 Ref 属于独立能力，现有 Box 类型注解参数不支持引用，不能因可嵌套描述而默认开放。Native 对象及容器原有逃逸规则不变。

## 回调验证、调用与类型传导

- 可接受签名明确且目标可验证的闭包、箭头函数、TypePHP 函数和方法的一等 callable，以及已有同契约的值。
- 未知动态字符串、动态方法数组、普通 var callable 或无法证明签名的实现，第一阶段拒绝绑定。执行方式是 Zend Bridge，不意味着签名来源必须未知。
- 验证目标能接受全部降低后的调用：固定参数数量和类型、引用方式、Variadic 能力、返回值契约均须成立。
- 参数按值匹配遵循“输入不能收窄，输出不能放宽”；第一阶段可只支持基础类型和 Nullable 的可证明匹配，复杂联合类型及类方差后续扩展。引用类型必须不变，强类型容器种类和参数必须一致。
- 已知错误实参、缺少必选参数和多余实参编译报错。普通值参数的 var/any 实参可插入严格运行时检查，不做隐式转换；不能据此恢复强类型数组或可信回调签名。
- 已验证返回类型传导给调用表达式及接收变量；Void 不能用于值表达式。动态未知返回值不能仅凭注解直接解箱为可信类型。
- 普通局部赋值传播完整契约，包括 Optional 默认值。签名被重绑定、捕获、缓存或作为参数转传时不能丢失默认值和动态边界规则；普通未注解边界不自动保存可信签名。
- 回调绑定的引用传递、动态替换、属性读写和逃逸仍需独立保护；第一阶段以参数声明及受控局部传导为范围，属性功能不自动开放。

## 实现分层与验收计划

1. 注册类型注解及白名单描述 AST，建立 `FunctionSignature` / 参数元数据和缺省哨兵，序列化进入声明缓存及 library stub。
2. 验证回调绑定，检查普通必选参数和返回值，在变量调用处传播返回类型；保留普通 Zend 闭包执行路径。
3. 实现 Nullable、Optional 缺省补齐、Variadic 及求值顺序。签名变化必须使依赖调用方失效，因为默认值位于调用端。
4. 接入已有安全标量引用 ABI，证明别名和异常行为；Optional Ref 生命周期、引用逃逸及受控 typed-array 回调分别验收，不复用不安全临时复制。
5. 在闭合边界前，不开放属性、命名调用、未知动态回调、引用返回和动态解包。

测试矩阵包括标志组合、显式 null 与未设置 default、Nullable 缺省、默认值类型错误、契约与实现默认值不同、目标固定参数必选、参数数量可观察行为、逐项求值一次、Variadic 位置与类型、引用别名/异常/逃逸、typed-array COW 与引用、动态修改拒绝、返回类型和缓存/stub 往返。

## 其他语言的参考与差异

TypeScript 将具体默认值从函数类型中擦除，只保留 Optional；Python callable 规范也允许默认值占位，并检查可确定默认值的类型。本设计借鉴参数种类和值类型的分离，但有意不同：`StdArgInfo` 默认值属于调用契约并由调用端补齐，必须保留在契约元数据中。

PHP 传 null 不触发函数默认值；这里同样只在参数省略时补齐，不把 null 当作“未传”。参考：[TypeScript 函数类型](https://www.typescriptlang.org/docs/handbook/functions)、[Python callable 规范](https://typing.python.org/en/latest/spec/callables.html)、[PHP 参数与引用](https://www.php.net/manual/en/functions.arguments.php)。

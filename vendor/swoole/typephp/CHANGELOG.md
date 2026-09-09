# TypePHP change log

## 0.8.0

### Breaking: native scalar storage is now the default

`use native_types` has been removed. Inferred `int`, `float`, and `bool`
locals now use `php::Int`, `php::Float`, and `php::Bool` by default. Their
storage type is fixed and the compiler never promotes one of these locals to
`php::Var` merely because a later operation needs dynamic PHP semantics.

Two explicit escape hatches remain:

- `use varint_types` is a file-level mode that stores inferred integer values
  in `php::Var`, preserving PHP's overflow-to-float, non-integral division,
  and other Zend integer arithmetic behavior. It does not box `float` or
  `bool` locals.
- `std::any($value)` erases the static type of that individual expression, so
  a variable initialized from it uses `php::Var` and may participate in PHP
  reference or dynamic-value operations.

For example, `$i = 100` is now permanently an integer local. Reusing `$i` as a
`foreach` key is rejected because PHP keys have type `int|string`; write
`$i = std::any(100)` or select `use varint_types` if this reuse is intentional.
Projects must remove `use native_types` and add the new compatibility mode only
to files that genuinely depend on Zend integer widening semantics.

Fixed `int`, `float`, `bool`, `string`, and `array` locals now use typed native
references. A stable local alias and an exact by-reference parameter on a
statically resolved TypePHP call lower directly to C++ `T&`; this path does not
box the value or allocate a Zend reference. Bindings must be unconditional,
one-time, function-local, and non-escaping. Rebinding, `unset`, reference
capture/return, and storing such a reference into PHP storage are rejected.

Dynamic calls and Closures retain the existing Zend reference path and require
explicit `std::ref()` / `toRef()`. A call-scoped bridge validates the value on
write-back and rejects an escaping temporary reference. Use `std::any()` for
unrestricted PHP reference identity. Fixed object/resource/stream,
high-precision, Native/typed-object, Box, and `std`-container locals remain
non-referenceable because those types already carry identity/handle semantics.
Typed properties and PHP array elements continue to use Zend references and
their normal type-source behavior.

TypePHP is always strict. Project sources no longer need
`declare(strict_types=1)`; the directive remains accepted as a redundant PHP
compatibility declaration, while `strict_types=0` is rejected.

### Breaking: compile-time API namespace cleanup

TypePHP compile-time APIs now occupy only two global class symbols:

- `Type::*` describes types used by extension-method metadata. It does not
  execute conversions or other built-in operations.
- `std::*` contains TypePHP built-in compile-time functions.

As with PHP class and method names, the `std` / `Type` class names and `std`
method names are case-insensitive. `Type::Int` and the other `Type` members are
class constants, so their constant names remain case-sensitive under PHP rules.

The former global compile-time functions have changed as follows:

| Before 0.8 | Since 0.8 | Notes |
| --- | --- | --- |
| `any($value)` | `std::any($value)` | Erases the static type to `mixed/any`. |
| `refval($target)` | `std::ref($target)` | Explicit reference wrapper for call arguments. |
| `expected($condition)` | `std::expected($condition)` | Emits the `EXPECTED(...)` branch hint. |
| `unexpected($condition)` | `std::unexpected($condition)` | Emits the `UNEXPECTED(...)` branch hint. |
| `objval($value, Foo::class)` | `$value->toObject(Foo::class)` | Replaced by the existing keyword method. |

No compatibility functions are installed in the global namespace. Applications
may define and call their own `any()`, `refval()`, `expected()`, `unexpected()`,
and `objval()` functions without being intercepted by the compiler.

`std::ref()` retains the previous reference-wrapper restrictions: its target
must be a variable, array element, or object property, and it is only valid as a
call argument wrapper. `toRef()` remains the equivalent keyword method.

All projects using the old spellings must update their source and rebuild.

### Compatibility policy before 1.0

TypePHP is still pre-1.0 software. Public compiler APIs, TypePHP-specific source
syntax, generated-code interfaces, configuration, and packaging may change
between minor releases. Breaking changes will be documented here, but users
should review the change log and run their full test suite before upgrading.

---

## 0.8.0（中文）

### 破坏性变更：默认使用原生标量存储

`use native_types` 已移除。推断出的 `int`、`float`、`bool` 局部变量现在默认分别
使用 `php::Int`、`php::Float`、`php::Bool`。这些变量的存储类型一旦确定便不会因为
后续操作需要 PHP 动态语义而被编译器自动提升为 `php::Var`。

只保留两个显式出口：

- `use varint_types` 是文件级模式，使推断出的整数使用 `php::Var`，保留 PHP 的
  整数溢出转浮点、整数除法产生非整数结果等 Zend 算术语义；它不会装箱 `float`
  或 `bool`。
- `std::any($value)` 只擦除该表达式的静态类型。以它初始化的变量使用 `php::Var`，
  可参与引用或其他动态值操作。

例如 `$i = 100` 现在固定为整数局部变量。由于 PHP 的 foreach key 类型为
`int|string`，之后复用 `$i` 作为 key 会在编译期报错；确需复用时，应写成
`$i = std::any(100)` 或在文件中声明 `use varint_types`。升级项目必须删除
`use native_types`，并且只为真正依赖 Zend 整数扩展语义的文件添加新兼容模式。

固定 `int`、`float`、`bool`、`string`、`array` 局部变量现在使用强类型原生引用。
稳定的局部别名，以及静态可解析 TypePHP 调用上的精确引用参数，会直接生成 C++ `T&`，
不装箱、不创建 Zend reference。绑定必须位于函数顶层、只发生一次且不得逃逸；重新绑定、
`unset`、引用捕获/返回，或把引用存入 PHP 槽位都会在编译期拒绝。

动态调用与 Closure 继续使用既有 Zend reference 路径，并要求显式使用 `std::ref()` /
`toRef()`。调用级 bridge 在返回时检查类型并拒绝临时引用逃逸；需要完整 PHP 引用身份时
应使用 `std::any()`。固定 object/resource/stream、高精度值、Native/typed object、Box
和 `std` 容器仍然禁止取引用，因为这些类型本身已有 identity/handle 语义。Typed
Property 与 PHP 数组元素继续使用 Zend reference 及其 type source 约束。

TypePHP 始终使用严格类型，项目源码不再需要 `declare(strict_types=1)`。该声明仍作为
冗余的 PHP 兼容语法被接受，而 `strict_types=0` 会被拒绝。

### 破坏性变更：整理编译期 API 命名空间

TypePHP 编译期 API 现在只占用两个全局类符号：

- `Type::*` 仅描述扩展方法元数据等场景使用的类型，不执行类型转换或其他内置操作。
- `std::*` 承载 TypePHP 内置编译期函数。

遵循 PHP 的类名与方法名规则，`std` / `Type` 类名以及 `std` 方法名均不区分
大小写。`Type::Int` 等成员属于类常量，因此其常量名仍按 PHP 规则区分大小写。

原全局编译期函数迁移如下：

| 0.8 之前 | 0.8 起 | 说明 |
| --- | --- | --- |
| `any($value)` | `std::any($value)` | 将静态类型降级为 `mixed/any`。 |
| `refval($target)` | `std::ref($target)` | 调用参数的显式引用包装器。 |
| `expected($condition)` | `std::expected($condition)` | 生成 `EXPECTED(...)` 分支提示。 |
| `unexpected($condition)` | `std::unexpected($condition)` | 生成 `UNEXPECTED(...)` 分支提示。 |
| `objval($value, Foo::class)` | `$value->toObject(Foo::class)` | 改用现有关键词方法。 |

TypePHP 不在全局命名空间安装兼容函数。应用可以自行定义并正常调用
`any()`、`refval()`、`expected()`、`unexpected()` 和 `objval()`，编译器不会拦截。

`std::ref()` 延续原引用包装限制：目标必须是变量、数组元素或对象属性，且只能作为
调用参数包装器使用。`toRef()` 仍是等价的关键词方法。

使用旧写法的项目必须修改源码并重新编译。

### 1.0 之前的兼容性策略

TypePHP 目前仍处于 1.0 之前。公开编译器 API、TypePHP 专有源码接口、生成代码接口、
配置和打包方式都可能在次版本中发生变化。破坏性变更会记录在本文件中；升级前请阅读
变更记录，并运行项目的完整测试。

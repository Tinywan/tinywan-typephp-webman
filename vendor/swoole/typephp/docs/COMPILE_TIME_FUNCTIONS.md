# AOT 编译期函数与关键词方法

本文档记录 AOT 编译器专有的编译期函数、关键词方法和相关构造入口。它们不是标准 PHP 语法的一部分，普通 PHP 运行时只能依赖 `src/polyfills.php` 提供的兼容占位。

## 核心编译期函数

当前核心全局编译期函数共 5 个。

| 名称 | 参数 | 作用 | 当前主要处理位置 |
| --- | --- | --- | --- |
| `any($value)` | 1 个 | 将表达式降级为 `mixed/any`，阻止继续按静态 native/object 类型处理。 | 通用函数调用表达式入口。 |
| `refval($target)` | 1 个 | 显式把变量、数组元素或对象属性作为引用传给动态调用或无法静态识别引用参数的调用。 | 参数解析、动态调用、SSA/优化器引用逃逸分析。 |
| `objval($value, ClassName::class 或 'ClassName')` | 2 个 | 告诉编译器 `$value` 是指定类对象，并生成 `php::toObject(..., target_ce)` 运行时兜底检查。 | 函数调用解析、对象类型推导。 |
| `expected($condition)` | 1 个 | 标记条件通常为真，生成 Zend `EXPECTED(...)` 分支预测宏。 | 通用函数调用表达式入口。 |
| `unexpected($condition)` | 1 个 | 标记条件通常为假，生成 Zend `UNEXPECTED(...)` 分支预测宏。 | 通用函数调用表达式入口。 |

约束：

- `refval()` 只接受变量、数组元素或对象属性。
- `objval()` 第二个参数必须是编译期可解析的类名字符串或 `ClassName::class`。
- `any()` 可在任意表达式位置使用，编译时直接展开其唯一参数，不生成运行时函数调用。
- `expected()` / `unexpected()` 只接受一个非展开参数，返回 bool；通常用于 `if`、`elseif` 和循环条件，不改变参数的求值次数及真假语义。

## 关键词方法

当前内置关键词方法共 12 个。

| 名称 | 等价行为 | 说明 |
| --- | --- | --- |
| `toAny()` | `any($receiver)` | 返回接收者本身，但类型降级为 `mixed/any`。 |
| `toRef()` | `refval($receiver)` | 返回接收者引用；参数限制与 `refval()` 一致。 |
| `toObject()` | `php::toObject($receiver)` | 可带目标类参数，执行对象转换/检查。 |
| `toInt()` | `php::toInt($receiver)` | 转为 native int 表达式。 |
| `toFloat()` | `php::toFloat($receiver)` | 转为 native float 表达式。 |
| `toString()` | `php::toString($receiver)` | 转为字符串表达式。 |
| `toBool()` | `php::toBool($receiver)` | 转为 bool 表达式。 |
| `toArray()` | `php::toArray($receiver)` | 转为数组表达式。 |
| `toStream()` | `php::toStream($receiver)` | 转为 stream 表达式。 |
| `toBigInt()` | `php::BigInt::newInstance($receiver)` | 构造 BigInt。 |
| `toBigFloat()` | `php::BigFloat::newInstance($receiver)` | 构造 BigFloat。 |
| `toDecimal()` | `php::Decimal::newInstance($receiver)` | 构造 Decimal。 |

约束：

- `toAny()`、`toRef()` 不接受参数。
- `toRef()` 只适用于可取引用的接收者。
- 关键词方法优先于普通方法和 universal method 分派。

## `std::` 编译期构造入口

当前 `std::` 编译期构造入口共 10 个。

| 名称 | 作用 | 主要限制 |
| --- | --- | --- |
| `std::int($value)` | 显式创建 native int 表达式。 | 需要 1 个值参数。 |
| `std::float($value)` | 显式创建 native float 表达式。 | 需要 1 个值参数。 |
| `std::bool($value)` | 显式创建 native bool 表达式。 | 需要 1 个值参数。 |
| `std::bigInt($value)` | 构造 BigInt。 | 不允许从 float 变量隐式构造。 |
| `std::decimal($value)` | 构造 Decimal。 | float 变量需改用字符串或整型；float 字面量会按原始字面量处理。 |
| `std::bigFloat($value)` | 构造 BigFloat。 | 需要 1 个值参数。 |
| `std::array($type, $size[, ...$sizes])` | 构造固定大小 std array。 | 只能在变量首次赋值的顶层作用域使用。 |
| `std::vector($type[, $size])` | 构造 std vector。 | 只能在变量首次赋值的顶层作用域使用。 |
| `std::map($keyType, $valueType)` | 构造 std map。 | 只能在变量首次赋值的顶层作用域使用。 |
| `std::ordered_map($keyType, $valueType)` | 构造 std ordered map。 | 只能在变量首次赋值的顶层作用域使用。 |

## Std 容器转换关键词方法

当前 Std 容器转换关键词方法共 4 个。

| 名称 | 作用 | 主要限制 |
| --- | --- | --- |
| `toStdArray(...)` | 将变量包装为 std array。 | 只能在变量首次赋值的顶层作用域使用。 |
| `toStdVector(...)` | 将变量包装为 std vector。 | 只能在变量首次赋值的顶层作用域使用。 |
| `toStdMap(...)` | 将变量包装为 std map。 | 只能在变量首次赋值的顶层作用域使用。 |
| `toStdOrderedMap(...)` | 将变量包装为 std ordered map。 | 只能在变量首次赋值的顶层作用域使用。 |

## 不计入本文清单的机制

- `$array->any()` 是 universal method，映射到 PHP `array_any()`，不是 `any()` 编译期函数。
- `Type::*` 是编译期类型描述常量，不是函数。
- keyword extension method 是用户自定义扩展方法机制，不属于固定内置编译期函数清单。

## 实现约束

编译期函数应当在任意合法表达式位置可用，并且在所有路径上保持一致语义：

- `any()` 已统一在普通函数调用表达式入口处理；赋值、参数、返回值、数组元素和运算子表达式共用相同语义。
- `refval()` / `toRef()` 在参数解析和动态调用路径中特判较多，后续应统一为一个“引用包装表达式”解析入口。
- `objval()` 当前通过函数调用解析和类型推导路径识别，整体较集中。
- `expected()` / `unexpected()` 在普通函数调用入口分别生成 `EXPECTED(...)` / `UNEXPECTED(...)`，不产生 PHP 运行时函数调用。

后续重构目标：

- 建立统一的 `CompileTimeFunctionResolver` 或等价模块。
- 在 `parseExpr()` / `detectTypeOfExpr()` / `detectClassOfExpr()` / 参数解析路径中复用同一份编译期函数元信息。
- 继续统一 `refval()`、`objval()` 在不同表达式路径上的行为。

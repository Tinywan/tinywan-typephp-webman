# TypePHP 三套对象存储与传递模型

> 状态：当前架构约束。本文解释 TypePHP 为什么同时保留 Zend Object、PHPX Box 和
> Native Class Object 三套对象式值模型，以及它们各自的所有权、传递方式和边界。

## 1. 结论

TypePHP 当前存在三套对象存储与传递机制：

1. 普通 PHP/Zend Object；
2. PHPX Box，包括 Std Container 和高精度类型；
3. `#[Native]` Native Class Object。

三者并非同一设计的历史残留，而是分别解决三类互相冲突的问题：

- Zend Object 保留 PHP 的动态对象语义和 ZendVM 生态兼容性；
- Box 为无法完整写进 PHP 类型声明的 C++ 类型提供不透明 Zend value 载体；
- Native Class Object 为静态可知的业务对象提供接近 C/C++ 的固定布局、裸指针调用和
  tracing GC。

任何一种机制都不能在不损失另一种机制核心能力的前提下替代其余两种。当前设计明确接受
三套模型长期共存，不以“统一对象表示”为目标。

## 2. 总览

| 维度 | Zend Object | PHPX Box | Native Class Object |
| --- | --- | --- | --- |
| 典型值 | 普通 PHP class 实例 | Std Container、BigInt、BigFloat、Decimal | `#[Native] class` 实例 |
| 主要表示 | `zend_object` / zval | `zend_resource` + `php::Box *` | Native Heap 中的 C++ struct + 裸指针 |
| 类型身份 | `zend_class_entry *` | Box C++ 动态类型、`type_info`/类型 ID | 编译期 Native class，descriptor 保存动态类型 |
| 生命周期 | Zend 引用计数 + Zend 循环 GC | Zend resource 引用计数调用 Box destructor | Wren 风格精确、非移动 mark-sweep GC |
| 参数传递 | `php::Object` / `php::Var`，复制句柄并调整 RC | `php::Var` 携带 resource；热路径提取具体 C++ 引用 | 具体 `NativeClass *` 按值传递，不调整 RC |
| 属性/方法访问 | Zend handlers、动态查找或已缓存 Native Call | 编译器根据具体 Box 类型生成操作 | 固定偏移字段访问和确定的 `php_*` Native Call |
| 动态 PHP 互操作 | 完整 | 作为不透明 resource 有限互操作 | 不可进入 ZendVM value 边界 |
| 循环图处理 | Zend GC 可扫描 Zend object graph | Zend GC 不扫描 Box 内部 C++ 对象图 | Native descriptor 精确 trace Native pointer graph |
| 核心目标 | PHP 兼容性 | 携带 C++ 泛型/扩展值 | 极致静态性能 |

## 3. 普通 PHP/Zend Object

### 3.1 存储

普通 class 注册到 ZendVM，实例由 `zend_object` 表示。TypePHP 通过 `php::Object`、
`php::Variant`/`php::Var` 等 PHPX RAII 类型持有对应 zval。

对象具有 Zend 的 class entry、属性表、对象 handlers 和方法元数据。根据编译期信息，
TypePHP 可以把部分访问优化为确定的 Native Call，但对象身份和生命周期仍属于 ZendVM。

### 3.2 传递和生命周期

PHP 对象赋值和参数传递复制对象句柄，不复制对象实体，并遵循 Zend 引用计数。对象图中的
循环引用由 Zend GC 处理。对象可以自然进入：

- PHP array 和普通对象属性；
- `mixed`/`object` 变量；
- Closure、Generator、Fiber 和动态调用；
- Reflection、序列化和扩展函数；
- ZendVM 执行的 PHP 代码。

### 3.3 必须保留的原因

只有 Zend Object 能完整承载 PHP 的运行时对象语义。用 Box 替代会丢失 class entry、对象
handlers、可见性、Reflection 和动态分派；用 Native Object 替代则会失去 ZendVM 可见性，
并迫使所有动态行为退化为编译期限制。

普通 PHP class 因此始终使用 Zend Object。编译器可以优化调用，但不能改变其对象模型。

## 4. PHPX Box

### 4.1 存储

`php::Box` 是由 PHPX 管理的 C++ 多态基类。Box 指针注册为 Zend resource，并由
`php::Var` 携带：

```text
zval(IS_RESOURCE)
    -> zend_resource
        -> php::Box*
            -> concrete C++ value
```

Zend resource 的析构回调最终调用 `Box::destroy()`。Box 因而可以经过普通 zval/Variant
调用边界，同时隐藏 Zend 无法表达的具体 C++ 类型。

当前主要使用者包括：

- `StdContainerBox<std::vector<T>>`；
- `StdContainerBox<std::array<T, N>>`；
- `StdContainerBox<map-like type>`；
- BigInt、BigFloat、Decimal 等高精度值。

### 4.2 Std Container 的热路径

Std Container 局部变量具有两层表示：

```cpp
php::Var values = php::Var(new php::StdContainerBox<Container>(type_id));
auto &values_ref = values.toBox<php::StdContainerBox<Container>>()->container;
```

`php::Var` 负责生命周期和必要的边界传递，具体容器引用用于后续元素访问，避免每次操作都
重复提取 Box。容器的 key/value/长度等泛型信息由编译器和具体 C++ 模板类型共同保存。

Std Container 跨 TypePHP 函数传递时，PHP 函数签名无法表达以下 C++ 类型信息：

```text
std::vector<int>
std::vector<string>
std::map<string, App\User>
```

PHP 参数最多只能声明一个非泛型类名或伪类型，不能同时携带容器种类、key 类型、value
类型、数组维度和长度。当前使用 `UnsafePtr`/`std::unsafe_cast()` 加编译器类型 ID 校验，
而不是把所有组合生成为 PHP class。

理论上可以增加参数和返回值注解描述泛型，但这要求每个声明、调用、返回、属性和传播点
都维护额外元数据，PHP Reflection 仍无法完整表达它。当前不引入这套独立泛型 ABI。

### 4.3 Box 的边界

Box 是不透明值载体，不是通用对象系统：

- Zend GC 只看见 resource，不会扫描 Box 内部保存的 C++ 引用；
- Box 不提供 PHP class 的方法表、属性表、继承和 Reflection；
- 通过 `dynamic_cast`、类型 ID 或专用 helper 恢复具体类型；
- 不应使用 Box 构建需要跨 Zend/Box 双向追踪的任意循环对象图；
- Std Container 的可用位置和逃逸路径继续受编译器限制。

Box 适合数值、容器和其他边界明确的扩展值。它不适合代替具有任意字段引用关系的 Native
业务对象。

### 4.4 必须保留的原因

Std Container 的泛型类型无法由 PHP 函数参数完整表达；高精度值又需要作为 `php::Var`
参与现有运算和调用。Box 同时提供：

- 可放进 zval 的稳定载体；
- C++ 具体类型的运行时恢复；
- Zend request 生命周期内的自动析构；
- 不为每一种模板实例注册一套 PHP class 的轻量实现。

Zend Object 无法直接表达 C++ 模板实例；Native 裸指针则无法安全穿过 `php::Var` 和动态
ZendVM 边界。因此 Box 仍有独立存在的必要。

## 5. Native Class Object

### 5.1 存储

`#[Native]` class 不注册 Zend class，不生成 Zend object handlers，也没有 zval 表示。每个
对象是 Native Heap 中的固定布局 C++ struct，TypePHP 局部变量、参数、返回值和字段保存
具体 Native 指针：

```cpp
php_app__point *point;
```

方法继续使用 TypePHP 的自由函数 ABI：

```cpp
php::Float php_app__point__length(php_app__point &this_);
```

普通调用只传递一个指针值。不会创建 zval、注册 resource、执行引用计数或通过
`zend_call_function()`。

### 5.2 生命周期

Native Object 使用 PHPX 中独立的 Wren 风格精确、非移动、stop-the-world mark-sweep GC：

- Native 局部变量、参数、返回临时值和 global/static slot 进入精确 root frame；
- Native 对象 descriptor 负责 trace Native pointer 字段；
- Std Container 保存 Native pointer 时注册专用 container root frame；
- 循环引用由 tracing GC 回收，不依赖引用计数降为零；
- 16-byte GC header 保存收集器所需的最小状态；
- `__destruct()` 由 Native finalization 执行，而不是由 Zend object destructor 执行。

Native 指针赋值不增加引用计数，也不需要 write barrier。固定字段直接按 C++ 偏移访问。

### 5.3 传递边界

Native Object 参数和返回值必须显式声明具体 Native class，或受支持的 nullable 具体类型：

```php
function distance(Point $left, Point $right): float;
function findPoint(): ?Point;
```

这使编译器可以把签名直接生成为 `Point *`。Native Object 不支持：

- 传给 PHP/ZendVM 函数、Closure 或动态 callable；
- 保存到 PHP array、普通 Zend Object 属性或 `mixed`；
- 自动转换为 `php::Object`、`php::Var` 或 Interface value；
- 依靠运行时 class name 恢复类型；
- 使用通用 PHPX `toObject()` helper 完成装箱或拆箱。Native Class 可以声明自己的
  `toObject(): object` 方法；关键词调用会直接解析为该 Native Call，并不提供通用 bridge。

需要进入 PHP API 时，用户必须显式转换数据，例如先调用 Native `toArray(): array`，再把
结果传给 `json_encode()`。该转换产生的是数据副本，不保留 Native 对象身份。

### 5.4 必须保留的原因

Native Class 的目标是接近 C/C++ 的热路径性能：

- 一个机器字的对象句柄；
- 固定字段布局；
- 不进行 Zend RC 增减；
- 不分配 `zend_object` 或 `zend_resource` carrier；
- 确定符号 Native Call；
- 可由 C++ 编译器内联和去虚化。

若改用 Box，每个 Native Object 都需要 resource/zval 封装、RC 管理和具体类型恢复，而且
Zend GC 无法扫描 Box 内部 Native 指针图；这既降低性能，也不能正确替代 Native tracing
GC。若改用自定义 `zend_object`，虽然能够接入 Zend GC 和动态边界，但对象 header、RC、
handlers 和访问路径都会改变 Native Class 的性能定位。

因此 Native Class 继续使用独立 Native Heap 和裸指针 ABI。

## 6. 为什么不能统一

### 6.1 不能全部改为 Zend Object

这样可以统一动态语义，却会让 Std Container 泛型实例和 Native Class 都承担 Zend object
header、RC、handlers、class registration 与动态访问成本。Native Class 将不再接近 C/C++，
Std Container 也需要为大量模板组合设计运行时 class 体系。

### 6.2 不能全部改为 Box

Box 能通过 zval 携带 C++ 值，但 Zend GC 不理解 Box 内部对象图。它不能替代普通 PHP
Object 的动态元数据，也不能在保持 Native 循环回收能力的同时提供裸指针热路径。

### 6.3 不能全部改为 Native pointer

Native pointer 要求完整静态类型。普通 PHP 对象需要 Reflection、动态属性、动态 callable
和 Zend 扩展互操作；Std Container 的完整泛型类型又无法写入 PHP 参数签名。把这些值都
改为裸指针会产生无法静态证明安全的类型擦除，并可能导致错误指针转换和崩溃。

### 6.4 不增加自动桥接

三套模型之间不进行隐式对象身份转换。自动装箱/拆箱会隐藏分配、复制、RC 和 GC root
变化，也会使编译器边界不再可靠。

允许的转换必须具有明确语义：

- Std Container 转 PHP array：复制容器数据；
- Native Object 的 `toArray()` 等实体方法：由用户定义并显式复制数据；
- 高精度类型的显式标量转换：产生新的 PHP 标量值；
- 普通 Zend Object 不会自动变成 Native Object。

## 7. 编译器实现约束

后续修改必须保持以下不变量：

1. 先根据静态类型确定对象模型，再选择代码生成路径；不得在运行时猜测三者之一。
2. Native Object 不得因通用 fallback 被包装成 `php::Var` 或传入 ZendVM。
3. Box 的具体类型恢复必须校验 resource 类型和 concrete C++ 类型/类型 ID。
4. Zend Object 优化不得改变 Zend 对象身份、生命周期或动态可见性。
5. 三种模型的参数 ABI 不得混用：`php::Object`、Box-bearing `php::Var`、`NativeClass *`
   分别代表不同所有权和类型约束。
6. 跨模型转换必须显式，并在文档和生成代码中体现分配或复制成本。
7. 若一个新特性需要牺牲所有 Native Class 热路径来获得少量动态兼容，应优先在编译期禁止。
8. 若一种新的 C++ 泛型类型需要穿过 Zend value 边界，应优先评估 Box，而不是扩大 Native
   Object 的动态边界。
9. 若一个值需要完整 PHP 对象语义，应使用 Zend Object，不能把 Box 当作简化的 PHP class。

## 8. 代码位置

主要实现入口：

```text
普通 Zend Object
  compiler/src/Parser/*
  phpx/include/phpx.h          Object / Variant / Zend API wrappers

PHPX Box 与 Std Container
  phpx/include/phpx.h          Box / StdContainerBox<T>
  phpx/src/core/base.cc        Box resource registration and destructor
  compiler/src/Parser/StdContainerTrait.php

Native Class Object
  compiler/src/NativeClass/
  compiler/src/Transform/NativeClassAttributeLowering.php
  phpx/include/phpx_native_gc.h
  phpx/src/core/native_gc.cc
  phpx/thirdparty/wren-gc/
```

详细规则分别见 [STD_CONTAINERS.md](STD_CONTAINERS.md)、
[NATIVE_CLASS_OBJECT.md](NATIVE_CLASS_OBJECT.md) 和
[NATIVE_CLASS_IMPLEMENTATION_AUDIT.md](NATIVE_CLASS_IMPLEMENTATION_AUDIT.md)。

## 9. 当前决策

当前阶段不实施以下重构：

- 不移除 Wren GC；
- 不把 Native Object 改为 Box 或自定义 Zend Object；
- 不给 Native Object 增加通用 `toObject()` 动态恢复机制；Native Class 自定义的
  `toObject(): object` 仍是普通的确定 Native Call；
- 不把 Std Container 改为无法跨签名表达类型的裸指针 ABI；
- 不尝试用单一统一 wrapper 覆盖三种对象模型。

未来只有在 PHP 语言层能够稳定表达泛型参数、或者有经过 benchmark 和完整 GC 正确性验证
的新 ABI 时，才重新评估这些边界。在此之前，三套机制的共存是有意的架构选择。

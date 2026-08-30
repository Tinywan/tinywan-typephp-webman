# Native Class Object 设计与实现

> 状态：核心方案已实现，正在进行最终整体验证。固定布局、Native Call、精确 tracing GC、
> 构造/克隆/析构、Trait、Getter/Setter、Property Hook、抽象类、单继承、有限虚分派、
> Interface 编译期契约及项目级 global slot 预发现均已落地。逐项实现证据见
> [NATIVE_CLASS_IMPLEMENTATION_AUDIT.md](NATIVE_CLASS_IMPLEMENTATION_AUDIT.md)。
> Native Class 与普通 Zend Object、PHPX Box 并存的架构原因见
> [OBJECT_STORAGE_AND_PASSING_MODELS.md](OBJECT_STORAGE_AND_PASSING_MODELS.md)。

## 1. 背景

TypePHP 的普通 class 会注册到 ZendVM，并生成 `zend_class_entry`、对象处理器、属性元数据和 Zend 方法包装函数。这使普通 class 能够兼容动态 PHP、Reflection、动态调用、序列化等能力，但也带来了固定的运行时成本。

Native Class Object 面向少量要求极致性能的场景。它只允许在静态编译的 TypePHP 代码中使用，不注册到 ZendVM，并直接生成为接近 C++ `struct` 的数据结构。

该能力不是普通 class 的自动优化模式，也不替代现有对象模型。开发者必须显式选择 Native Class，并接受相应的功能限制。

## 2. 设计目标

1. 属性具有固定内存布局，可以直接访问 C++ 字段。
2. 方法继续编译为现有 `php_*` C++ 自由函数，第一个参数是具体 Native struct 的 `this_`。
3. 不生成 `zend_class_entry`、Zend object handlers 和 Zend 方法包装函数。
4. 不执行 `zend_call_function()`，不经过 ZendVM 动态分派。
5. 对象句柄只占一个机器字，不使用 `std::shared_ptr` 和原子引用计数。
6. 普通参数传递不增加引用计数，不复制对象实体。
7. Getter、Setter、Property Hook 等静态特性可以被 C++ 编译器内联。
8. Native Class 编译器实现放在独立目录中，避免将特殊规则散布到现有编译器代码。
9. 不为了兼容少量依赖 ZendVM 的特性牺牲主要路径的性能和可维护性。
10. 所有属性必须显式声明类型，禁止依靠首次赋值推断属性布局。
11. 除 `bool`、`int`、`float` 外，允许属性保存 string、array、object、Stream、mixed 等合法且受支持的 PHP/TypePHP 类型。

## 3. 非目标

Native Class Object 初版不追求以下能力：

- 与动态 PHP 代码互操作。
- 在 `eval()`、动态 `include` 中访问。
- Zend Reflection 元数据。
- 动态属性、变量方法名和动态类名实例化。
- 与普通 ZendVM class 互相继承。
- 自动装箱为 `php::Object` 或 `php::Variant`。
- 完整兼容 PHP 对象的析构时机和垃圾回收行为。
- 在无法静态证明安全时自动降级为 ZendVM Object。

`new (表达式)()` 始终属于 PHP 的动态类名语法。Native Class 分支不会为了
`new (NativeClass::class)()` 等写法额外求值、识别或增加专用诊断；这种动态创建行为
不在 Native Class 的工作范围内。Native Object 只能通过源码中静态可解析的
`new NativeClass(...)` 创建。

## 4. 显式声明

使用专用内置注解：

```php
#[Native]
class Point
{
    public float $x;
    public float $y;

    public function length(): float
    {
        return sqrt($this->x ** 2 + $this->y ** 2);
    }
}
```

Native Class 支持单继承，但只能继承另一个 Native Class。普通 ZendVM class 与 Native Class 之间禁止互相继承。`final class` 和 `final` 方法继续生效，并为编译器提供更强的去虚化条件。

继承链中的每一个 concrete class 都必须显式声明 `#[Native]`，不能仅因父类是 Native Class 就隐式切换对象模型：

```php
#[Native]
class Base {}

#[Native]
class Child extends Base {}
```

`#[Native]` 是 Native Class Object 的正式显式声明方式。未使用该注解的普通 class 继续进入现有 ZendVM Object 编译流程。
该注解只能用于具名 `class`；Interface、Trait 与 Enum 均不能声明为 Native。Trait 仍可由
Native Class 在 convert 阶段注入，但 Trait 自身不形成 Native runtime 类型。

## 5. 生成的 C++ 结构

上述代码近似生成：

```cpp
struct php_app__point {
    php::Float x;
    php::Float y;
};

php::Float php_app__point__length(php_app__point &this_);
```

没有父类、子类和 override method 的 Native Class 不包含：

- 虚函数表。
- Native Object 基类。
- 运行时 class id。
- 对象内引用计数。
- Zend object header。
- 属性名称表和方法名称表。

不参与 override 分派时，Native struct 只包含字段，不生成 C++ 成员函数。PHP 源码中的实例方法继续沿用 TypePHP 当前的函数式 ABI：

```cpp
php::Float php_app__point__length(php_app__point &this_) {
    return php::fn::sqrt(this_.x * this_.x + this_.y * this_.y);
}
```

调用生成：

```cpp
php_app__point__length(*point);
```

调用方在解引用前完成一次 null 检查，`php_*` 方法函数内部可以假设 `this_` 有效。普通实例方法统一使用可写的 `native_struct &this_`，不因方法体是否修改属性而产生不同 ABI。

这个规则带来以下收益：

- 完全复用当前 `php_*` 方法符号命名与 Native Call 机制。
- 参数求值顺序、默认参数、类型检查和异常边界继续走现有函数生成逻辑。
- 不参与继承分派的 Native struct 不包含 vtable 或成员函数声明。
- 所有 Native struct 可以先完成前置声明和字段定义，再统一生成方法函数。
- Getter、Setter、Property Hook 和魔术方法可以统一 lowering 为同类自由函数。

Native 方法不生成 Zend method wrapper，也不注册到 ZendVM。普通 PHP class 与 Native Class 的 `php_*` 符号仍必须进入现有编译符号冲突检测。

### 5.1 继承与虚分派

Native Class 使用 C++ public single inheritance 保持基类子对象布局。PHP 方法的实现主体仍然是 `php_*` 自由函数，不改为复杂的 C++ 成员函数模型。

Native abstract class 及 abstract method 也完全在编译期实现。抽象方法在 C++ struct 中
生成 pure virtual thunk，具体 Native 子类的实现继续调用对应 `php_*` 自由函数；通过抽象
基类 typed parameter 调用时只发生一次 C++ 虚分派，不注册 Zend class 或 method。

全程序分析发现继承链中存在同名的 public/protected instance method 时，为每一个声明层级
生成独立的内部 virtual slot。子类实现同时覆盖祖先 slot，并以各 slot 自己的参数和返回
签名生成 adapter，再转调子类的 `php_*` 实现。这样可以保留 PHP 允许的参数逆变、返回值
协变，而不会把不兼容的 C++ 函数指针或引用强制转换到同一个 vtable slot：

```cpp
struct php_app__base;
php::Str php_app__base__name(php_app__base &this_);

struct php_app__base {
    virtual php::Str __native_dispatch_base_name() {
        return php_app__base__name(*this);
    }

    ~php_app__base() noexcept = default;
};

struct php_app__child;
php::Str php_app__child__name(php_app__child &this_);

struct php_app__child : public php_app__base {
    php::Str __native_dispatch_base_name() override {
        return php_app__child__name(*this);
    }
};
```

调用规则：

- receiver 的静态类型确定为最终实现类时，直接调用对应 `php_*` 函数。
- receiver 是可能指向子类的基类指针，且方法族存在 override 时，通过内部 virtual thunk 分派。
- 未被 override 的方法继续直接调用 `php_*` 函数，不引入虚调用。
- private、static、constructor 和 destructor 不加入 virtual method family。
- PHP 不支持按参数签名重载；Native Class 同样不增加 C++ overload 语义。
- override 必须通过现有 PHP 方法兼容性规则和 Interface 检查。
- 普通值参数通过 per-declaration adapter 支持 PHP 的参数逆变与返回协变。Native Object
  参数禁止 `&`；typed pointer 按值传递已经共享对象身份，而 `&` 还会暴露调用方指针槽的
  重绑定能力，这不属于 Native Object ABI。
- C++ 默认参数由 receiver 的静态类型绑定，不能直接放在 virtual 声明上。编译器为每个
  可用的位置参数数量生成一个重载 virtual slot；动态选中的 adapter 再调用自身 `php_*`
  实现，因此使用动态实现类的默认值，不需要运行时 presence mask。
- Native virtual method 的命名参数调用可以省略尾部连续的 optional 参数；若在后续实参
  之前留下命名参数空洞则编译期拒绝。该少见形状无法由位置型 C++ 重载表达，支持它会使
  每次虚调用携带 presence mask。非虚 Native Call 仍保留普通命名参数行为。

这会提供继承所必需的有限单分派多态，但不支持变量方法名、运行时 overload resolution、`__call()` 或 ZendVM 动态调用。C++ 编译器仍可对 `final` class、`final` method 和已知精确类型完成去虚化。

GC header 的 `NativeTypeDescriptor` 始终记录最派生的动态类型。即使对象通过基类指针存活，trace、finalize 和 destroy 也必须使用动态 descriptor，不能仅依据变量的静态类型。

### 5.2 继承对象布局

继承层次必须满足以下布局规则：

- 父类字段位于 C++ base subobject 中，子类只追加自身新增字段。
- public/protected 同名属性必须通过现有 PHP 属性兼容性检查；表示同一个继承属性时复用父类 slot，不得在子类重复存储。
- 父类 private 属性与子类同名属性是两个不同 slot。生成字段名必须包含 declaring class 或稳定 slot id，避免 C++ 名称隐藏造成误访问。
- 访问继承属性时，代码生成器依据 property definition 的 declaring class 计算固定字段路径，不进行名称查找。
- 最派生类型的 `trace()` 必须覆盖自身和所有 base subobject 中的 Native pointer field。
- constructor 不参与虚分派。与 PHP 一样，子类是否调用 `parent::__construct()` 由源码显式决定，并直接生成确定的 `php_*` 调用。
- `final` class 禁止被继承，`final` method 禁止被 override。

继承图必须在生成 struct 前完成拓扑排序。Native Class 只支持单一 class parent；多个 Interface 不参与对象布局。

## 6. 属性类型与存储

Native Class 的每一个实体属性都必须具有显式类型：

```php
#[Native]
final class RequestContext
{
    public bool $ready = false;
    public int $status = 0;
    public float $elapsed = 0.0;
    public string $method = '';
    public array $headers = [];
    public object $request;
    public Stream $body;
    public mixed $metadata = null;

    public function __construct(object $request, Stream $body)
    {
        $this->request = $request;
        $this->body = $body;
    }
}
```

禁止未声明类型的属性：

```php
#[Native]
final class InvalidContext
{
    public $value; // FatalError
}
```

类型声明用于在编译期确定 C++ 字段布局。不同类型建议映射如下：

| TypePHP 属性类型 | C++ 字段表示 | 说明 |
|---|---|---|
| `bool` | `php::Bool` | 原生值字段 |
| `int` | `php::Int` | 原生值字段 |
| `float` | `php::Float` | 原生值字段 |
| `string` | `php::Str` | TypePHP 当前使用的 PHPX RAII 字符串类型 |
| `array` | `php::Array` | PHPX RAII 数组，保留 PHP COW 语义 |
| 确定的 Zend class | `php::Object` | 保存 Zend Object，并在赋值入口验证 class |
| `object` | `php::Object` | 保存任意 Zend Object |
| Native Class | `native_struct *` | 保存同一 Native Heap 内的裸指针 |
| `Stream` | `php::Var` | 保存 stream resource zval，并在赋值入口执行精确类型检查 |
| `mixed` / `any` | `php::Var` | 保存任意 PHP zval；只有 `any` 明确允许暴露引用 |
| 不含 Native Class 的 union/intersection/nullable | `php::Var` | 与普通类属性使用同一类型描述和运行时写入检查 |
| `?NativeClass` | `native_struct *` | `nullptr` 表示空值；包含 Native Class 的 union/intersection 不支持 |
| BigInt/BigFloat/Decimal | `php::Var` | 保存 PHPX boxed 高精度值；字段寻址仍是固定偏移，运算复用现有 Variant ABI |

`string`、`array`、Zend Object、Stream 和 mixed 字段仍然直接位于 C++ `struct` 的固定偏移处。它们持有的底层 zval 或 zend 对象由 PHPX RAII 类型管理，但属性读取不需要属性哈希表、object handler 或 ZendVM 分派。

PHP 本身不允许将 `resource` 写成属性类型；TypePHP 中的 stream resource 应使用已有的 `Stream` 伪类型声明。`void`、`never`、`callable` 等 PHP 本身禁止用于属性声明的类型，在 Native Class 中同样禁止。

以下类型明确禁止作为 Native Class 属性类型：

- `Box`
- `std\array`
- `std\vector`
- `std\map`
- `std\ordered_map`
- 后续增加的其他 Std Container 类型

这些类型具有独立的泛型布局、引用或所有权语义，将它们嵌入 Native Class 会显著扩大首版类型组合和生命周期分析范围。开发者可以使用普通 PHP `array` 字段；PHP array 中仍然不能保存 Native Object，因为 Native Object 没有 `zval` 表示。

例如：

```cpp
struct php_app__requestcontext final {
    php::Bool ready;
    php::Int status;
    php::Float elapsed;
    php::Str method;
    php::Array headers;
    php::Object request;
    php::Var body;
    php::Var metadata;
};
```

允许字段持有 ZendVM 值不代表 Native Class Object 本身进入 ZendVM。ZendVM 可以管理字段指向的 String、Array、Object 或 resource，但它不知道外层 Native Class 的存在。

### 6.1 属性引用

Native 属性是否允许取引用必须完全由声明元数据在编译期决定，不生成运行时类型分支：

- 只有 `any` 属性允许 `$ref =& $object->property`；这是显式选择允许 Zend 动态代码替换槽值。
- `mixed` 虽然也使用 `php::Var` 存储，但仍拒绝取引用；Native Class 中除 `any` 外的所有声明类型都必须维持编译期类型约束。
- `bool`、`int`、`float` 等固定布局字段不能表示 PHP 引用，编译期拒绝。
- `string`、`array`、`object`、Stream 和高精度类型虽然具有 PHPX 包装层，但仍是固定声明类型，引用写入会绕过类型约束，因此编译期拒绝。
- nullable、union、intersection 等受约束的 `php::Var` 字段同样拒绝引用；不能仅因底层存储也是 `php::Var` 就允许。
- 带 Property Hook 的属性没有可暴露的实体槽，始终拒绝引用。

这项规则只允许引用无约束字段值，不允许引用 Native Object 指针变量本身。Native Object 变量之间的普通赋值已经共享对象身份。

### 6.2 初始化状态

Native Class 不保存 PHP typed property 的 `UNDEF` 状态，也不为字段增加额外状态位。对象创建时，每个没有显式默认值的字段直接使用类型零值：

- `bool` 为 `false`；`int`、`float` 为 `0`。
- `string` 为空字符串，`array` 为空数组。
- `mixed`/`php::Var` 为 `null`。
- Zend Object 和 Native Class 指针均为 `null`。
- `Stream` 为空 resource 状态。
- 属性具有显式默认值时，在零值构造之后应用声明默认值。
- 初版禁止对 Native Class 属性使用 `unset()`，避免重新引入运行时 UNDEF 状态。

因此没有默认值的属性也可以立即读取，但读取到的是上述确定零值，而不是 PHP 的“未初始化 typed property”异常。所有属性仍必须声明类型。

Property Hook 的虚拟属性没有实体字段，但 Hook 声明仍必须包含类型。

### 6.3 赋值检查

已确定的赋值在编译期检查。来自 `mixed`、动态 PHP 返回值或其他无法静态确定的值，在写入字段前执行一次运行时类型检查。检查完成后直接写入对应字段，不经过 Zend property handler。

这意味着支持任意 PHP 字段类型不会改变 Native Class 的属性寻址性能；额外成本只出现在无法静态证明类型安全的赋值边界。

## 7. 对象变量与身份语义

为了保留 PHP 对象的身份和别名语义，TypePHP 变量保存原始对象指针：

```cpp
php_app__point *point;
```

赋值只复制指针：

```php
$a = new Point();
$b = $a;
$b->x = 10;
```

生成语义近似：

```cpp
auto *a = native_heap.make<php_app__point>();
auto *b = a;
b->x = 10;
```

因此 `$a` 和 `$b` 仍指向同一个对象，不会因为采用 C++ `struct` 而变成值复制。

Native Class Object 不使用 `std::shared_ptr`。`std::shared_ptr` 的控制块、原子引用计数和循环引用问题与本特性的极致性能目标不符。

Native Object 的严格比较使用指针身份：`===`/`!==` 判断两个槽是否指向同一个 Native
对象，也支持与 `null` 比较。与任何 Zend 标量或 Zend Object 的严格比较恒为
`false`，不会把裸指针隐式转换为 `bool`。`match` 条件同样使用这一指针身份规则。
PHP 的 `==`/`!=` 会递归比较 Zend Object 属性；Native
Object 没有 Zend object handler，而且对象图可能包含环，因此不提供隐式字段值比较。
松散比较、大小比较和算术/位运算在编译期直接报错。需要值相等语义时应声明一个具有
明确字段和循环处理规则的普通 Native 方法。

一元算术/位运算、`++`/`--`、复合算术赋值和 `switch` 也依赖 PHP 的数值或松散比较
语义，因此禁止用于 Native Object。该检查必须发生在 C++ 生成前，避免裸指针意外进入
合法但危险的 C++ 指针算术。

`isset($native)` 与 `empty($native)` 直接检查裸指针是否为 `nullptr`。命名 Native
属性链使用短路 lambda 逐级检查中间指针，不会把指针传入 `php::Variant`，因此
`isset($node->next->next)` 在中间槽为空时返回 `false`，而不是触发空对象调用。

### 7.1 Native Class 循环引用

两个或多个 Native Class 可以在属性类型上相互引用：

```php
#[Native]
final class A
{
    public ?B $b = null;
}

#[Native]
final class B
{
    public A $a;

    public function __construct(A $a)
    {
        $this->a = $a;
    }
}
```

生成 C++ 时必须先前置声明所有 Native struct：

```cpp
struct php_a;
struct php_b;

struct php_a final {
    php_b *b;
};

struct php_b final {
    php_a *a;
};
```

Native Class 属性始终保存指针，不按值嵌入另一个 Native struct，因此不会产生无限递归的对象尺寸，也不要求按依赖顺序完整定义 struct。

编译器应对 Native Class 类型依赖图计算强连通分量（SCC）：

- SCC 只用于安排前置声明、完整定义和方法实现的生成顺序。
- 循环类型依赖本身不是错误。
- 禁止把 Native Class 属性生成为 by-value struct 字段。
- 所有 Native struct 完整定义完成后，再生成依赖完整类型的方法体。

Native Heap tracing GC 能够遍历所有 Native 指针字段，因此 A 与 B 相互指向不会形成引用计数循环，也不会产生永久泄漏。裸指针字段本身没有析构动作。

Native Object 属性的零值是 `nullptr`，包括源码中声明为 non-nullable 的 Native Class
属性。non-nullable 约束只作用于后续显式赋值，不引入 PHP typed property 的 UNDEF
状态。因此循环对象图可以先分别构造，再建立双向关系：

```php
$a = new A();
$b = new B($a);
$a->b = $b;
```

不需要构造依赖 SCC 或“两阶段发布”机制；类型 SCC 仅用于 C++ 前置声明与生成顺序。

## 8. 内存与生命周期

Request Arena 只能作为分配器和 Request Shutdown 兜底，不能作为唯一生命周期机制。对于常驻 CLI、HTTP Server 或单个超长 request，如果对象只能在 Request Shutdown 释放，内存会持续增长。

Native Class Object 应使用独立的、非移动、精确 tracing GC。本文将该运行时称为 Native Heap。

### 8.1 Native Heap

Wren 派生实现为每个对象分配一块连续内存，并在 struct 前放置隐藏 GC header。Header 固定为两个机器字，在 64 位平台上是 16 字节：

```cpp
struct NativeGcHeader {
    // 低 3 位复用为 marked/finalized/allocated-during-collection 标志。
    uintptr_t nextAndFlags;
    const NativeTypeDescriptor *type;
};

// 内存布局：[NativeGcHeader][php_app__point]
auto *point = native_heap.make<php_app__point>();
```

GC header 不属于生成的 C++ struct，也不会改变属性偏移。用户可见对象变量仍然只是一个 `native_struct *`。对象尺寸、对齐方式及 trace/finalize/destroy 回调保存在每个类型唯一的静态 `NativeTypeDescriptor` 中，不在每个实例中重复保存。

当前分配器使用独立 non-moving allocation；Arena/chunk/free-list 可以作为后续分配器
优化，但不得改变对象地址稳定性、header 布局、精确 tracing 或 finalization 语义。

Native Heap 具有以下特征：

- non-moving：对象地址从创建到回收始终不变。
- precise：只扫描编译器明确登记的 Native 指针，不保守扫描任意内存。
- stop-the-world：初版只在当前 TypePHP request/thread 的安全点执行完整回收。
- non-atomic：Native Object 不跨线程，GC 元数据不使用原子操作。
- 无 per-assignment retain/release：普通指针赋值不修改引用计数。

Request Shutdown 会销毁 Native Heap 中的全部剩余对象，但正常运行期间也会周期性回收不可达对象。

### 8.2 类型描述与对象图遍历

每个 Native struct 生成静态类型描述：

```cpp
struct NativeTypeDescriptor {
    void (*trace)(void *object, NativeMarkVisitor &visitor);
    void (*destroy)(void *object);
    size_t size;
    size_t alignment;
};
```

`trace()` 只访问 Native Class 指针字段：

```cpp
static void trace_a(void *ptr, NativeMarkVisitor &visitor) {
    auto *object = static_cast<php_a *>(ptr);
    visitor.mark(object->b);
}
```

`php::Str`、`php::Array`、`php::Object`、`php::Var` 和 Stream 字段由 Zend 引用计数管理，但它们不能反向保存 Native Object，因此无需由 Native GC 深入扫描。

禁止 Native Object 进入 PHP Array、Box 和 Zend Object，是保证 Native 对象图封闭且可精确遍历的重要条件。局部 Std Container 是例外：当元素类型明确写成 Native Class 时，容器直接保存 typed Native pointer，并由独立的容器 Root Frame 在 GC 标记阶段遍历其当前元素。该 Root Frame 跟踪容器而不是元素地址，因此 vector/map 扩容搬迁不会产生悬空 root。

### 8.3 Root 管理

GC 必须知道当前仍被 TypePHP 代码引用的 Native Object。编译器为可能跨越 GC safe point 存活的局部变量生成轻量 shadow root frame：

```cpp
struct FunctionNativeRoots {
    NativeRootFrame frame;
    php_a *a;
    php_b *b;
};
```

函数入口将 frame 链接到当前 Native Heap，退出时通过 C++ RAII 自动解除链接。C++ 异常展开时也必须正确移除 frame。

为降低开销：

- 不包含 Native Object 的函数不创建 root frame。
- 只借用调用者对象且不会触发 Native 分配的叶子方法不创建 root frame。
- 只登记可能跨越 Native allocation 或显式 GC safe point 存活的变量。
- 普通方法 receiver 由调用者的 root 或对象图保持存活，不重复登记。
- 临时对象如果跨越一次可能触发 GC 的调用，必须先写入 root slot。
- Native Class 允许保存在 TypePHP global 和 static local 中。这些槽不注册到 Zend symbol table，而是生成独立 Native 指针槽。
- `global $slot` 与 `$GLOBALS['slot']` 使用同一个 Native 指针槽；`$GLOBALS` 的键也可以是编译期可求值为字符串的全局常量、类常量或常量表达式。
- `$GLOBALS[$dynamicKey]` 仍按 PHP 语法走 Zend HashTable。由于 Native Object 没有 zval 表示，动态键不能用于读写 Native global。
- ZTS 构建中的 global/static 指针槽和 static 初始化状态均使用 `THREAD_LOCAL`，不同线程之间不共享 Native 对象。
- RINIT 将这些槽登记为 request root；RSHUTDOWN 清空槽和初始化状态，随后由 Native Heap 统一 finalization 和回收。

这比每次对象赋值执行引用计数更适合大量属性写入和循环计算。

### 8.4 GC 触发点

初版只在确定的 safe point 执行 GC：

- Native Heap 分配量超过自适应阈值。
- Request Shutdown 强制清理全部对象。

Native GC 不暴露语言级显式收集函数。PHPX 内部的收集入口只供运行时阈值策略和底层测试使用，不注册为 TypePHP/PHP API。

普通字段读取、字段写入和方法调用本身不触发 GC。GC 不应异步运行，也不在任意 C++ 指令之间发生。

初版采用完整 mark-sweep：

1. 从 shadow root frame、global/static root 开始标记。
2. 通过每个类型的 `trace()` 遍历 Native 指针字段。
3. 使用显式 worklist，避免递归遍历造成 C++ 栈溢出。
4. 扫描 Native Heap，回收未标记对象。
5. 保留存活对象地址，清除 mark 状态。
6. 根据本次存活比例调整下一次 GC 阈值。

A/B 相互引用但无法从任何 root 到达时，两者都会在同一次 sweep 中回收。

### 8.5 析构与 GC 重入

不可达对象可能包含 `php::Array`、`php::Object` 或 `php::Var`。销毁这些字段时，Zend Object destructor 可能执行用户代码，甚至再次分配 Native Object。因此 sweep 不能一边修改 GC 链表一边直接执行全部 C++ 析构。

初版必须采用 finalize/destroy 分离的回收流程：

1. 标记并从活动对象集合中摘除全部不可达对象，将其状态设为 `finalizing`。
2. 完成 GC 内部数据结构更新后，在 GC 临界区外调用用户 `__destruct()` finalizer。
3. finalizing 阶段产生的新 Native Object 加入新的活动列表。
4. finalizing 期间禁止递归进入 GC；新的收集请求记录为 pending，在本轮完成后执行。
5. Native Object 本身不能进入 ZendVM，但用户 `__destruct()` 可以把 `$this` 保存到另一个 Native root，使对象在 finalization 期间复活；对象的 finalized 状态保证用户析构最多执行一次。
6. finalizer 完成后重新扫描 roots；用户 `__destruct()` 与实际 C++ 字段析构分离，只有未复活对象才执行 C++ destroy 和存储释放。

### 8.6 后续栈分配优化

当逃逸分析能够证明对象不会离开当前函数时，可以直接栈分配：

```cpp
php_app__point point_storage;
auto *point = &point_storage;
```

栈分配属于后续优化，不应成为首版正确性依赖。返回值、写入另一个 Native Object 属性或传给未知函数的对象均视为逃逸。

栈上 Native Object 本身不加入 Native Heap，但如果包含 Native 指针字段，GC root descriptor 必须能够遍历该栈对象的对外引用。

### 8.7 Request Shutdown

Request Shutdown 是最终兜底，而不是常规对象回收时机。它必须在 PHP 内存池销毁前停止 GC、摘除 root frame，并销毁 Native Heap 中的全部剩余对象。

不能直接等待 PHP 内存池统一释放，否则 `php::Str`、`php::Array`、`php::Object`、`php::Var` 等字段持有的资源无法正确析构。

### 8.8 开源 GC 实现参考

Native Heap 不应从零发明未经验证的 GC 模型，但也不适合直接嵌入完整语言 VM。应复用成熟算法和测试方法，并针对 TypePHP 的封闭 Native 对象图实现小型专用 GC。

| 项目/算法 | 特点 | 对 TypePHP 的适用性 |
|---|---|---|
| Wren GC | 小型、non-moving、精确 mark-sweep、显式 gray worklist、自适应 heap threshold | 最适合作为首版代码上游；无对象赋值 barrier，容易验证和移植 |
| BDWGC | 历史悠久的 C/C++ conservative collector，默认 STW，也支持部分平台的 incremental/parallel 能力 | 成熟度和接入便利性最高，但不能保证回收所有不可达对象 |
| Oilpan/cppgc | Chrome/Blink 使用的 C++ tracing GC，精确扫描 heap、保守扫描 native stack，支持并发/增量处理 | 大型 C++ 项目成熟，但要求 `GarbageCollected<T>`、`Member<T>`、Trace 和 write barrier，集成过重 |
| MMTk | Rust GC framework，具有 MarkSweep、Immix、generational 等多种 plan 和多语言 VM binding | 性能上限高，但需要实现完整 VM binding、root scanning、object model、barrier 和 safepoint |
| mruby GC | 三色增量 mark-sweep，可选 generational，具有 root arena 和 write barrier | 适合作为第二阶段增量 GC 参考；实现和状态机更复杂 |
| Lua 5.4 GC | 成熟的 incremental/generational collector，可调 pause、step multiplier 和 step size | 长时运行经验丰富，但与 Lua VM 深度耦合，不适合直接集成 |
| PHP/CPython 风格 RC + cycle collector | 对象不可达时通常立即释放，循环由附加 collector 处理 | 高频传参、赋值和字段写入都会产生 INCREF/DECREF，不符合主要性能目标 |

官方参考：

- Wren VM GC：<https://github.com/wren-lang/wren/blob/main/src/vm/wren_vm.c>
- BDWGC：<https://github.com/bdwgc/bdwgc>
- Oilpan standalone library：<https://v8.dev/blog/oilpan-library>
- Oilpan C++ GC 设计：<https://v8.dev/blog/high-performance-cpp-gc>
- MMTk plans/bindings 状态：<https://www.mmtk.io/status>
- MMTk VM porting guide：<https://docs.mmtk.io/portingguide/>

#### 8.8.1 性能比较

TypePHP 的主要热路径是 Native Object 指针传参、变量赋值和 Native 指针属性写入，而不是 GC 本身。候选方案必须优先避免让每次赋值承担额外成本。

| 方案 | 指针赋值热路径 | 分配与回收 | 暂停特征 |
|---|---|---|---|
| Wren 派生 STW mark-sweep | 裸指针写入，无 RC、无 barrier | 简单 free-list/page allocator，完整 heap mark/sweep | heap 很大时 full mark 暂停较长 |
| BDWGC 默认模式 | 普通裸指针写入，无显式 barrier | 高度优化且成熟；扫描 stack、register、globals 和 GC heap | 默认 STW；部分平台可 incremental/parallel |
| Oilpan/cppgc | `Member<T>` 写入；增量/并发 marking 需要 barrier fast path | page heap、并发/增量 marking/sweeping 成熟 | 低暂停能力最好，但 mutator 热路径更复杂 |
| MMTk MarkSweep | 可选择 NoBarrier 的 non-moving MarkSweep | allocator/metadata/parallel worker 基础设施强 | 取决于 binding 和 plan；首版 binding 本身成本很高 |
| MMTk Immix/Generational | 需要 barrier、object logging 或 remembered set；部分 plan 可能移动对象 | 吞吐和空间利用潜力最高 | 可实现更低暂停，但破坏裸指针稳定性的风险更高 |

对于“对象互相传递、赋值、引用非常多”的 TypePHP 程序，Wren 派生 STW 和 BDWGC 默认模式的 mutator 热路径最有优势。Oilpan 和 MMTk 高级 plan 的优势主要体现在大 heap 的暂停和吞吐，而代价会进入每次指针写入或整体 runtime integration。

#### 8.8.2 精确性与长期内存稳定性

BDWGC 是 conservative collector。它把 stack/register/global 中看起来像 GC heap 地址的机器字当作潜在指针。其官方文档明确说明，它不保证回收所有不可访问存储。误识别通常只是延迟回收，但在长期运行程序中，内存上界会依赖 stack 内容、地址布局和编译器行为。

BDWGC 可以使用 typed allocation descriptor 减少 heap 内部的误扫描，但 native stack 仍然是 conservative root。TypePHP 已经能够在编译期准确知道 Native pointer local 和 Native pointer field，因此放弃这些信息改用 conservative scanning 并不理想。

Oilpan 同样是 heap precise、native stack conservative。它在 Chrome/Blink 中可靠，但仍可能因 native stack 上的伪指针延迟回收。Oilpan 的使用场景可以利用 event-loop task 边界选择更干净的 stack 状态；常驻 TypePHP CLI 不一定具备相同条件。

Wren 派生 GC 与 MMTk 都可以使用 TypePHP 生成的 shadow root frame 做到完全 precise。只要 root frame 和 `trace()` 生成正确，不存在伪指针导致的对象滞留。

#### 8.8.3 对象布局兼容性

TypePHP 已确定 Native Object 变量是裸指针，Native struct 只包含 public 字段，方法是 `php_*` 自由函数。

- Wren 派生 GC 可以把 GC header 放在 struct 之前，并通过 `NativeTypeDescriptor` 扫描裸指针字段，完全匹配该布局。
- BDWGC 允许直接返回裸指针，对布局侵入最少，但无法自然复用 TypePHP 的精确 root 信息。
- Oilpan 要求 GC object 使用 `GarbageCollected<T>`，heap pointer 使用 `Member<T>`，并提供 `Trace()`；这会改变已经确定的 struct 和字段设计。
- MMTk 不强制 C++ 基类，但 binding 必须定义 object reference、header/side metadata、copy/pin、root slot 和 object scanning。若使用 moving plan，所有裸指针还必须可更新或永久 pin。

#### 8.8.4 C++ 析构和 Zend 重入

Native Object 可以包含 `php::Str`、`php::Array`、`php::Object` 和 `php::Var`，回收时必须执行 C++ 析构；Zend Object destructor 还可能执行 PHP 用户代码。

- Wren 派生库可以按 TypePHP 需要实现“摘除不可达对象，再在 GC 临界区外析构”的两阶段流程。
- BDWGC 提供 finalizer，但 finalizer 的执行顺序和重新可达语义需要额外适配；它无法直接理解 ZendVM 的异常和 request 生命周期。
- Oilpan 会对具有非平凡析构的对象执行 finalization，但官方约束 finalizer 不应访问其他 on-heap object；复杂场景需要 pre-finalizer，并依赖其运行时规则。
- MMTk 把 finalizer/weak reference 语义留给 VM binding，实现责任仍然落到 TypePHP。

因此四种方案都不能直接解决 Zend 重入；Wren 派生方案虽然需要自行实现，但可以只实现 TypePHP 实际需要的严格语义。

#### 8.8.5 成熟度与集成风险

| 方案 | 上游成熟度 | TypePHP 新增代码风险 | 构建与分发 |
|---|---|---|---|
| Wren 派生 GC | Wren 算法经过长期使用；提取后的派生库需要 TypePHP 自己验证 | 中等，核心小但 root/finalization adapter 必须充分测试 | 小型 C 静态库，容易支持 GCC/Clang/MSVC/WASI |
| BDWGC | 最高，拥有长期 C/C++ 使用历史和多平台代码 | 低到中等，主要风险是 conservative retention 和 Zend finalizer adapter | CMake/静态库成熟，最接近 GMP 类依赖 |
| Oilpan/cppgc | Chrome/Blink 内成熟度很高 | 高，API、对象布局、platform/task integration 都与当前设计冲突 | 源于 V8 工程，GN/platform 依赖和版本升级成本大 |
| MMTk | GC framework 和多个 VM binding 活跃 | 很高，新 TypePHP binding 本身就是大型 runtime 项目 | 增加 Rust/Cargo、C ABI、worker/safepoint 和跨平台构建链 |

Oilpan 和 MMTk 的“上游成熟”不能直接等价为“TypePHP 集成可靠”。真正决定可靠性的会是新建的 adapter/binding，而这两种方案要求的 binding 面远大于 Wren 派生库或 BDWGC。

#### 8.8.6 跨平台与 WASM

TypePHP 需要同时考虑 Linux、Windows、macOS 和 wasm32-wasip2：

- Wren 派生 GC 只依赖显式 root frame 和普通线性内存，最容易跨平台。
- BDWGC 的 native stack/register/dynamic-library 扫描包含平台相关实现。原生桌面平台成熟，但 WASI 需要单独验证；WebAssembly 通常无法像原生程序一样任意检查 VM stack，传统移植往往需要 shadow stack。
- Oilpan/cppgc 依赖 V8 platform/task 基础设施，不适合作为 WASI 静态库依赖。
- MMTk 当前没有 TypePHP/WASI binding；Rust target 可用不代表 GC plan、线程、内存映射和 root scanning 已可用。

#### 8.8.7 最终选择

综合结论：首版继续选择 Wren 派生的精确、非移动、stop-the-world mark-sweep。

选择依据按优先级排列：

1. Native 指针赋值和传参保持真正的裸指针零附加操作。
2. 使用 TypePHP 已知的精确 root/field 信息，避免 conservative retention。
3. 保持对象地址稳定，不引入 handle、pin 或 pointer update。
4. 可以完全控制 C++ 字段析构、Zend 重入和 request shutdown 顺序。
5. C 静态库小，适合现有 CMake、三大桌面平台和 WASI 工具链。
6. GC 功能只影响 `#[Native]` 分支，不把大型 runtime framework 带入普通 TypePHP 程序。

BDWGC 保留为备选验证基线。实现阶段可以用相同 benchmark 对比 Wren 派生 GC 与 BDWGC typed allocation；如果 Wren 派生实现未能通过可靠性、长时运行或性能门槛，可以退回 BDWGC，而不是直接跳到 Oilpan/MMTk。

Oilpan 不采用，主要原因是对象布局、`Member<T>` write barrier、保守 stack 和 V8 platform/build 依赖与当前设计冲突。MMTk 暂不采用，主要原因是 VM binding 和 Rust runtime 集成规模远超首版需求；当 Native Heap 达到数 GB、full mark pause 成为实际瓶颈且项目能够承担专门 GC 团队时，可以重新评估 MMTk MarkSweep/Immix。

在编码前应定义稳定的 GC adapter API，但首版只实现和交付 Wren backend：

```cpp
namespace php::native_gc {

void *allocate(
    size_t size,
    size_t alignment,
    const NativeTypeDescriptor *type
);
void addRoot(NativeRootFrame *frame);
void removeRoot(NativeRootFrame *frame);
void collect();
void shutdown();

} // namespace php::native_gc
```

这里不使用运行时函数表、虚函数或 backend 对象。生成代码只调用固定符号，最终静态链接到 Wren adapter，分配入口可以被 LTO 内联。基准测试若要替换为 BDWGC，可在单独构建目标中链接实现相同 API 的 adapter；正式产物不承担多 backend 的运行时抽象成本。

Wren GC 确定为 Native Heap 的首版算法和代码上游。Wren 使用 MIT License，但它的 GC 实现目前与 Wren VM 对象模型耦合，并不是可以直接链接的独立 GC library。因此 TypePHP 应从固定的 Wren upstream commit 提取最小 collector 子集，并维护为独立第三方派生库，而不是将完整 Wren VM 链入程序。

建议目录：

```text
phpx/thirdparty/wren-gc/
├── include/
│   └── wren_gc.h
├── src/
│   └── wren_gc.c
├── LICENSE
├── UPSTREAM.md
└── CHANGES.md
```

第三方库要求：

- `LICENSE` 保留完整 Wren MIT License 和原始版权声明。
- `UPSTREAM.md` 记录 Wren 仓库 URL、提取文件和固定 commit hash。
- `CHANGES.md` 记录从 Wren Object/VM 模型适配到 TypePHP `NativeTypeDescriptor`/root frame 的修改。
- 上游代码与 TypePHP adapter 分离，避免把编译器逻辑继续写入第三方文件。
- 生成独立静态库，例如 `libwren_gc.a`，构建和链接方式与 GMP、MPFR、libmpdecimal 等第三方依赖保持一致。
- 未使用 `#[Native]` 的程序不需要初始化 Native Heap；是否仍统一链接静态库由最终构建方案决定。
- 不导入 Wren parser、bytecode VM、对象系统、标准库或其他无关模块。

PHPX 的 Native GC adapter 负责类型 descriptor、root frame、C++ 析构回调、Zend 重入保护及面向生成代码的稳定 C++ API。TypePHP 编译器只负责生成 descriptor、trace/finalize/destroy 函数、root frame 操作和调用代码。第三方 Wren GC 只负责对象登记、mark worklist、sweep、阈值与 heap page/free-list 管理。

### 8.9 首版算法选择

首版确定采用 Wren 风格的精确、非移动、stop-the-world mark-sweep：

- Native 指针赋值不执行引用计数。
- Native 指针属性写入不需要 write barrier。
- 普通传参只是复制一个指针。
- GC 只在 Native allocation、显式收集和 shutdown safe point 运行。
- 每次收集都从精确 root 开始遍历完整 Native 对象图。
- 循环对象与普通不可达对象使用同一算法回收。
- 收集完成后使用存活字节数计算下一次阈值。

首版采用以下固定默认值：

- 首次收集阈值：16 MiB。
- 收集后的最低阈值：1 MiB。
- 存活堆增长比例：50%。
- 下一次收集阈值：`max(1 MiB, liveBytes + liveBytes * 50%)`。

自适应增长策略沿用 Wren 的成熟设计；TypePHP 将首次阈值设为更规整的 16 MiB。
阈值会随每轮收集后的实际存活字节数自动伸缩，
但不读取 PHP `memory_limit`、主机物理内存或容器内存。PHP `memory_limit` 面向
Zend 请求内存，常见的 128 MiB 默认值不能代表常驻 TypePHP 程序的 Native Heap
预算；按主机内存同比放大阈值也会使相同程序在不同机器上表现不稳定。

16 MiB 只表示首次触发完整收集前允许的累计 Native allocation，并不是预留或立即
申请 16 MiB。1 MiB 下限避免小型存活集反复触发 stop-the-world 收集；50% headroom
在扫描 CPU 与额外内存之间采取比 Go 默认 100% 更保守的折中，因为首版 collector
是单线程 stop-the-world，而不是并发 collector。后续只能依据 TypePHP 的真实分配率、
存活率、暂停时间和峰值内存 benchmark 调整这些内部常量，不开放语言级 GC 调参接口。

选择 stop-the-world 而不是增量 GC 的主要原因，是 TypePHP 程序中的对象传递、字段赋值和引用更新可能非常密集。增量三色 GC 必须在 marking 期间维持颜色不变量，从而在 Native 指针字段写入路径增加 write barrier。即使 barrier 的正常路径只有一次分支，它仍会影响最重要的高频路径。

### 8.10 后续低停顿模式

如果 benchmark 证明完整 mark 阶段停顿不可接受，可以参考 mruby/Lua 增加可选的 incremental 模式，但不能改变默认快速路径：

```cpp
object->child = value;

if (UNLIKELY(native_heap.is_incremental_marking())) {
    native_heap.write_barrier(object, value);
}
```

实际生成时应先判断 GC phase，再只在 marking 阶段执行 barrier。普通模式下编译器可以完全不生成 barrier；启用 incremental 模式时才增加 `UNLIKELY` 分支。

Generational GC 需要 remembered set，并使 old-to-young 指针写入长期携带 barrier，不应进入首版。只有在真实应用证明大量 Native Object“朝生夕死”且 full mark 成本明显时再评估。

## 9. 参数传递

普通对象参数按指针值传递：

```php
function move(Point $point, float $x): void
{
    $point->x = $x;
}
```

近似生成：

```cpp
void php_move(php_app__point *point, php::Float x);
```

这同时满足：

- 不复制对象实体。
- 不增加引用计数。
- 修改属性对调用者可见。
- 在函数内部重新赋值 `$point` 不影响调用者变量。

这里不需要、也不允许 PHP 引用符号：

```php
function replace(Point &$point): void; // FatalError
$alias =& $point;                       // FatalError
refval($point);                         // FatalError
$point->toRef();                        // FatalError
```

普通的 `$alias = $point` 已经只复制有类型的指针，二者指向并修改同一个对象。

返回 Native Object 时返回指针：

```cpp
php_app__point *php_create_point();
```

非 nullable class 参数在函数入口执行一次空指针检查。确定非空的成员访问不应重复检查。
nullable class 必须使用 `?Point`，使用相同指针表示，`nullptr` 表示 `null`。
`Point $value = null` 这种隐式 nullable 声明不支持，必须写为 `?Point $value = null`。
`Point|null`、其他 union/intersection、Native variadic 参数以及 Native 引用返回均不支持。
参数和返回值必须显式声明具体 Native Class（或其 nullable 形式），不能通过 `mixed`、
`object` 或 Interface carrier 传递。

例如：

```php
function bar(Point $point): void
{
    // 函数入口已经完成非空检查；进入函数体后 $point 一定指向 Point 对象。
    echo $point->x;
}

function maybeBar(?Point $point): void
{
    // $point 可能是空指针，使用前必须先收窄或由成员访问生成空值检查。
    if ($point !== null) {
        echo $point->x;
    }
}
```

两者的 C++ ABI 都使用 `php_app__point *`，但契约不同：`bar()` 在执行第一条用户
语句前拒绝 `nullptr`；`maybeBar()` 接受 `nullptr`。这项入口保证只约束传入时的值，
函数内部仍可把自己的局部指针槽重新赋为 `null`，且不会改变调用者的变量槽。

## 10. ZendVM 边界

Native Object 没有对应的 `zval` 表示，因此只能传给明确接受相同 Native Class或其 Native 基类的参数。Interface 只用于校验 Native Class 的声明契约，不能作为 Native Object 的参数、属性、变量或返回值 carrier。

Native Class 的字段可以保存 `php::Var`、`php::Array` 或 `php::Object`，但这并不会使外层 Native Object 获得 `zval` 表示。允许“Zend 值进入 Native 字段”，不等于允许“Native Object 进入 ZendVM”。

初版禁止将 Native Object：

- 赋值给 `mixed` 或普通 `object`。
- 转换为 `php::Var` 或 `php::Object`。
- 传给未知 PHP 函数、PHP 扩展函数或动态方法。
- 使用 `$nativeObject->$expr()`、`$nativeObject->{$expr}()` 等变量方法名调用。
- 放入普通 PHP `array`。
- 捕获到需要注册为 Zend Closure 的闭包中。
- 作为 TypePHP Generator 的参数、`this`、局部变量、返回类型或 `yield` 值。Generator
  由 Zend Closure/Fiber 状态机表示，Native pointer 不进入该 Zend 状态。
- 作为 Fiber API 的传入值、恢复值或 Closure capture。普通 TypePHP 函数可以在自己的
  C++ 局部槽中保存 Native Object 并跨越 `Fiber::suspend()`；Native Root Frame 使用
  可任意 O(1) 摘除的 thread-local 双向侵入链表，GC 会同时扫描运行中与挂起 Fiber 的
  有效 frame，不依赖跨 Fiber 的 LIFO 析构顺序。
- 作为 `call_user_func()` 等动态 callback 的 receiver。
- 保存到 ZendVM 全局变量或对象属性中。

Box 不能保存 Native Object。Std Container 不能作为 Native Class 属性，但局部
`std::array`、`std::vector`、`std::map` 和 `std::ordered_map` 可以使用具体
`NativeClass::class` 作为 value type，并保存该类或其 Native 子类。普通 PHP array
仍然不能保存 Native Object。

TypePHP 当前的 Std Container 本身就只允许作为函数内的局部变量，不允许作为
global/static，因此不存在需要为 Native 元素另外设计的长期容器所有权。Native 元素
Std Container 进一步要求它是函数顶层的局部变量。编译器为该局部容器生成与其
词法生命周期一致的 `NativeContainerRootFrame`；因此它不能保存到 global/static、
Zend 或 Native 属性、PHP array，也不能被返回、取引用、捕获进 Closure/arrow
function，或通过 `toArray()`/`toAny()` 等方式转换。上述行为都会让保存裸指针的
`StdContainerBox` 比 root frame 活得更久，必须在编译期统一拒绝。读取或写入单个
typed Native 元素仍然保持在 Native 指针模型内，不属于容器逃逸。

任何跨越 ZendVM 边界的行为都应在编译期抛出 FatalError。编译器不得静默装箱或降级，因为这会使性能模型不可预测。

Native Object 必须始终保持 typed object。它不能被擦除为 `var`、`mixed`、普通 `object` 或无类型 callback receiver。即使编译器能够常量折叠 `$expr = 'run'`，变量方法名语法仍不支持；只有源码中明确写出的 `$nativeObject->run()` 才进入 Native method resolution。

## 11. 属性访问

没有 Hook 的属性直接访问字段：

```php
$point->x = 1.0;
echo $point->x;
```

近似生成：

```cpp
point->x = 1.0;
echo(point->x);
```

所有实体属性必须显式声明类型。初版不支持动态属性、字符串属性名、`__get()` 和 `__set()`。

Visibility 只在编译期检查，不生成运行时访问控制元数据。

### 11.1 Visibility

`public`、`protected`、`private` 的访问权限完全由 Native Class 编译器静态检查。运行时不保存 visibility flag，也不执行 scope 切换或权限判断。

生成的 C++ `struct` 中所有字段都保持 public：

```cpp
struct php_app__user final {
    php::Str name;
    php::Int age;
};
```

PHP 源码中的 `private string $name` 不生成 C++ `private:`。这是因为方法是 `php_*` 自由函数，C++ private field 会阻止对应方法函数直接访问字段，并迫使实现引入 friend、成员方法或额外 accessor。

编译器必须在以下位置完成静态权限检查：

- 直接属性读取和写入。
- Getter、Setter 和 Property Hook lowering。
- 方法调用和静态方法调用。
- clone 字段复制。
- Trait AST 注入后的访问。
- 编译器生成的辅助代码。

Native Object 不能进入动态调用、Reflection 或 ZendVM，因此不存在运行时绕过 visibility 的合法入口。手工编写 C++ 代码直接访问字段不属于 TypePHP 语言兼容范围。

## 12. Getter、Setter 和生成器注解

Getter、Setter 等纯编译期生成器注解可以支持。它们应先展开为普通 AST，再由 Native Class 分支生成 `php_*` 自由函数。

```php
#[Native]
final class User
{
    #[Getter]
    #[Setter]
    private string $name;
}
```

近似生成：

```cpp
struct php_app__user final {
    php::Str name;
};

php::Str php_app__user__getname(php_app__user &this_) {
    return this_.name;
}

void php_app__user__setname(php_app__user &this_, php::Str value) {
    this_.name = value;
}
```

简单 Getter/Setter 应允许 C++ 编译器完全内联。Native Class 不注册注解或生成 Reflection 元数据。

原则上可以支持所有只修改 AST、不依赖 ZendVM 的生成器注解。具体支持清单需要在实现前逐项确认。

### 12.1 Trait AST 注入

Native Class 支持 Trait。Trait 不建立独立的 Native runtime 类型，也不生成对象实体；继续复用 TypePHP 现有的编译期 AST 注入机制。

处理顺序固定为：

1. 解析 class 和 Trait，并完成 `use`、`insteadof`、`as` 及冲突检查。
2. 在 convert 阶段把 Trait 的属性、常量、方法和 Property Hook AST 注入目标 class。
3. 保留节点的 Trait 来源、Trait namespace/use context 和 `__TRAIT__` 信息。
4. 对注入后的完整 class AST 执行 Native Class 类型、visibility、继承、Interface 和边界检查。
5. 将注入成员与普通 class member 一样生成字段及 `php_*` 方法。

同一个 Trait 可以同时被普通 TypePHP class 和 Native Class 使用；最终采用哪一种对象模型，由目标 class 决定。Trait 注入后的属性仍必须具有合法的显式类型，方法也必须满足 Native Class 的 ZendVM 边界限制。

### 12.2 Interface

Interface 不接受 `#[Native]`。它仍是普通 PHP/TypePHP Interface，照常注册到
ZendVM；普通 PHP class 实现该 Interface 的行为不变。Native Class 支持
`implements`，但它与该 Interface 的关系只存在于 TypePHP 编译期：

- 使用与 PHP 一致的规则检查 required method 和 hooked property 是否存在、visibility、
  static/引用/variadic、参数与返回类型及属性读写约束是否兼容。
- 项目 Interface 使用预处理得到的完整声明；PHP 内置 Interface 使用 Reflection 得到的
  正式签名。Tentative return type 保持 PHP 8.4 的非致命语义，不擅自升级为 FatalError。
- 在 Trait AST 注入及继承成员合并完成后检查，因此 Trait 或父类提供的方法可以满足 Interface。
- 支持 Interface 继承和多个 `implements` 声明。
- 不为 Native Class 生成 Interface vtable、runtime interface id、`zend_class_entry` 或
  Reflection 元数据；ZendVM 不会看到该 Native Class 是 Interface 的 implementor。
- 当 receiver 的具体 Native Class 在编译期已知时，`$native instanceof SomeInterface`
  根据完整 `implements` 关系直接折叠为 `true` 或 `false`。
- 不支持动态 Interface cast，也不能把 Native Object 交给 ZendVM 的 Interface 参数或
  使用 Reflection 查询其实现关系。

Interface 类型不能成为 Native Object 的类型擦除 carrier。即使调用点知道具体 Native
Class，也禁止把 Native Object 传给 Interface typed parameter，或赋值、返回为 Interface
类型。Interface typed 参数和属性仍可正常保存 Zend Object，但不能同时保存 Native
Object。编译器不得为此生成 `reinterpret_cast`、`void *` 转换或临时 Zend Object；错误
转换会破坏对象布局并可能导致 crash，因此必须在 C++ 代码生成前抛出 FatalError。

首版明确不提供调用点静态特化、fat pointer 或 interface table。需要共享 Native 方法实现
时使用 Trait；需要多态传参时使用具有真实 C++ 继承关系的 Native 基类。若未来确实需要在
多个无共同 Native 基类的实现之间做运行时动态分派，应作为新的对象表示单独设计，不能
偷偷把 Native Object 装箱为 Zend Object，也不能改变当前裸指针 Native Call 的热路径。

### 12.3 `Iterator` 与 `IteratorAggregate`

Native Class 只有显式实现 `Iterator` 或 `IteratorAggregate` 时才允许作为 `foreach`
的 iterable。编译器不会像 ZendVM 那样回退为“遍历当前作用域可见的对象属性”；没有
迭代接口的 Native Object 在编译期报错。

`Iterator` 完全降级为确定的 Native Method Call，调用顺序与 PHP 一致：

```text
rewind() → valid() → current() → key() → loop body → next()
```

未绑定 key variable 时不调用 `key()`。编译器在循环入口只求值一次 iterable，并用独立
的精确 GC root 保存它；因此循环体重新赋值原变量不会改变正在执行的 iterator。
`continue` 通过 C++ `for` 的 iteration expression 调用 `next()`，`break` 则不会调用。
Native iterator 的 null 检查只在循环入口执行一次，协议方法的热路径不重复检查。

`IteratorAggregate::getIterator()` 只调用一次：

- 返回具体 Native Class 且该类实现 `Iterator` 时，继续使用上述全 Native 路径；
- 返回普通 PHP `Traversable` 时，只有返回对象进入现有 PHPX `ForeachIterator`；
- 其他返回类型在编译期拒绝。

`current()` 可以声明返回具体 Native Class，foreach value variable 会被推断为对应的 typed
Native pointer。Native `foreach` 不支持 `&$value`，避免引用和间接修改进入迭代协议。

### 12.4 `instanceof`

Native Class 没有 `zend_class_entry` 或运行时类名查找，因此只支持目标 class
能够在编译期解析的 `instanceof`。编译器依据 Native 静态类型与继承关系直接
折叠为 `true` 或 `false`，但仍保留左操作数中构造、函数调用等副作用：

```php
$object instanceof NativeClass;
```

TypePHP 不为 `NativeClass::class` 增加特殊的 `instanceof` 语法。以下运行时
class operand 不支持：

```php
$class = NativeClass::class;
$object instanceof $class; // FatalError
```

如果变量的静态类型是 Native 父类，而目标是其子类，结果依赖对象的运行时动态
类型，编译器同样抛出 FatalError，不伪造错误的布尔结果。

## 13. Property Hook

Property Hook 可以编译为确定的 C++ getter/setter：

```php
#[Native]
final class User
{
    public string $name {
        get => strtoupper($this->name);
        set => trim($value);
    }
}
```

近似生成：

```cpp
struct php_app__user final {
    php::Str name_storage;
};

php::Str php_app__user__get_name(php_app__user &this_);
void php_app__user__set_name(php_app__user &this_, php::Str value);
```

读取和赋值分别生成：

```cpp
php_app__user__get_name(*user);
php_app__user__set_name(*user, value);
```

为保持 Native 分支简单且不存在隐式运行时分派，首版只支持直接读取和直接赋值：

```php
$value = $user->count;
$user->count = getValue();
```

带 Hook 的属性禁止：

- `+=`、`.=` 等复合写入。
- `++`、`--`。
- `$object->hookedArray[] = ...`、元素赋值或元素 `unset()` 等间接写入。
- `isset()`、`empty()`。
- 取引用。
- 引用返回。
- 返回底层属性 slot。
- 使用 `int_ref`、`float_ref` 等引用优化。
- 绕过 Hook 直接写入 backing field。

Native Property Hook 只有编译期语义，不生成 Zend Property Hook 元数据。

## 14. Clone

`clone` 可以支持，但必须由编译器生成字段级浅复制，不能无条件依赖 C++ 默认 copy constructor。

当 receiver 的静态类型可能保存 Native 子类时，继承层次生成一个内部协变 virtual clone
thunk，由动态子类执行正确尺寸的字段复制并调用其 `__clone()`；不能按静态基类复制，否则会
发生 C++ object slicing。没有 Native 继承关系的类继续使用静态 clone 路径，不增加 vptr。

```php
$copy = clone $source;
```

近似生成：

```cpp
auto *copy = native_heap.make<php_app__user>();
copy->name = source->name;
copy->profile = source->profile;
php_app__user____clone(*copy);
```

复制规则：

- 标量字段按值复制。
- String、PHP Array、Zend Object、Stream 和 mixed 按各自 PHPX/C++ 类型的复制语义处理。
- PHP Array 保持 PHP 的 copy-on-write 行为，不进行无条件深拷贝。
- Zend Object 字段复制对象句柄，继续指向同一 Zend 对象。
- Native Object 字段复制指针，继续指向同一对象，保持浅复制语义。
- 完成字段复制后调用可选的 `__clone()`；子类未重新声明时会解析并调用继承的
  `__clone()`，子类重新声明时不会隐式再调用父实现，与普通方法覆盖规则一致。
- `__clone()` 的 public/protected/private 可见性在 clone 表达式所在的编译期作用域检查，
  不允许通过直接 Native Call 绕过。
- clone operand 可以是 typed variable、Native function/method call 或 Native property
  expression；非变量 operand 会先物化为精确 root 的临时裸指针。

包含不可复制字段的 Native Class 必须显式禁止 clone；对它使用 `clone` 时编译期报错。

## 15. 构造和析构

### 15.1 构造

`new` 在 Native Heap 中创建结构，然后直接调用构造函数对应的 `php_*` 自由函数：

```cpp
auto *object = native_heap.make<php_app__user>();
php_app__user____construct(*object, args...);
```

构造函数抛出异常时，必须销毁已经初始化的字段，并从 Native Heap 活动对象集合中移除该对象。

与其他 TypePHP class 一致，`__construct()` 只能由 `new` 触发。显式调用
`$object->__construct()` 在编译期报错，避免重复初始化已经存活的 Native Object。

### 15.2 析构

`__destruct()` 与 PHP 的精确析构时机存在冲突。Tracing GC 只能保证在对象变为不可达并完成 GC 后执行资源清理，不能保证在最后一个变量离开作用域时立即执行。

Native Class 必须支持用户定义的 `__destruct()`，但采用 tracing GC 的生命周期语义：

- 对象在一次 GC 中被确认不可达，或 Native Heap shutdown 时，调用 `__destruct()`。
- 每个对象最多调用一次用户析构逻辑。
- 用户代码不能显式调用 `$object->__destruct()`、`self::__destruct()` 或 `parent::__destruct()`；编译期直接报 FatalError。
- 同一个回收批次内，不同对象之间的析构顺序不保证与 PHP 一致。
- 继承链上的析构按最派生类到基类的顺序自动执行，不要求也不允许用户显式调用父类析构。

用户 `__destruct()` 不能直接作为实际 C++ destructor 的函数体。原因是 TypePHP 方法可能抛出异常、调用 ZendVM 或再次分配 Native Object；让这些行为从 C++ destructor 中发生，尤其是在异常栈展开期间，可能触发 `std::terminate()`，也无法安全处理对象复活。

因此使用两个明确分离的阶段：

```cpp
struct NativeTypeDescriptor {
    void (*trace)(void *object, NativeMarker &marker);
    void (*finalize)(void *object); // 调用 php_* __destruct 链
    void (*destroy)(void *object);  // C++ destructor + 释放存储
};
```

1. GC 将不可达对象从 active set 摘除并标记为 `finalizing`。
2. 在 GC 标记/扫描临界区之外调用动态类型 descriptor 的 `finalize()`。
3. `finalize()` 自动按 derived-to-base 顺序调用各层声明的 `php_*__destruct` 自由函数。
4. 用户析构完成后重新检查 roots；如果对象在析构期间被重新保存到 Native root，则保留对象，但将其标记为 `finalized`，以后不再调用用户析构。
5. 未复活对象调用 `destroy()`；实际 C++ destructor 只负责字段 RAII 和基类子对象清理，保持 `noexcept`。descriptor 已记录最派生类型，因此不依赖通过基类指针执行 `delete`，也不要求仅为销毁而给所有继承层次增加 vtable。
6. finalizer 抛出异常时，GC 必须先恢复内部状态并保证对象最终可清理，再把异常传播到当前 TypePHP 异常边界；shutdown 阶段遵循单独的不可抛出策略。

Request shutdown 会在 finalization 前后各清空一次已注册的 global/static Native root。
这是必要的：`__destruct()` 可能在 finalization 中把 `$this` 重新写入某个全局槽，但 request
heap 随后仍会整体销毁；第二次清空可防止悬空指针进入下一 request。

这种设计保留 `__destruct()` 的资源清理能力，同时避免让复杂用户代码穿过 C++ destructor。它与 PHP 的主要差异是调用时机由 Native GC 决定，而不是引用计数降为零的时刻。

### 15.3 `unset()` 与析构时机

Native 局部变量是一个由 root frame 跟踪的 `native_struct *` 槽。普通赋值只复制指针，因此多个变量可以引用同一对象。

`unset($object)` 和 `$object = null` 只把当前指针槽设为 `nullptr`，既不清零对象属性，也不影响其他别名。只有对象已经不存在其他 Native root 或 Native field 引用，并在下一次 GC 或 shutdown 中被确认不可达，才进入 finalization。这保持了 PHP 的对象身份与别名语义，但不保证 PHP 引用计数归零时的立即析构时机。

方法调用的空值检查应由编译器的 nullability 分析决定：`new`、非 nullable 参数完成入口检查后的值以及 Native `this_` 可直接解引用；nullable/global/static 或控制流合并后无法证明非空的值才生成 `UNEXPECTED(ptr == nullptr)` 运行时检查。

### 15.4 关键词转换方法

Native Class 支持 `toArray()`、`toString()`、`toInt()`、`toFloat()`、`toBool()` 等 TypePHP 关键词转换方法，但不会进入 PHPX 的动态转换 helper。编译器要求 Native Class 实际声明对应的零参数方法，并把调用直接 lowering 为 Native Call。

`toObject()` 遵循相同规则：Native Class 若声明 `toObject(): object`，关键词调用直接指向
这个确定的 Native 方法；未声明、声明了参数或返回值不是 object 时均在编译期报错。这里
的 `toObject()` 是用户定义的数据转换方法，不是把 Native pointer 交给通用 PHPX
`php::toObject()` helper，也不会为 Native Object 建立隐式 Zend carrier。

方法返回类型必须与关键词类型完全一致。例如 `toArray(): array`、`toInt(): int`、`toString(): string`；缺少方法、接收参数、按引用返回或返回类型不同均为编译期 FatalError。

对象条件与显式转换是两套语义。`if ($object)`、`!$object`、`$left && $right` 和
`$left || $right` 只判断 Native pointer 是否为 `nullptr`，不会调用 `toBool()`；这与
PHP 对普通对象“存在即为 true”的语义一致，也使 nullable Native pointer 可以直接作为
条件。只有显式 `(bool) $object` 或 `$object->toBool()` 才解析为 Native `toBool(): bool`
调用；没有定义该方法时在编译期报错。即使类定义的 `toBool()` 返回 `false`，一个非空
对象在 `if ($object)` 中仍为 `true`。

`__toString(): string` 是 `toString(): string` 的兼容别名。对 Native Object 使用 `toString()`、`strval($object)`、`(string) $object`、字符串拼接或 `echo` 时，编译器优先使用实际声明的 `toString()`，若不存在则使用 `__toString()`。

与 PHP 一致，声明合法 `__toString()` 的 Native Class 在编译期隐式满足 `Stringable`；
`$native instanceof Stringable` 折叠为 `true`，但这仍不允许把 Native Object 转换或
传递为 `Stringable` Interface 值。

### 15.5 `count()` 与 `Countable`

当编译器能静态确定 Native Class 实现了 `Countable` 时，`count($nativeObject)` 等价于
`$nativeObject->count()`，并直接 lowering 为同一个 Native Call。Native Object 不会因此构造
Zend Object，也不会进入 `php::fn::count()`。

仅仅声明一个名为 `count()` 的方法并不足够；Native Class 必须显式 `implements Countable`，且
实现会经过内部 Interface 签名校验。首版只支持 `count($nativeObject)` 单参数形式；带 `$mode`
的形式不进入这条 Native 特化路径。

### 15.6 Nullsafe operator

Native root 及每一个中间 receiver 都是 Native pointer 时，`?->` 使用专门的短路
lowering。每一级只执行一次 `nullptr` 判断，方法参数只在 receiver 非空后求值。最终结果
为 Native Object 时继续返回 nullable typed pointer；最终结果为 PHP 标量或 PHPX value
时，因为 PHP 语义是 `T|null`，只在结果边界装箱为 `php::Var`。

Native nullsafe chain 不能在中间切换到 Zend Object 后继续；该混合对象模型链在编译期
拒绝，用户应拆成两条语句。Native Property Hook 可以作为最终的直接读取；
`isset()/empty()` 不支持 Hook 属性。

### 15.7 `json_encode()`

Native Object 没有 `zval` 表示，不能作为 `json_encode()` 或其他 PHP/ZendVM 函数的参数。编译器不会为 `json_encode()` 增加特殊 lowering，也不会隐式构造临时 Zend Object 或 DTO；`json_encode($nativeObject)` 在编译期直接报错。

需要 JSON 时，Native Class 应显式提供返回 PHP array 的 `toArray(): array`，再由用户调用：

```php
$json = json_encode($nativeObject->toArray());
```

显式转换使分配成本和对象图转换边界在源码中清晰可见，也保持“Native Object 不跨越 ZendVM 边界”的统一规则。

## 16. 首版支持边界

| 特性 | 首版建议 |
|---|---|
| 固定类型属性 | 支持 |
| string/array/object/Stream/mixed 属性 | 支持，使用对应 PHPX RAII 字段 |
| 无类型属性 | 不支持，编译期 FatalError |
| 直接属性读写 | 支持 |
| 普通成员方法 | 支持 |
| Native Object 参数/返回值 | 必须显式声明具体 Native Class；按 pointer value 传递，不复制对象 |
| non-null Native 参数 | `NativeClass $value` 在函数入口统一拒绝 `nullptr`；进入函数体后保证非空 |
| nullable Native 参数/返回值 | 支持 `?NativeClass`，以 `nullptr` 表示；成员访问必须检查或先证明非空 |
| Native 参数/返回值的 `&` | 不支持；编译期 FatalError |
| 对 Native Object 变量取引用 | 不支持；普通赋值已经共享对象身份 |
| 对 Native 属性取引用 | 仅显式声明为 `any` 的字段支持；包括 `mixed` 在内的其他字段均编译期 FatalError |
| Native variadic、union/intersection | 不支持；编译期 FatalError |
| `__construct()` | 支持 |
| `clone` / `__clone()` | 支持 |
| Getter/Setter 注解 | 支持 |
| Property Hook | 支持直接 get/set；间接写入、复合写入、引用、isset/empty 不支持 |
| Trait AST 注入 | 支持，注入完成后按普通 Native member 编译 |
| `readonly` | 不支持，编译期 FatalError；PHP readonly 是依赖 Zend 属性初始化状态的运行时机制，与 Native 固定裸字段模型不兼容 |
| `toArray()`/`toInt()`/`toObject()` 等关键词转换 | 支持，要求 Native Class 声明零参数且返回类型完全一致的方法；直接生成 Native Call |
| `toString()` / `__toString()` | 支持确定 Native Call；字符串强转、`strval()`、拼接和 `echo` 使用同一规则 |
| `count($nativeObject)` | 支持确定 Native Call；要求 Native Class 实现 `Countable`，首版限单参数形式 |
| `isset()` / `empty()` | 支持裸指针槽及纯 Native 命名属性链，逐级短路，不进入 ZendVM |
| `is_null()` | 支持 Native typed pointer，直接与 `nullptr` 比较 |
| Nullsafe `?->` | 支持纯 Native receiver chain；Native 返回保持 typed pointer，标量返回按 `T|null` 装箱 |
| `__invoke()` | 支持确定 Native Call |
| `__destruct()` | 支持，由 GC finalization 触发且每个对象最多一次 |
| Native Class 单继承 | 支持；与普通 ZendVM class 禁止互相继承 |
| Native abstract class / abstract method | 支持；生成 pure virtual thunk，具体子类在编译期完成实现检查 |
| override method | 支持，继承链同名实例方法生成 virtual dispatch thunk |
| 基于参数签名的同名方法重载 | 不支持；PHP 源码不允许在同一个类中重复声明同名方法 |
| Interface | 普通 Interface 注册到 ZendVM；Native `implements` 只做编译期契约校验，Native Object 不能转换为 Interface 值 |
| `foreach` / `Iterator` | 实现 `Iterator` 时直接生成 `rewind/valid/current/key/next` Native Call；iterable 只求值一次，支持 `continue`/`break` |
| `IteratorAggregate` | `getIterator()` 只调用一次；具体 Native Iterator 继续走 Native 路径，PHP `Traversable` 走 PHPX iterator |
| Native `foreach` 引用遍历 | 不支持 `foreach ($native as &$value)`；编译期 FatalError |
| 未实现迭代接口的 Native Object | 不枚举 public 属性；用于 `foreach` 时编译期 FatalError |
| `instanceof` | 支持编译期可解析的 Native class 和 Interface，直接折叠；变量 class 不支持 |
| `===` / `!==` | 支持 Native 指针身份及与 `null` 的严格比较 |
| Native 条件的 `match` | 支持，使用与 `===` 相同的指针身份规则 |
| `==` / `!=`、大小及算术/位运算 | 不支持，编译期 FatalError；值相等应使用显式 Native 方法 |
| 一元算术/位运算、`++`/`--`、复合算术赋值、`switch` | 不支持，编译期 FatalError |
| 动态属性 | 不支持 |
| `$nativeObject->$expr()` | 不支持，只允许命名方法调用 |
| `__call()` / `__callStatic()` | 不支持；Native Call 必须在编译期解析为确定符号 |
| `__get()` / `__set()` / `__isset()` / `__unset()` | 不支持，以命名属性与 Property Hook 替代 |
| `__sleep()` / `__wakeup()` / `__serialize()` / `__unserialize()` | 不支持；Native Object 不进入 Zend 序列化系统 |
| `__set_state()` / `__debugInfo()` | 不支持；Native Object 没有相应 Zend object handler |
| Reflection | 不支持 |
| TypePHP Generator 保存或产出 Native Object | 不支持；编译期 FatalError |
| 普通函数的 Native 局部变量跨 `Fiber::suspend()` | 支持；Root Frame 注册表允许非 LIFO Fiber 生命周期 |
| `get_class()` / `get_parent_class()` / `get_called_class()` | 不支持 Native runtime introspection；使用 `self::class`、`parent::class` 或具体类名 |
| WeakReference | 不支持 |
| PHP serialize | 不支持 |
| PHP `json_encode()` | 不支持直接传入 Native Object；先显式调用 `toArray()` |
| 动态 callback | 不支持 |
| 动态 PHP/eval 使用 | 不支持 |
| 普通 PHP array 保存 Native Object | 不支持 |
| Native Object 作为 PHP array key | 不支持；编译期 FatalError |
| Native Object 作为 `[]` receiver | 实现 `ArrayAccess` 时支持直接读写、追加、`isset`、`empty`、`??` 和 `unset`；直接生成 Native `offset*()` 调用 |
| Native `ArrayAccess` 元素间接修改 | 不支持 `++/--`、复合赋值、`??=`、嵌套写入、属性写入和取引用；编译期 FatalError |
| Box/Std Container 属性 | 不支持 |
| Box 保存 Native Object | 不支持 |
| 局部 Std Container 保存 Native Object | 仅支持函数顶层局部变量和具体 Native class value type；容器 Root Frame 参与 GC tracing |
| Native 元素 Std Container 转 PHP array/mixed 或作为 PHP 参数 | 不支持；裸指针不得越过 ZendVM value boundary |
| Native Class 属性循环引用 | 支持，指针字段加 Native tracing GC |
| TypePHP global/static local | 支持；ZTS 使用 thread-local request roots，RSHUTDOWN 清理 |
| `$GLOBALS` 访问 Native global | 字面量或编译期可求值的字符串常量映射到同一 C++ slot；动态键不支持 Native Object |
| global/static local 类型 | 第一次 Native 赋值固定 C++ slot 类型；后续可写入其 Native 子类或 null，不可改为基类/无关类 |
| Native Class 属性循环类型 | 支持；字段零值为 `nullptr`，类型图使用 C++ 前置声明 |
| late static binding / `new static()` | 不支持；Native Class 无运行时 `zend_class_entry`，使用 `self::`、`parent::` 或具体类名 |

## 17. 实际目录与隔离方式

当前实现把对象模型的主体规则集中在以下位置：

```text
src/NativeClass/
├── NativeClassSupportTrait.php       # 声明、布局、方法、边界和 codegen 策略
├── NativeGlobalDiscovery.php         # 项目级 Native global slot 预发现
└── NativeGlobalTypeResolver.php      # 预发现器的只读符号查询边界

src/Transform/NativeClassAttributeLowering.php
src/TypeSystem/NativeTypeCompatibilityTrait.php

phpx/include/phpx_native_gc.h
phpx/src/core/native_gc.cc
phpx/thirdparty/wren-gc/
```

普通 parser、call generator、property resolver 和 control-flow lowering 中只保留进入
Native 策略所需的窄 hook。Native 具体诊断、类型映射、字段生成、virtual thunk、trace、
clone 和 finalizer 规则集中在 `NativeClassSupportTrait`；项目级预分析放在同目录的独立
analyzer 中。这样可以复用 TypePHP 已有 AST、符号表和求值顺序基础设施，而不复制一套
容易发生语义漂移的平行编译器。

隔离约束如下：

1. 普通对象路径不得生成 Native pointer，也不得依赖 Native Heap。
2. 公共 hook 必须先检查确定的 Native 类型；未命中时保持原有路径。
3. 项目中没有 Native class 时，global pre-pass 在扫描源码前立即返回。
4. Native Object 不能通过 fallback 进入 `php::Var`、Zend Object 或动态调用。
5. GC runtime 位于独立 PHPX 头文件和源文件中；第三方 Wren 派生代码保留来源与 MIT
   license 文件。
6. Native 正向测试与编译期拒绝测试分别集中在：

```text
tests/compiler/native-class/
phpunit/src/NativeClass/NativeClassValidationTest.php
```

普通 class 的测试与 Native Class 测试不得混用，以明确两套对象模型的语义边界。

## 18. 诊断原则

所有不支持的行为必须在编译期给出明确错误，不能在运行时崩溃，也不能静默退回 ZendVM。

示例：

```text
Fatal error: Native class object App\Point cannot be passed to parameter $value of type mixed
```

```text
Fatal error: Native class App\Point cannot be used with ReflectionClass
```

```text
Fatal error: Native class objects cannot be stored in a PHP array
```

诊断需要指出具体 ZendVM 边界以及可用的替代方式。

## 19. 性能原则

Native Class 的主要路径必须满足：

- 对象变量为一个裸指针。
- 普通参数传递为一次指针复制。
- 属性访问等价于 C++ 字段访问。
- 非原生 PHP 字段通过固定偏移访问对应 PHPX RAII 对象，不查找属性名称。
- 确定方法调用等价于普通 C++ 函数调用。
- 不使用原子操作。
- 不使用哈希表查找属性或方法。
- 不创建临时 Zend Object。
- 不执行运行时 class name 比较。
- 不为了兼容动态能力插入隐藏的 fallback。

若某个 PHP 特性无法满足这些要求，应优先禁止该特性，而不是降低所有 Native Class 的性能。

## 20. 实施状态

已落地的实施阶段：

1. `#[Native] class` 语法、typed pointer 规则和编译期诊断边界。
2. C++ struct、固定字段、descriptor、trace 和 `php_*` 方法生成。
3. Wren 风格 Native Heap、精确 root frame、循环回收、异常恢复和 request shutdown。
4. 构造、析构、属性类型、PHPX 字段、普通方法及 typed pointer 参数/返回。
5. Trait AST 注入、Getter/Setter 和关键词方法直接调用。
6. 单继承、abstract、override virtual thunk、签名 variance 和 Interface 编译期契约。
7. Property Hook 直接 getter/setter lowering，并拒绝所有间接与复合写入。
8. clone、动态子类 clone、循环类型、生命周期失败和对象复活处理。
9. Std Container 局部 Native value、Fiber root、global/static request root 与跨文件 slot
   ABI 预发现。

`json_encode()` 已确定不支持直接接收 Native Object；使用显式 `toArray()` 边界。
栈分配和逃逸分析仍属于独立的后续性能优化，不是当前对象模型正确性的组成部分。
每一项已实现能力均同时具有 PHPT、PHPUnit 或 PHPX C++ 测试，详细对应关系见验收矩阵。

## 21. 已确定但仍需性能验证的参数

- Native Object 永远不能转换或赋值为 Interface 类型；`implements` 只提供编译期契约
  校验，首版不提供调用点特化、fat pointer 或 interface table。
- Native Heap 使用 16 MiB 首次阈值、1 MiB 最低阈值和 50% live-heap headroom。

这些约定已经固定。后续 benchmark 可以调整 GC 的内部数值，但不得改变 Native Object
不做 Interface 类型擦除、没有 Zend 表示、热路径使用裸指针 Native Call 的基本设计。

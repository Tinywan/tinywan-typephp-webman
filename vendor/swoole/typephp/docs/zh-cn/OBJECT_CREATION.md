# Zend Object 创建与属性默认值初始化

本文记录 TypePHP 生成的 Zend Class 在 MINIT 和对象创建阶段的初始化职责，重点说明何时需要自定义 `create_object`、其中允许执行哪些行为，以及对象创建热路径上的性能边界。

本文只讨论注册到 ZendVM 的普通 TypePHP Class。`#[Native]` Class 使用 Native Heap 与 GC，不走本文流程。

## 1. 两个初始化阶段必须分开

TypePHP Class 的属性初始化分为两个阶段：

1. `gen_stub.php` 在 MINIT 生成 `register_class_*()`，建立 `zend_class_entry`、属性元数据和默认属性表；
2. 只有默认属性表无法准确表达的值，才在每次创建对象时由自定义 `create_object` 补充。

这两个阶段不能重复执行相同的属性赋值。`register_class_*()` 已写入的值会由 Zend 的 `object_properties_init()` 复制到新对象；再次调用 `zend_update_property()` 不仅没有语义价值，还会进入属性名查找、类型检查、handler 分派和引用计数路径。

## 2. gen_stub.php 负责的默认值

以下值可以准确写入 Zend Class 的默认属性表：

| 源代码默认值 | 注册阶段表示 | 是否需要在 `create_object` 中再次写入 |
|---|---|---|
| `null` | `ZVAL_NULL` | 否 |
| `bool` | `ZVAL_TRUE/FALSE` | 否 |
| `int` | `ZVAL_LONG` | 否 |
| `float` | `ZVAL_DOUBLE` | 否 |
| `string` | 持久化 `zend_string` | 否 |
| 标量常量表达式 | 编译期求值后的标量 zval | 否 |
| `[]` | `ZVAL_EMPTY_ARRAY` | 否 |
| 没有显式默认值的 TypePHP typed property | TypePHP 规定的零值、空字符串、空数组、`null` 或 `UNDEF` | 否 |

例如：

```php
class Value
{
    private const BASE = 20;

    public int $id = self::BASE + 3;
    public string $name = 'type' . 'php';
    public array $items = [];
}
```

只要表达式能够在编译期安全求值，以上三个属性都应完全依赖 Zend Class 默认属性表。创建 `Value` 时不得再次调用 `zend_update_property()`。

## 3. 默认值何时需要运行时补充

当前 `gen_stub.php` 不能在默认属性表中准确表示以下值。

### 3.1 非空数组

非空数组默认值当前在注册函数中使用 `ZVAL_EMPTY_ARRAY` 作为占位值。每个对象必须构造独立、语义正确的数组值：

```php
class Request
{
    public array $options = ['timeout' => 10];
}
```

因此 `Request::$options` 需要在 `create_object` 中补充。多个对象仍遵守 PHP 数组的 copy-on-write 语义；修改一个对象的数组不得影响其他对象。

数组常量也遵守相同规则。若编译器只能确定它是数组、不能证明它为空，则保守地保留运行时初始化。

### 3.2 Enum case

Enum case 是对象，不是标量常量：

```php
enum State
{
    case Ready;
}

class Task
{
    public State $state = State::Ready;
}
```

类注册代码当前只能先生成占位值，`create_object` 再取得真正的 enum case 对象并写入属性。因此“只有非空数组才需要自定义 `create_object`”并不成立，enum case 是明确的第二类反例。

### 3.3 无法安全解析的常量表达式

若预处理阶段无法证明默认值可由 Zend 默认属性表准确表达，编译器必须保守地保留运行时初始化。优化只能删除已证明冗余的工作，不能根据表达式外形猜测其运行时类型。

## 4. handlers 与父类 allocator

### 4.1 Property Hook 与非对称 set 可见性不单独触发

PHP 8.4 Property Hook、`private(set)` 和 `protected(set)` 会安装 TypePHP 自定义 object handlers，但这本身不要求覆盖 `create_object`。Zend 8.4 的 `object_properties_init()` 直接复制 class default table，不调用 read/write handler；普通 `php::stdCreateObject()` 已能正确设置最终 handlers。

只有该类同时含有非空数组、enum case 等运行时默认值时，才需要自定义创建流程。补充初始化必须绕过 setter；即使使用 `zend_std_write_property()`，PHP 8.4 也会根据 Hook 元数据调用 setter。当前生成代码因此使用编译期已知的 property offset，经 PHPX `Object::attr(offset)` 直接更新 backing slot。

### 4.2 父类自定义对象分配器

若父类来自 PHP 内置扩展，或祖先类拥有自定义对象存储布局，子类不能绕过父类的 allocator。当前类因运行时默认值确实需要自定义创建流程时，必须先调用保存的父类 `create_object`，再补充当前类的值。

TypePHP 父类已经安装自定义 allocator 时，普通子类通常直接继承它。只有子类自身也需要补充初始化时，才生成新的委派层。

## 5. 自定义 create_object 的执行流程

生成代码通过 `typephp_create_object_with_defaults()` 完成以下步骤：

1. 保存类最终的 `default_object_handlers`；
2. 若必须尊重父类对象布局，调用保存的父类 allocator；否则执行 `zend_objects_new()` 与 `object_properties_init()`；
3. 临时把新对象切换到 Zend 标准 object handlers，确保异常路径和其他对象操作处于可控状态；
4. 只执行标记为 `requiresRuntimeDefaultInit` 的属性初始化，并通过缓存的 declared-property offset 直接写 backing slot；
5. 每次写入后检查 Zend 异常；
6. 无论正常返回还是发生 C++ 异常，都恢复最终 handlers；
7. 返回已完整初始化的 `zend_object *`。

初始化器是模板参数和编译期 lambda，不使用 `std::function`，也不会为 lambda 动态分配内存。`delegate_to_base` 是调用点确定的布尔值，优化构建中通常可被 C++ 编译器折叠。

以下行为不属于 `create_object`：

- PHP `__construct()` 的函数体；
- static property 默认值初始化；它在 `module_init()` 中完成；
- 已由默认属性表表达的标量、`null` 和空数组赋值；
- clone 后重新应用默认值；clone 应复制源对象当前状态，而不是重新创建默认状态。

## 6. 已修复的主要性能问题

旧生成逻辑只要类中存在任意显式非 static 默认值，就安装自定义 `create_object`，并在每次创建对象时重新 update 所有默认属性。这会产生两层重复成本：

1. 只含 `public int $value = 0` 的普通类也绕过标准快速创建路径；
2. 一个类只要含有一个非空数组，其他标量属性也会被逐个重复 update。

当前规则已经调整为：

- 只有确实需要运行时补充的属性才使 `requireCtor` 生效；
- 已由 `gen_stub.php` 准确注册的属性不会出现在运行时初始化 block 中；
- 只有 Hook/非对称可见性而没有运行时默认值的类不再生成空的自定义 allocator；
- Hook 与运行时默认值同时存在时，使用固定 property offset 更新 backing slot，不调用 setter。

在 micro benchmark 中，仅包含标量属性的 `new Foo()` 已从约 `1.8s` 降至约 `0.78s`，与同环境 ZendPHP 扣除空循环后的约 `0.83s` 接近。该数字只用于记录优化量级，不是跨机器性能承诺。

## 7. 已实现优化、剩余成本与后续方向

### 7.1 非空数组使用请求级模板与 copy-on-write

不能把非空数组放进 internal class 的默认属性表，但这不等于必须为每个对象重新构建数组。当前生成器已经使用请求级默认值模板：

1. 每个包含运行时数组默认值的类拥有一组 `THREAD_LOCAL php::Var` 模板和一个初始化状态，NTS 构建不引入锁；
2. 第一次创建该类对象时，通过 `UNEXPECTED(!initialized)` 惰性构建该类的全部模板；
3. 模板全部在局部临时值中成功构建后才提交并设置初始化标记，构造异常不会发布半初始化状态；
4. 模板初始化发生在对象分配之前，失败时不会遗留一个尚未返回的对象；
5. 后续创建对象时只把模板 zval 复制到目标 backing slot，即增加一次数组引用计数；
6. 某个对象第一次修改该属性时，由 Zend/PHPX 的 `SEPARATE_ARRAY` 执行 copy-on-write；
7. 在 `module_clean()` 中释放模板并重置初始化状态，request allocator 分配的 HashTable 不会跨越 RSHUTDOWN。

以如下默认值为例：

```php
class Request
{
    public array $options = [
        'timeout' => 10,
        'headers' => ['Accept' => 'application/json'],
    ];
}
```

若创建一万个对象但不修改 `$options`，数组及嵌套数组只构建一次；每个对象只持有共享 zval。若其中一个对象执行 `$request->options['timeout'] = 30`，只有该对象在写入时分离，其他对象和模板保持不变。嵌套数组也继续使用 Zend 原有的逐层 copy-on-write 规则。

PHP 属性默认数组不能包含引用，允许出现在常量表达式中的对象主要是不可变的 enum case，因此共享模板符合默认属性语义。PHPT 已覆盖顶层写入、嵌套写入、`unset`、引用写入和动态对象写入，确认这些路径都会正确分离。

不能简单地在 MINIT 构造持久化数组并传给 `zend_declare_typed_property()`。TypePHP 注册的是 `ZEND_INTERNAL_CLASS`，Zend 8.4 明确禁止 internal property 使用 refcounted default zval；`_object_properties_init()` 的 internal-class 快速路径也不会增加默认值引用计数。非空 array 与 enum object 都属于 refcounted value。

因此，在不改变“TypePHP Class 注册为 internal class”这一基础设计、也不修改 Zend ABI 的前提下，非空数组仍不进入 class default table；表外请求级模板把数组构造成本从“每个对象一次”降为“每个请求、每个默认值一次”。未修改默认数组的对象只承担 zval 复制和引用计数成本，实际修改的对象才承担数组分离成本。

模板按类惰性初始化，而不是在 RINIT 无条件构建全部模板：大型项目中很多类在一次请求内不会实例化。每个对象只增加一个高度可预测的初始化状态分支；第一次之后该分支稳定为 false。

暂不生成模块生命周期的 persistent immutable template。该方案需要完整验证 persistent HashTable、interned string、嵌套数组、MSHUTDOWN 和 ZTS，并且包含运行时常量或 enum case 的数组仍要走请求级路径。在 ZendVM 对这些组合的约束得到充分验证前，请求级模板是安全边界。

### 7.2 已改为固定属性槽写入

运行时补充的属性在编译期已经知道 class、属性名、offset 和类型。当前实现复用 persistent property-offset cache，并通过 `php::Object::attr(offset)` 更新槽位，已经省去每个对象上的属性名 hash 查询、通用 write handler 和 Property Hook setter。

这里仍会为 initializer 建立一个短生命周期 `php::Object` carrier，并读取 offset cache。后续若 profiling 证明它是热点，可以在 MINIT 后直接保存最终 offset，或在 PHPX 增加不取得对象所有权的初始化 helper。任何进一步优化都必须继续处理旧值析构、引用计数、父类 private slot、Hook backing slot 和异常安全，不能退回裸指针的无保护赋值。

### 7.3 Enum case 可提前绑定

Enum case 同样是 refcounted object，不能直接作为 internal class 默认 zval。可考虑在 MINIT 缓存稳定的 enum case 指针或 zval，再在每次创建对象时执行正确的引用计数复制，从而省去重复 class/case 查找；仍不能省略对象属性写入本身。

### 7.4 继承链上的多层 allocator

父类和子类都拥有运行时默认值时，创建流程会逐层委派并执行各自初始化，成本随相关继承层数增长。未来可以对完全由 TypePHP 控制、且没有特殊对象布局的继承链合并初始化计划；内置扩展父类仍必须调用其 allocator。

### 7.5 保守常量可能产生不必要的 allocator

无法在预处理阶段解析的常量会保守进入运行时路径。可以在符号准备完成后增加一次统一的常量默认值分类，减少“实际是标量，但早期无法证明”的自定义 allocator。该优化必须保留 enum case 和数组常量的区别。

### 7.6 自定义 handlers 的动态访问成本

TypePHP 当前为普通 Zend Class 安装属性 handlers，以支持 typed property 的 unset 语义、Property Hook 和非对称写可见性。安装发生在 MINIT，不等同于安装自定义 `create_object`；但动态属性读写仍可能进入 handler。已被编译器解析为固定槽位的 Native 属性访问不应因此退化。

## 8. 回归测试要求

修改该流程至少应覆盖：

- 标量、标量常量表达式和空数组不生成自定义 allocator；
- 非空数组生成 allocator，且两个对象的数组修改互不影响；
- enum case 默认值在对象创建后是真正的 enum object；
- 仅含 Property Hook 或非对称 set 可见性的类不生成空 allocator，且 Reflection、动态读写行为不退化；
- Property Hook/非对称属性与运行时默认值组合时不触发 setter；
- 父子类分别声明运行时默认值时，父类和子类属性都正确；
- 继承内置扩展类时不破坏其对象布局；
- 异常路径恢复 object handlers；
- 自举编译和完整 PHPUnit/PHPT 回归通过。

当前针对代码生成的核心断言位于 `NewObjectCodegenTest`，运行语义由 `default-initialization-paths.phpt`、`default-expressions-inheritance.phpt` 和 Property Hook 测试组覆盖。

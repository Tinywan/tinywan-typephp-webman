# PHP 8.4 Property Hook 集成设计

本文记录 TypePHP 编译器与 PHPX 对 PHP 8.4 Property Hook 的实现方式，重点说明 Zend 元数据注册、对象内省、内存生命周期和版本兼容边界。本文是内部维护文档；用户侧语法说明应放在外部文档仓库。

Interface 中不带实现体的 Property Hook 属于抽象属性契约，不走本文描述的具体类 lowering 流程；其模型、方差检查和 Zend 元数据注册见 [Interface Property Hook 实现方案](INTERFACE_PROPERTY_HOOKS.md)。

## 1. 背景

TypePHP 会把 Property Hook 的函数体编译成隐藏的 AOT getter/setter。仅完成这一步，可以满足编译器明确识别出的属性读写，但 ZendVM 并不知道这些隐藏方法代表 Property Hook，因此以下动态能力会与 PHP 8.4 不一致：

- `ReflectionProperty::hasHooks()`、`getHooks()` 和 `isVirtual()`；
- `get_object_vars()`、`json_encode()` 和 `var_export()`；
- 对象的 `foreach` 遍历；
- backed property 与 virtual property 的存储差异；
- ZendVM 发起的动态属性读写。

TypePHP 不单独模拟这些 PHP 行为。编译器在 lowering 后保留 Hook 元数据，类在 MINIT 阶段注册时由 PHPX 将 AOT 方法接入 PHP 8.4 原生 Property Hook 结构。此后 Reflection 和对象内省复用 ZendVM 的标准实现。

## 2. 编译流程

### 2.1 AST lowering

`PropertyHookLowering` 将每个 Hook 转换为隐藏类方法，并在属性 AST 上记录：

- getter/setter 对应的隐藏方法名；
- Hook 是否访问自身 backing storage；
- 属性是否为 virtual property。

例如：

```php
public string $name {
    get => strtoupper($this->name);
    set => $this->name = trim($value);
}
```

在内部会产生等价的隐藏 getter/setter。Hook 中的 `$this->name` 会被标记为 backing access，避免再次调用 Hook 而递归。

如果 Hook 没有访问 backing storage，则该属性标记为 virtual。这个结论必须在 lowering 阶段获得，因为生成 Zend 属性声明时需要据此决定是否分配属性槽位。

### 2.2 类注册代码

`gen_stub.php` 声明属性并取得 `zend_property_info *` 后生成：

```cpp
typephp_register_property_hooks(
    class_entry,
    property_info,
    getter_method_name,
    setter_method_name
);
```

调用发生在类的持久化注册阶段，不在请求热路径中。

## 3. PHPX 注册流程

PHPX 的 `typephp_register_property_hooks()` 只在 PHP 8.4 及以上版本实现，并位于 TypePHP 专用 helper 中。

### 3.1 查找 AOT 实现方法

PHPX 从类方法表找到 lowering 生成的隐藏方法：

```cpp
zend_hash_str_find_ptr(&ce->function_table, method_name.data(), method_name.size());
```

该方法是已注册的 `zend_internal_function`，其 handler 最终进入 TypePHP 生成的 C++ getter/setter。查找只执行一次；属性读写时不会重复查询函数表。

### 3.2 创建 Hook 函数描述

不能直接修改或复用类方法表中的隐藏函数对象。Zend Property Hook 需要独立的函数身份和属性关联：

```cpp
hook->function_name = "$name::get"; // 或 "$name::set"
hook->prop_info = property_info;
```

PHPX 因此复制一份 `zend_internal_function` 描述，并替换 Hook 专属字段。复制不会生成另一份 C++ 实现；handler、参数信息和其他持久化数据仍来自原 AOT 方法。

独立函数描述可以避免修改隐藏方法后破坏类方法表的 key、反射名称或所有权关系，并让 Reflection 正确报告 `$name::get` 和 `$name::set`。

### 3.3 挂载属性 Hook

PHP 8.4 在 `zend_property_info` 中新增了 Hook 表：

```cpp
property_info->hooks[ZEND_PROPERTY_HOOK_GET] = getter;
property_info->hooks[ZEND_PROPERTY_HOOK_SET] = setter;
```

同时必须更新：

```cpp
ce->num_hooked_props++;
```

Zend 的 Reflection、对象属性构建和继承检查都会读取这些元数据。只注册隐藏方法而不填写 `property_info->hooks`，不会被 Zend 识别为真正的 Property Hook。

### 3.4 安装 Hook 对象遍历器

PHPX 在类没有自定义 iterator 时设置：

```cpp
ce->get_iterator = zend_hooked_object_get_iterator;
```

`zend_hooked_object_get_iterator()` 是 PHP 8.4 在 `zend_property_hooks.h` 中导出的 `ZEND_API`。PHP 自身编译包含 Property Hook 的类时也会安装这个 iterator。

普通对象 iterator 主要遍历物理属性槽，而 Hook iterator 还负责：

- 对 backed property 和 virtual property 调用 getter；
- 跳过没有 getter 的 virtual property；
- 执行属性可见性规则；
- 拒绝不支持的引用遍历；
- 合并动态属性。

因此不应在 PHPX 中复制一套遍历实现。直接复用 Zend 的导出实现可以保持 `foreach` 行为一致，并降低后续维护成本。

## 4. Virtual property

PHP 8.4 使用特殊 offset 表示 virtual property：

```cpp
#define ZEND_VIRTUAL_PROPERTY_OFFSET ((uint32_t) -1)
```

Zend 声明属性时，需要以 `IS_UNDEF` 作为声明值，才会为带 `ZEND_ACC_VIRTUAL` 的属性建立 virtual offset。因此生成代码使用：

```cpp
zval default_value;
ZVAL_UNDEF(&default_value);
```

不能用 `null` 或普通默认值代替，否则 Zend 可能分配 backing slot，`ReflectionProperty::isVirtual()` 也会得到错误结果。

## 5. 对象内省与序列化

当 `ce->num_hooked_props` 非零时，Zend 的 `zend_std_get_properties_for()` 会在 JSON、`get_object_vars()` 和 `var_export()` 等场景调用 `zend_hooked_object_build_properties()`。该函数读取 Hook 后的公开属性值。

序列化采用不同语义：

- virtual property 没有持久状态，不进入序列化结果；
- backed property 序列化 backing value，而不是 getter 计算后的值；
- 私有存储属性仍按 PHP 的属性名修饰规则序列化。

这一区别是 PHP 8.4 的既有行为，不应为了让 JSON 和序列化输出相同而覆盖。

## 6. 生命周期与线程安全

TypePHP AOT 类以 persistent internal class 注册。Hook 表、Hook 函数描述和函数名必须具有相同的进程级生命周期，因此 PHPX 使用：

```cpp
pemalloc(size, true);
zend_string_init(data, length, true);
```

不能使用 request 内存；否则 RSHUTDOWN 后 class entry 会保留悬空指针，下一请求访问属性或 Reflection 时可能崩溃。

注册只发生在 MINIT：

- 请求执行期间只读 Hook 元数据；
- 不需要在每次请求重新构建；
- 不需要在每次属性访问查找隐藏方法；
- NTS 没有锁开销；
- ZTS 下在工作线程处理请求前已完成注册，不会并发修改 class entry。

## 7. PHP 版本边界

TypePHP 与 PHPX 的最低版本均为 PHP 8.4，因此 Property Hook 实现直接使用以下 PHP 8.4 ABI：

- `zend_property_info::hooks`；
- `zend_class_entry::num_hooked_props`；
- `ZEND_PROPERTY_HOOK_*`；
- `ZEND_PROPERTY_HOOK_STRUCT_SIZE`；
- `ZEND_VIRTUAL_PROPERTY_OFFSET`；
- `zend_hooked_object_get_iterator()`。

PHPX 头文件和 CMake 配置会拒绝 PHP 8.4 以下的 headers/`php-config`。PHP 8.4 与 8.5 仍分别构建对应 PHPX 二进制；`--php-version` 只控制源码语法，不要求与 `libphp.so` 的小版本完全相同，但两者都必须不低于 8.4。

## 8. ABI 风险和升级检查

`zend_hooked_object_get_iterator()` 是导出的 Zend API，但 Property Hook 整体仍属于版本相关的底层 Zend ABI。PHP 8.4 没有提供一个完整的高层 `zend_declare_property_hook()` 扩展 API，因此当前实现需要填写 Zend 元数据。

采用该方案的依据是：

1. TypePHP 与 PHPX 版本绑定，并针对具体 PHP 版本重新编译；
2. 注册流程与 Zend 编译器处理原生 Property Hook 的步骤一致；
3. 只复用 Zend 导出的 iterator，不复制其复杂实现；
4. PHP 8.4 以下版本在构建入口统一拒绝；
5. 所有注册均在 MINIT 完成，不增加请求热路径上的名称查找。

升级 PHP 版本时必须检查：

1. `zend_property_info` 的 Hook 字段和所有权是否变化；
2. `ZEND_PROPERTY_HOOK_COUNT` 和 Hook kind 是否增加；
3. virtual property 的声明条件和 offset 是否变化；
4. `zend_hooked_object_get_iterator()` 是否仍为导出 API；
5. class linking、继承、variance 和 Reflection 是否增加新的必填元数据；
6. persistent internal function 的销毁和继承复制规则是否变化。

如果 Zend 将来提供正式的扩展注册 API，应优先迁移到该 API，减少对内部结构布局的直接依赖。

## 9. 测试要求

Property Hook 改动至少需要覆盖：

- 直接 getter/setter 和 backing access；
- virtual property 与 backed property 的 Reflection 差异；
- `hasHooks()`、`getHooks()`、Hook 名称和 final 状态；
- `get_object_vars()`、JSON 和对象 `foreach`；
- 序列化只包含真实存储状态；
- 动态 Zend 属性读写；
- 继承和属性可见性；
- PHP 8.4 与 PHP 8.5 构建。

当前核心回归测试位于：

- `tests/compiler/object_property/property-hooks.phpt`；
- `tests/compiler/object_property/property-hooks-operations.phpt`；
- `tests/compiler/object_property/property-hooks-reflection.phpt`；
- `tests/compiler/object_property/property-hooks-introspection.phpt`。

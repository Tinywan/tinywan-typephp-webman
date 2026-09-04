# Interface Property Hooks 实现方案

本文记录 TP-AOT-010 的设计与实施计划。目标是支持 PHP 8.4 的 Interface Property Hook 契约，同时保持 TypePHP Native 调用的零成本抽象，并让 PHP 8.4 ZendVM 的 Reflection、动态类链接和继承检查获得完整元数据。

## 当前状态（2026-08-14）

第一阶段已经落地：Interface 契约模型、AOT 实现检查、get/set 方向方差、PHPX 抽象 Hook 元数据、Reflection、动态 PHP 实现类及回归测试均已接通。显式 setter 参数类型仍按下文约定在编译期拒绝；完成独立写入类型模型后再开放。

## 1. 设计结论

Interface 中的 Hooked Property 只表示属性契约：

```php
interface Named
{
    public string $name { get; set; }
}
```

- Interface 不持有属性槽，不生成 getter/setter 实现，也不产生访问时的契约检查。
- TypePHP 在编译期验证已知 AOT 类是否满足属性的可见性、类型和 `get`/`set` 能力。
- PHP 8.4 目标在 MINIT 注册原生 Zend Hook 元数据，使 Reflection 和动态 PHP 类获得相同契约。
- TypePHP、PHPX 和最终目标运行时的最低版本均为 PHP 8.4，不提供旧版本降级路径。

## 2. 语法与诊断

支持三类契约：

```php
public string $readable { get; }
public string $writable { set; }
public string $readWrite { get; set; }
```

Interface Property Hook 必须是 `public`、非 `static`、无默认值且 Hook 不得包含函数体。普通 Interface Property、`private`/`protected`、`readonly`、重复或未知 Hook，以及带实现体的 Hook 均在 TypePHP 编译期抛出 FatalError。错误信息应尽可能与 PHP 8.4 一致。

第一阶段只接收隐式 setter 参数：

```php
public string $name { set; }
```

PHP 8.4 还允许 `set(string|Stringable $value)` 这类显式、可逆变的 setter 参数。该语法需要让编译期契约模型与 Zend Hook `arg_info` 同时保存独立于属性读取类型的写入类型；在这部分完成前，TypePHP 会给出明确的编译期错误，不生成可能错误的运行时元数据。

## 3. 编译器模型

Interface Property Hook 不应伪装成普通属性或 lowering 后的普通方法。为其建立独立契约模型，至少保存：

- 属性名和声明节点；
- 解析后的 TypePHP 类型与类类型；
- 是否要求 `get`；
- 是否要求 `set`；
- 可见性及其他用于诊断的标志。

契约存放在 `InterfaceDef` 中。AST/预处理阶段只收集和验证声明，不为 Interface 分配属性槽，不运行具体类使用的 `PropertyHookLowering`，也不生成隐藏方法。

所有类型完成预处理后再执行契约链接：展开父 Interface 契约，然后检查实现类自身或父类提供的属性。普通 public backed property 同时满足读写契约；Hooked Property 根据实际 Hook 能力判断。get-only 类型按读取方向协变，set-only 类型按写入方向逆变，同时包含 get/set 时保持不变。

## 4. PHPX 与 Zend 元数据

现有 `typephp_register_property_hooks()` 用于具有真实 AOT getter/setter 的具体类，不能复用于抽象 Interface Hook。

PHPX 增加独立 helper：

```cpp
typephp_register_abstract_property_hooks(
    zend_class_entry *interface_ce,
    zend_property_info *property_info,
    bool readable,
    bool writable
);
```

TypePHP/PHPX 已统一要求 PHP 8.4+，因此该 helper 直接访问 PHP 8.4 ABI，并负责：

- 持久化分配 `zend_property_info::hooks`；
- 创建没有 handler 的 abstract `get`/`set` `zend_internal_function` 元数据；
- 设置 `ZEND_ACC_PUBLIC | ZEND_ACC_ABSTRACT`、正确的参数/返回类型及 `common.prop_info`；
- 更新 `num_hooked_props`，使 Zend inheritance 和 Reflection 识别该契约；
- 保证所有字符串、Hook 表和函数描述具有 MINIT 级持久生命周期。

生成代码先注册 Interface，再以 `IS_UNDEF`、`ZEND_ACC_PUBLIC | ZEND_ACC_ABSTRACT | ZEND_ACC_VIRTUAL` 声明属性并挂载抽象 Hook，最后才注册和链接实现类。

## 5. PHP 版本边界

TypePHP 区分源码语言版本与链接运行时：

- `--php-version` 只允许 `8.4` 或 `8.5`，用于解析语法和处理项目条件；
- PHPX headers、`libphp` 与最终运行时必须为 PHP 8.4 或更高版本；
- `--php-version` 与 `libphp.so` 的小版本不要求完全一致，例如使用 8.5 语法模式并链接 PHP 8.4 时，最终能否构建仍由实际使用的 Zend API 决定；
- PHP 8.4 以下环境在 TypePHP/PHPX 构建入口直接拒绝。

## 6. TDD 覆盖

实现前先加入失败测试，覆盖：

1. get-only、set-only、get/set Interface 契约；
2. 普通 backed property、Hooked Property 和继承属性满足契约；
3. 缺失属性、缺少 get/set、非 public 和类型不兼容的编译错误；
4. Interface 继承、多个契约的合并与冲突；
5. Reflection 的 abstract、virtual、hasHook/getHook 元数据；
6. PHP 8.4 动态 PHP 类的成功与失败链接；
7. O0/O3 结果一致，Interface 不生成属性槽或 Native Hook 实现；
8. PHPX helper 在 NTS/ZTS 和 PHP 8.4/8.5 下的生命周期与 ABI 回归。

## 7. 实施顺序

1. 添加 TP-AOT-010 正常场景及语法错误 PHPT，确认当前失败。
2. 增加 Interface Property Contract 模型和预处理收集逻辑。
3. 实现 Interface 继承与实现类的编译期契约检查。
4. 在 PHPX 增加抽象 Hook 元数据 helper。
5. 修改 stub 生成和类注册顺序，接入 PHP 8.4 Zend 元数据。
6. 添加 Reflection、动态类链接、目标版本和生成代码测试。
7. 执行 Interface、Property Hook、Reflection 及全量编译器回归。

完成后的运行时属性访问仍直接进入实现类的普通属性或 Native Hook；Interface 契约本身只存在于编译期模型和 MINIT 元数据中，不进入请求热路径。

# C++ 命名空间、前缀与符号 ABI 规则

本文是 TypePHP、PHPX 以及 TypePHP 生成代码的内部 C++ 命名规范。它解决以下问题：

- 区分 TypePHP 运行时逻辑、PHPX ZendAPI 封装、项目私有实现和用户 PHP 符号；
- 防止框架 helper 与用户定义的 PHP 函数或类方法生成相同的 C++ 符号；
- 明确哪些名称属于稳定 ABI，哪些名称仅限单个生成项目内部使用；
- 为新增 helper、缓存、入口函数和生成符号提供统一的命名决策。

## 1. 总体规则

| 命名域 | 含义 | 典型形式 | 可见范围 | ABI 属性 |
| --- | --- | --- | --- | --- |
| `typephp_` | TypePHP 独有的运行时或编译产物支持逻辑 | `typephp_call_parent_constructor()` | TypePHP/PHPX 运行时 | 内部或显式导出 ABI |
| `php::` | 对 ZendAPI、zval、HashTable、call frame 等 PHP 运行时能力的 C++ 封装 | `php::deindirect()` | PHPX C++ API | PHPX API |
| `typephp_project_<project>` | 单个编译项目的私有 C++ 命名空间 | `namespace typephp_project_tpc` | 当前生成项目 | 非公共 ABI |
| `php_` | 用户 PHP 函数和类方法映射后的 C++ callable 符号 | `php_app__user__save()` | 链接器可见 | TypePHP/stub callable ABI |

核心约束：

1. 不得新增全局的框架 `php_*` helper。
2. 与 TypePHP 无关、只是包装 ZendAPI 的能力必须放入 `namespace php`。
3. TypePHP 独有且需要跨生成文件调用的逻辑使用 `typephp_` 前缀。
4. 只服务于一个编译项目的数据和函数放入 `typephp_project_<project>` 命名空间。
5. 全局 `php_*` callable 名称保留给用户 PHP 声明的编译 ABI。

## 2. `typephp_`：TypePHP 独有逻辑

`typephp_` 表示该 API 的语义由 TypePHP 定义，不是 ZendAPI 的一般性 C++ 包装。常见场景包括：

- TypePHP 属性读写规则；
- TypePHP 构造、克隆和父方法调用链；
- TypePHP 编译期 Attribute 的运行时支持；
- TypePHP Native Class、Property Hook 等专属运行时逻辑；
- TypePHP embed runtime 的初始化和关闭入口。

示例：

```cpp
typephp_call_parent_constructor(object, constructor, args);
typephp_call_parent_clone(object, clone_method);
typephp_install_property_handlers(class_entry, handlers);
typephp_write_property_scoped(object, member, value, scope);
TYPEPHP_RUNTIME_INIT(project)(argc, argv);
```

### 2.1 使用边界

- 该前缀是 TypePHP 内部 C/C++ 名称空间，不代表 PHP 用户函数。
- 新增 API 时应使用完整、可识别的 snake_case 名称，不能使用含义过宽的名称，例如 `typephp_call()`。
- 仅在一个 `.cc` 文件中使用的函数还应增加 `static` 或放入匿名命名空间。
- 需要跨动态库边界时，使用对应的导出宏；不需要导出的 helper 不应扩大符号可见性。
- 不要仅因为代码位于 `typephp_helper.h` 就使用 `typephp_`；判断依据是语义是否为 TypePHP 独有。

### 2.2 正反例

```cpp
// 正确：TypePHP 独有的构造链语义。
typephp_call_parent_constructor(object, constructor, args);

// 错误：只是将 INDIRECT zval 物化为普通值，并非 TypePHP 独有。
typephp_deindirect(value);

// 正确：通用 Zend 值包装属于 PHPX。
php::deindirect(value);
```

## 3. `php::`：ZendAPI 的 C++ 封装

`namespace php` 由 PHPX 提供，用于把 Zend 的 C API、宏、裸指针和手工资源管理封装为类型安全、RAII 友好的 C++ API。

这一命名域包含两类能力：

1. PHP 值和运行时对象，如 `php::Var`、`php::Str`、`php::Array`、`php::Object`；
2. ZendAPI 的安全包装，如符号查询、作用域管理、值转换、对象创建和调用。

示例：

```cpp
php::Var value;
php::Array arguments;

auto plain = php::deindirect(value);
auto called_ce = php::getCalledCe(this_);
auto scope = php::getCallableScope(function, this_);
auto create_object = php::getCreateObjectFn(class_entry);
auto globals = php::globalsArray();
```

### 3.1 何时使用 `php::`

满足以下条件时应放入 `namespace php`：

- API 对任何 PHPX C++ 调用者都有意义；
- API 的行为可以完全用 Zend/PHP 运行时语义解释；
- API 不依赖 TypePHP AST、编译期 Attribute 或 TypePHP 特有语言规则；
- API 的主要作用是隐藏 Zend 宏、裸 `zval *`、引用计数或异常检查。

### 3.2 禁止全局 `php_*` helper

以下旧式写法是禁止的：

```cpp
php::Var php_deindirect(const php::Var &value);
php::Str php_get_called_class(php::Object &this_);
zend_class_entry *php_get_called_ce(php::Object &this_);
auto php_get_create_object_fn(zend_class_entry *ce);
```

它们必须写成：

```cpp
namespace php {

Var deindirect(const Var &value);
Str getCalledClass(Object &this_);
zend_class_entry *getCalledCe(Object &this_);
auto getCreateObjectFn(zend_class_entry *ce);

}  // namespace php
```

原因是用户可以合法声明：

```php
function deindirect(mixed $value): mixed {}
function get_called_ce(): string {}
function get_create_object_fn(): string {}
```

这些 PHP 函数会生成 `php_deindirect`、`php_get_called_ce` 和
`php_get_create_object_fn`。如果 PHPX 也在全局定义同名 helper，可能在声明、重载解析或链接阶段发生冲突。

### 3.3 命名风格

PHPX C++ API 使用现有的 camelCase 风格：

```cpp
php::getCalledClass();
php::getClassEntrySafe();
php::getPersistentCache();
php::stdCreateObject();
```

不要把 Zend 的 snake_case 名称机械地保留为全局 C++ 名称。底层调用可以继续使用 Zend 原始 API，例如 `zend_objects_new()`，但对生成代码暴露的包装层应使用 `php::`。

## 4. `typephp_project_<project>`：项目私有命名空间

每个 TypePHP 编译项目拥有独立的 C++ 命名空间：

```text
typephp_project_<target-name>
```

例如项目名为 `tpc`：

```cpp
namespace typephp_project_tpc {
    // Project-private generated state and helpers.
}
```

项目名中的 `-` 和 `*` 会转换为 `_`，其余字符必须满足编译器的 target identifier 校验。独立的 `typephp_project_` 前缀可防止生成 namespace 与全局 `typephp_*` 运行时 helper 冲突；即使项目名以数字开头，最终 C++ namespace 仍是合法标识符。

### 4.1 应放入该命名空间的内容

- literal string 表和 `get_str()`；
- class/function/property cache 表及其访问函数；
- 当前项目的全局变量存储；
- class entry、object handler 和默认属性模板；
- module entry、MINIT/RINIT/RSHUTDOWN 辅助状态；
- `module_init()`、`module_clean()` 等仅在生成 extension 文件内部调用的函数；
- Python module cache 等项目级生成状态。

示意：

```cpp
namespace typephp_project_demo {

static php::Str literal_strings[] = {
    php::Str{"hello"},
};

php::Str &get_str(uint32_t index) {
    return literal_strings[index];
}

static THREAD_LOCAL zend_class_entry *class_map[8];

zend_class_entry *get_class(int id, const php::Str &name) {
    // Resolve and cache a symbol owned by this project.
}

static void module_init() {
    // Initialize this project's generated state.
}

}  // namespace typephp_project_demo
```

### 4.2 可见性与 ABI

- `typephp_project_<project>` 内的名称是实现细节，不是 library stub ABI。
- 可限制为 `static` 的对象和函数应继续标记为 `static`。
- 生成头文件可以声明必须跨 translation unit 使用的项目内部 accessor，但不应暴露底层数组或缓存表。
- 外部手写 C++ 代码不得依赖 literal index、cache index 或项目内部 storage 名称。
- 不同 TypePHP 项目可以链接到同一进程，因为相同的内部短名称位于不同的项目 namespace 中。

### 4.3 作用域优先于名称拼写

项目 namespace 中仍可能出现历史生成名称，例如：

```cpp
typephp_project_demo::php_class_entry_App_User
```

虽然成员名以 `php_` 开头，但完整符号位于 `typephp_project_demo` 中，因此它属于项目私有实现，而不是第 5 节所述的全局用户 callable ABI。新增项目内部 helper 应优先使用不带 `php_` 的短名称，例如 `get_class()`、`get_func()` 和 `get_str()`。

## 5. `php_`：用户 PHP callable 的 C++ ABI

全局 `php_` 前缀用于 TypePHP 将用户声明的 PHP 函数和类方法映射为 C++ callable 符号。这套命名同时被生成代码、library stub 和外部 C++ 实现使用，因此不能随意改变。

示例：

```php
namespace App;

function greet(string $name): string {}

class User
{
    public function save(): bool {}
}
```

概念上的 C++ 符号为：

```cpp
php::Str php_app__greet(php::Str name);
php::Bool php_app__user__save(php::Object &this_);
```

规则包括：

- 使用 `php_` 标识“由 PHP 声明映射而来”；
- PHP namespace、class 和 method/function 名经过规范化后组合；
- `__` 是现有 ABI 的组合分隔符；
- 实例方法的第一个参数是对象 `this_`；
- stub、library 和消费方必须使用完全相同的映射规则。

### 5.1 为什么内部 helper 不能使用 `php_`

`php_` 映射不是独立的保留关键字空间，而是用户 PHP 名称的机械 ABI。以下用户声明：

```php
function deindirect(mixed $value): mixed {}
```

会自然生成：

```cpp
php::Var php_deindirect(php::Var value);
```

因此框架若定义全局 `php_deindirect()`，就侵占了用户符号空间。正确做法是 `php::deindirect()`。

### 5.2 组合冲突

由于当前 ABI 使用 `__` 组合 PHP namespace、class 和 callable 名，下列两个 PHP 声明可能映射到同一个 C++ 符号：

```php
function App\user__test(): void {}

namespace App;
class User
{
    public function test(): void {}
}
```

编译器必须在预处理阶段检测这种情况并抛出 FatalError，不能通过覆盖、链接顺序或增加运行时分派来处理。修改映射分隔规则会破坏既有 stub/ABI，因此冲突必须由用户重命名解决。

### 5.3 入口符号例外

少量 C ABI/嵌入入口由生成器固定定义，不属于普通用户 callable。例如：

```cpp
php_<project>_embed_get_module();
typephp_<project>_runtime_init(argc, argv);
typephp_<project>_runtime_shutdown();
```

这些是 binary/library embed runtime 与当前项目 module entry 的连接点。定义和引用统一通过
`TYPEPHP_EMBED_GET_MODULE_FUNCTION()`、`TYPEPHP_RUNTIME_INIT_FUNCTION()`、
`TYPEPHP_RUNTIME_SHUTDOWN_FUNCTION()` 及对应的符号宏生成，风格与 Zend 的
`PHP_MINIT_FUNCTION()`/`PHP_MINIT()` 一致。最终符号包含项目名，不得作为通用 helper 命名模板。

### 5.4 多扩展进程中的公共运行时

TypePHP 扩展不得分别编译或静态链接包含进程级 Zend 状态的 PHPX 实现。Reflection handler、
`FiberGenerator` class entry、作用域和 Property Hook 运行时均由共享的 `libphpx` 唯一提供：

- host 模式的 extension/library 必须链接 `libphpx.so`、`libphpx.dylib` 或 `phpx.dll`，不能回退到 `libphpx.a`；
- Unix PHP extension 不链接 Embed `libphp.so`，Zend/PHP 符号由加载它的 SAPI 提供；
- macOS extension 使用 `-undefined dynamic_lookup` 解析宿主符号；
- binary 和独立 WASI 程序仍可以静态链接，因为每个进程或 Wasm 实例只有一份运行时。

`src/core/typephp_*.cc` 只承载 TypePHP 专属的 `typephp_*` 运行时；`php::` ZendAPI 包装应放在不带
`typephp_` 前缀的 core 源文件中，例如 `src/core/scope.cc`。

## 6. 名称选择流程

新增 C++ API 时按以下顺序判断：

1. **它是否是用户 PHP 函数或类方法的编译本体？**
   - 是：使用既有 `php_` callable ABI 生成器，禁止手写另一套映射。
2. **它是否只服务于当前一个 TypePHP 项目？**
   - 是：放入 `typephp_project_<project>`，并尽可能使用 `static` 或私有 accessor。
3. **它是否实现 TypePHP 独有语义？**
   - 是：使用 `typephp_` 前缀。
4. **它是否只是对 Zend/PHP 运行时能力的 C++ 封装？**
   - 是：放入 `namespace php`，使用 PHPX camelCase 风格。
5. **以上都不是？**
   - 不应随意加入 `typephp_helper.h`；应重新确认所属模块和公共 API 边界。

## 7. 代码审查清单

新增或修改生成 helper 时必须检查：

- [ ] `typephp_helper.h` 中没有新增全局 `php_*` helper；
- [ ] ZendAPI 包装位于 `namespace php`；
- [ ] TypePHP 独有逻辑使用 `typephp_`；
- [ ] 项目缓存和 storage 位于 `typephp_project_<project>`；
- [ ] 项目私有表没有通过生成头文件直接 `extern` 暴露；
- [ ] 用户 callable 仍使用统一的 `php_` ABI 生成器；
- [ ] 新名称不会与用户可声明的 PHP 函数或方法发生冲突；
- [ ] bin、lib、ext 和 WASM 构建使用相同的项目名推导规则；
- [ ] 修改公开 callable 映射时同步评估 stub 和既有 ABI；
- [ ] 至少增加一个用户同名函数的编译回归测试。

当前相关回归测试为：

```text
tests/compiler/basic/helper-symbol-collision.phpt
```

## 8. 主要实现位置

| 责任 | 文件 |
| --- | --- |
| `php_` callable 前缀与组合分隔符 | `src/CompilerBase.php` |
| callable 组合冲突检测 | `src/Preprocessor.php` |
| `typephp_project_<project>` 生成及项目私有表 | `src/Translator.php` |
| TypePHP extension 前缀常量 | `src/Metadata/Constants.php` |
| PHPX/TypePHP helper 分类 | `vendor/swoole/phpx/include/typephp_helper.h` |
| embed module accessor 拼接 | `vendor/swoole/phpx/src/misc/typephp_main.cc` |

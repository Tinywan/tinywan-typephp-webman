# TypePHP 与 Python 语言级互调用设计

> 状态：核心设计已确认，按 `python/implementation-plan.md` 分阶段实施。
>
> 本文是语法、类型语义、运行时边界和兼容性目标的设计规范；尚未确认的细节继续在文末维护。

## 1. 目标

TypePHP 应在语言层面提供从 TypePHP 调用 Python 包的能力：

1. TypePHP 导入 Python 模块，访问模块成员，调用 Python 函数和类。
2. TypePHP 操作 Python 对象，包括属性、方法、下标、迭代、运算符和调用协议。
3. TypePHP 函数、闭包和对象可以作为 Python 调用的参数，并允许 Python 在该次动态调用关系中同步回调。
4. 两个 VM 在同一进程内直接互调用，不通过 JSON、RPC 或子进程。
5. 默认保留 Python 对象身份和类型信息，避免不必要的深拷贝。
6. 语法面向普通 TypePHP/PHP 开发者，常规调用不要求理解 CPython C API、GIL 或引用计数。
7. 本功能是可选的扩展级能力；不使用 Python 语法的项目不依赖 phpy。

其中最主要的语言变化是 Python 特殊根命名空间。全局命名空间中的 `python\module\member()`，或其他命名空间中的 `\python\module\member()`，可以直接访问模块成员；`use python\module` 完全按照 PHP 的普通 namespace alias 规则工作，编译器不对 `use` 语句进行 Python 特殊处理。两种形式都把 phpy 原本需要手写的 `PyCore::import('module')` 和返回变量提升为编译期可识别的 lazy module binding。Python 对象的属性、方法、下标、迭代、参数转换、返回包装和异常等能力原则上复用 phpy 已有实现，不在 TypePHP 中重新建立一套运行时。

非目标：

- 不编译 Python 源码，也不试图替代 CPython。
- 不承诺将动态 Python API 静态类型化。
- 永久不支持 Python 线程、`asyncio` 或 CPython subinterpreter。
- 不生成 Python extension，不向 Python 注册 TypePHP 函数、类或模块。
- 不提供 `#[PythonExport]` 或其他 TypePHP 符号导出机制。
- 不追求兼容 Python 语法；目标是让 TypePHP 程序方便、可靠地调用 Python 包。
- 不将任意 Python 容器自动、递归地复制为 TypePHP 数组。

## 2. 参考设计

### 2.1 Mojo

Mojo 使用未经修改的 CPython 运行时保证 Python 生态兼容性，并用统一的 `PythonObject` 包装动态 Python 值。TypePHP 只借鉴其嵌入和对象包装设计，不采用其导出机制。

可借鉴的部分：

- Python 值默认保持为包装对象。
- TypePHP 基础值传入 Python 时可自动转换。
- Python 值转回 TypePHP 原生类型时显式转换。
- 动态 Python 值使用统一代理类型承载。

参考：[Mojo Python interoperability](https://docs.modular.com/stable/mojo/manual/python/)、[Mojo Python types](https://docs.modular.com/mojo/manual/python/types)。

### 2.2 pybind11

pybind11 明确区分对象所有权、返回值策略、解释器生命周期、GIL guard、位置参数和关键字参数。其经验说明：跨语言调用最危险的部分不是调用语法，而是对象生命周期和异常路径。

TypePHP 不应把 pybind11 的所有权策略暴露给普通用户，但运行时必须建立同等严格的内部契约。

参考：[pybind11 embedding](https://pybind11.readthedocs.io/en/stable/advanced/embedding.html)、[pybind11 functions](https://pybind11.readthedocs.io/en/stable/advanced/functions.html)。

### 2.3 PyO3

PyO3 使用 GIL token 和带生命周期的 Python 对象指针，从类型系统上区分持有对象、借用对象和 GIL 绑定对象。

TypePHP 无需向用户暴露生命周期参数，但 phpy 的 C++ 层应借鉴这一点：所有 CPython API 调用必须能证明当前持有 GIL，所有 `PyObject*` 必须明确是 owned、borrowed 还是 stolen reference。

参考：[PyO3 object model](https://pyo3.rs/main/doc/pyo3/)、[PyO3 Python object types](https://pyo3.rs/main/types)。

## 3. phpy 的定位

phpy 是本功能的运行时基础候选，而不是已经验证完成的稳定依赖。

可复用能力包括：

- 在 ZendVM 进程内初始化 CPython。
- `zval` 与 `PyObject*` 的边界转换。
- Python 模块、对象、字符串、序列、字典、集合、迭代器和 callable 的代理对象。
- TypePHP/PHP 闭包传入 Python后的 callable 代理。
- Python 异常到 Zend 异常的基础映射。
- Python 同步调用由 ZendVM 主动传入的函数、对象和 callable 代理的基础设施。
- GIL RAII guard 的雏形。

但是不能直接假定现有实现完全正确。后续实施必须同时 review phpy、重构边界、增加测试、修复 BUG 和优化性能。

设计阶段已经识别出的重点审计项：

- CPython 初始化、重复初始化、关闭顺序和仍存活对象的析构。
- 每个 CPython API 的 owned/borrowed/stolen reference 规则。
- 所有成功路径和异常路径的 `Py_INCREF/Py_DECREF` 对称性。
- GIL 获取、重入调用和 TypePHP 回调 Python 再回调 TypePHP 的行为。
- 转换过程已改为每次顶层转换创建独立的 C++ 转换器对象；转换策略、递归栈和深度限制均为对象内状态，并由 RAII 恢复，不再使用进程级或线程级临时函数指针。仍需继续审计跨 VM 回调和生命周期边界。
- Python 异常转 Zend 异常后，CPython error indicator 是否始终被正确清理。
- Zend 异常转 Python 异常时，原始异常类型、消息和 traceback 的保存。
- 运算符协议是否正确。例如 PHP `/` 不能映射为 Python floor division。
- Python 大整数、无效 UTF-8、包含 NUL 的 bytes、递归容器和循环引用。
- Python 代理持有 Zend 对象时，Zend GC 与 CPython GC 之间可能形成的跨 VM 引用环。
- Python 线程、`asyncio`、subinterpreter 必须被永久、显式拒绝，而不是产生未定义行为。

TypePHP 通过 ZendVM 动态调用 phpy 扩展公开的 `PyCore`、`PyObject`、`PyDict` 等 Facade，不直接链接 `libphpy.so`，也不生成任何 phpy C++ 符号引用。现有公开名称必须保留，TypePHP 不建立第二套用户可见命名体系。

职责边界：

- phpy 负责所有运行时问题：CPython 初始化、GIL、引用计数、对象代理、类型转换、异常和双 VM 生命周期。
- phpy 负责提供稳定、可测试的 Zend internal class/function/object-handler API。
- TypePHP 只负责识别语言语法、静态类型和求值顺序，并生成基于 `zend_function*` 与 PHPX/Zend 通用对象 API 的动态调用。
- TypePHP 不直接操作裸 `PyObject*`，不复制 phpy 的 GIL、引用计数或异常实现。
- 修复运行时 BUG 时优先修复 phpy，不能只在 TypePHP 生成代码中增加补丁绕过。

最小适配原则：

- TypePHP 的核心新增能力是 Python `use` 解析、模块别名符号和对应代码生成。
- `python\name()`、`module\name`、`module\name()` 和运算符 lowering 都应落到 phpy 的 Zend Facade；Python 运算符通过标准库 `operator` module 调用完整的 CPython 运算协议。
- phpy 已正确解决的行为只补测试并复用；只有 review 或测试证明存在 BUG、隐式转换不符合 TypePHP 规则，或者缺少 Zend 动态入口时，才修改 phpy。
- TypePHP 不实现 CPython 协议细节，不在生成代码中复制 `PyCore`、`PyObject` 或 `PyModule` 的逻辑。

## 4. 可选扩展与运行时检测

Python 互调用是扩展级特性，不是 TypePHP 核心程序的强制依赖。

- TypePHP 生成代码只依赖 ZendVM/PHPX，不 include phpy 头文件，也不链接 `libphpy.so`。
- 编译器识别 Python 语法并保留逻辑上的 `PyObject` 类型信息，但不检查 phpy SDK、动态库、ABI 或 Python module 是否存在。
- phpy 必须像普通 PHP 扩展一样由运行环境加载并注册 `PyCore`、`PyObject` 等 Zend internal classes。
- 首次实际使用 Python 符号时，TypePHP 通过 class map/func map 解析 `PyCore` 和对应的 `zend_function*`。
- phpy 未加载时，Zend class lookup 抛出可捕获的 PHP `Error`；若未捕获，则按普通 PHP 规则成为 fatal error。
- phpy 已加载但 Python module 不存在时，`PyCore::import()` 通过 phpy 抛出 `PyError`。
- 只有 `use python\sys` 而没有实际访问任何 Python 符号时，不发生运行时解析，因此即使没有安装 phpy 也不会报错。

这种模型使同一个 TypePHP 二进制可以在未安装 phpy 的环境中运行不涉及 Python 的路径，也避免 TypePHP 与 phpy 建立原生 C++ ABI 依赖。

### 4.1 TypePHP 代码隔离

TypePHP 中所有 Python 专用实现必须集中到独立子目录，暂定为：

```text
src/Python/
```

该目录负责：

- `python` 特殊根命名空间识别。
- import/module symbol 表。
- Python Zend class/method 名称和逻辑返回类型映射。
- Python 语法糖和静态返回类型映射。
- Python 调用、属性、下标、迭代和运算符的 C++ lowering。
- Python 专用诊断。

通用 Parser、TypeSystem、Optimizer 和 Generator 只允许保留最小、稳定的扩展入口，不应散落 `if ($isPython...)` 特判。Python 功能未启用时，不加载 Python 专用分析器，也不改变现有代码生成路径。

测试同样独立组织，建议使用：

```text
phpunit/src/Python/
phpunit/code/python/
tests/compiler/python/
```

具体目录名在 coding 计划阶段确认，但“实现与测试隔离”是设计约束。

## 5. 总体运行时模型

采用以下模型：

- 一个进程内同时存在一个 ZendVM 和一个 CPython 主解释器。
- CPython 完全通过 phpy 已有的扩展生命周期初始化和关闭；TypePHP 不建立第二套初始化路径。
- 所有 Python API 边界自动获取 GIL，普通用户不操作 GIL。
- `PyObject` 及其 `PyDict`、`PyList`、`PyStr` 等子类持有 CPython strong reference。
- Python 代理对象复制时增加引用计数，析构时在合法的解释器/GIL 上下文中减少引用计数。
- borrowed reference 只允许存在于 phpy 内部的短生命周期作用域，不暴露给 TypePHP。
- TypePHP 调用 Python、Python 同步回调由 TypePHP 作为参数传入的 callable、该 callable 再调用 Python，必须支持同步重入。
- Python 不能独立导入 TypePHP 应用，也不能通过全局注册表查找 TypePHP 函数或类型。

解释器关闭前必须先释放所有由 TypePHP 持有的 Python 对象。不能依赖 `Py_Finalize()` 自动修复错误的生命周期。

## 6. 模块名称与导入语法

`python` 是编译器识别的保留根命名空间：

```php
python\math\sqrt(16);
python\os\path\join('/tmp', 'file.txt');

use python\sys;
use Python\numpy as np;
use python\numpy\linalg as linalg;
```

完整名称不要求先写 `use`：

```php
$root = python\math\sqrt(16);
$pi = Python\math\pi;
```

最后一个 `\` 之前、Python 根之后的所有片段均构成 Python module path，最后一个片段是模块成员。PHP 的 `\` 在导入时转换为 Python 的 `.`。因此全局命名空间中的 `python\os\path\join()` 明确表示 module `os.path` 的 `join` callable。

Python module 名称仍严格服从 PHP 的 namespace 解析规则。位于普通 PHP namespace 内时，完整模块名必须使用前导 `\`：

```php
namespace App;

\python\math\sqrt(16); // Python module math
python\math\sqrt(16);  // 普通 PHP 名称 App\python\math\sqrt，不是 Python module
```

这是 PHP 语法的一部分，`python` 不作为例外绕过当前 namespace。`use python\math;` 与其他 PHP `use` 声明一样从根名称导入，因此在 namespace 内也可以使用 alias 简写。

`use` 仅用于缩短完整名称，不是访问 Python module 的前置条件：

PHP 的 `use function` 和 `use const` 同样适用，并支持普通的 `as` alias：

```php
use function python\len;
use function python\math\sqrt as py_sqrt;
use const python\math\pi as py_pi;

$length = len([1, 2, 3]);
$root = py_sqrt(16);
$pi = py_pi;
```

这些声明仍完全由 PHP 名称解析处理。TypePHP 只在 `FuncCall` 或 `ConstFetch` 的最终完整名称位于根命名空间 `python\...` 时进入 Python lowering；`use` 声明本身不会导入 Python module。

分别等价于：

```python
import sys
import numpy as np
import numpy.linalg as linalg
```

在现有 phpy PHP API 中，语义上对应：

```php
$sys = PyCore::import('sys');
$np = PyCore::import('numpy');
$linalg = PyCore::import('numpy.linalg');
```

`PyCore::import()` 返回一个 `PyModule`/`PyObject` 变量，后续属性和方法均通过该变量访问。`use python\module` 只是普通 PHP namespace alias，不立即执行导入，也不生成 ZendVM class、namespace 或用户可见变量。编译器仅在处理函数调用或常量读取时检查 PHP 已解析的完整名称。

当编译器在函数代码中发现 `module\attr` 或 `module\func()` 时，采用与现有 `funcMap` 相同的编译器结构：为实际使用的完整 module 名称分配整数 ID，生成统一的 `THREAD_LOCAL` zval array，并通过 lazy getter 动态调用 `PyCore::import()`。下列名称只是设计示意：

```cpp
THREAD_LOCAL zval php_python_module_map[module_count];

php::Object php_get_python_module(int module_id, const php::Str &module_name)
{
    zval *module = &php_python_module_map[module_id];
    if (UNEXPECTED(Z_ISUNDEF_P(module))) {
        // Resolve PyCore::import through classMap/funcMap and invoke zend_function*.
        php::Variant value = php::call(/* cached zend_function* */, php::ArgList{module_name});
        ZVAL_COPY(module, value.ptr());
    }
    return php::Object(module);
}
```

对应 lowering：

```text
use Python\numpy as np
    -> compile-time namespace marker: np => "numpy"
    -> module id allocated only when np is actually referenced

np\version
    -> php::Object(php_get_python_module(module_id, "numpy")).attr("version")

np\array($value)
    -> php::Object(php_get_python_module(module_id, "numpy")).call("array", converted($value))

python\numpy\array($value)
    -> the same module id and lowering as np\array($value)

python\os\path\join($left, $right)
    -> php::Object(php_get_python_module(module_id, "os.path")).call("join", ...)
```

同一完整 module 名称在整个 TypePHP 构建中只分配一个 ID；完整名称和任意 `use` 别名引用同一 module 时也共享该 ID。如果当前 `.php` 文件只有 `use python\sys`，但没有出现任何 `sys\attr`、`sys\func()` 或其他 `sys` 符号访问，则编译器不为它分配 module ID，运行时不调用 `import('sys')`，也不会因为 Python 环境缺少该 module 而报错。

未使用 module 不触发任何 phpy 运行时解析。`tpc` 只检查 `use python\sys` 本身的语法和别名冲突，不检查 phpy SDK/ABI，也不增加 phpy 链接依赖。

### 6.1 与 `funcMap` 的关系

`pythonModuleMap` 复用 `funcMap` 已验证的整体模式：

- 编译期使用 `完整 module 名称 → integer ID` 的 map 去重。
- 数据声明集中生成，普通 `.cc` 只引用 extern array 和 getter。
- getter 首次访问时初始化，后续通过数组直接命中。
- 只为真正出现成员访问或调用的 module 分配 ID。
- 在应用/request clean 阶段集中清理。

但是两者不能机械地使用完全相同的清理代码：

- `funcMap` 保存由 Zend function table 拥有的 non-owning `zend_function*`，清理时可以直接 `memset`。
- `pythonModuleMap` 保存 phpy 返回的 Zend `PyModule` object zval，不能直接 `memset` 覆盖有效对象。
- request clean 必须逐项执行 `zval_ptr_dtor()` 并恢复为 `UNDEF`，让 phpy 自己的 Zend object destructor 处理 Python reference 和 GIL。
- import 失败时 slot 保持 `UNDEF`，不能缓存异常值或半初始化对象。

清理由 TypePHP 使用普通 Zend zval API 完成，不调用 phpy C++ 符号：

```cpp
for (zval &module : php_python_module_map) {
    if (!Z_ISUNDEF(module)) {
        zval_ptr_dtor(&module);
        ZVAL_UNDEF(&module);
    }
}
```

TypePHP 只释放 Zend object；其内部 Python 引用计数、GIL 和 error state 仍由 phpy object handler 负责。

### 6.2 `sys.modules` 仍是全局事实来源

Python import 本身就是全局的。getter 首次调用底层 import 时，CPython 从 `sys.modules` 返回已加载 module 或执行首次加载。`pythonModuleMap` 不是第二套 import 系统，只相当于 Python 文件执行 `import numpy as np` 后保存在该文件 namespace 中的绑定：

```text
php_get_python_module(id, "numpy")
    -> TypePHP request 内的 PyModule zval binding
    -> CPython sys.modules（全局 module identity 与加载状态）
```

它避免每次函数调用都重复进入 Python import API，同时不承担包查找、加载或 reload 逻辑。即使同一个 module 被多个 TypePHP 文件以不同别名引用，只要完整 module 名称相同，就使用同一个 ID 和 `PyModule` Zend object zval。

该绑定与 Python 普通 import 一致：Python 代码之后删除或替换 `sys.modules['numpy']`，不会自动改变已经完成的 `np` 绑定；显式执行 `PyCore::import('numpy')` 则按调用当时的 `sys.modules` 状态处理。

规则：

- `python` 根命名空间的大小写不敏感，`python`、`Python`、`PYTHON` 均识别为同一个语言符号。
- 只有根命名空间不区分大小写。后续模块路径、成员、方法和关键字参数名称严格区分大小写。
- 全局 namespace 中的 `python\package\module\member`，以及其他 namespace 中的 `\python\package\module\member`，是完整 module 访问，不需要 `use`，并按首次实际执行进行 lazy import。
- namespace 内没有前导 `\` 的 `python\...` 是相对 PHP 名称，必须按 PHP 规则加上当前 namespace，不能识别为 Python module。
- `use python\...` 只能导入 Python 模块。
- 是否存在该模块只能在运行时由 CPython 判断。
- 不支持 `from package import *`。
- 初版不设计单独的 `from package import name` 语法，成员统一通过模块别名访问。
- 根命名空间 `\python` 保留给语言互调用；例如 `App\python` 仍是普通 PHP namespace。
- 模块别名不能与当前文件中的 TypePHP 类、命名空间导入或其他 Python 模块别名冲突。
- 用户仍可直接调用 `PyCore::import()` 并把返回的 `PyModule` 保存到普通变量；完整名称和经 PHP 普通 `use` 解析后的名称都使用 `pythonModuleMap` lazy binding。

示例：

```php
python\len($value); // 正确
Python\len($value); // 正确，根命名空间大小写不同
python\Len($value); // 错误，Python builtin 名称大小写错误
Python\Len($value); // 错误
```

解析结果位于根命名空间 `\python` 时，它由 TypePHP 编译器转换为 Python 语言符号；解析为 `App\python` 等其他名称时，仍进行普通 PHP 函数或类查找。

## 7. 模块成员

Python module 在 TypePHP 中表现为 namespace，而不是 class。模块中的名称仍由 Python VM 作为属性动态解析。

### 7.1 包变量

读取 Python 包变量使用 PHP namespace constant 的语法形式 `module\name`：

```php
use python\math;
use python\os;
use python\numpy as np;

$pi = math\pi;
$environ = os\environ;
$arrayType = np\ndarray;
$directPi = python\math\pi;
$text = math\pi->__str__();
```

这里使用的是 PHP 合法的 namespace constant 表达式，但 TypePHP 不会把它注册为 Zend constant，也不会进行常量折叠。编译器将每次读取 lowering 为 Python module attribute lookup，结果保持为 `PyObject`，因此可以继续调用对象方法。

不允许使用 `math::pi` 或 `math::$pi` 读取包变量；两者都是 class member 语法，会错误地把 module 表达为 class。编译器发现这类旧语法时给出有针对性的 FatalError，并提示改用 `math\pi`。

### 7.2 包函数和类构造

调用 Python 包中的 callable 使用 PHP namespace function 语法 `module\name(...)`：

```php
$a = np\array([1, 2, 3]);
$b = np\array([4, 5, 6]);
$c = np\add($a, $b);
$root = python\math\sqrt(16);
$joined = python\os\path\join('/tmp', 'file.txt');
```

编译器读取 module 的 `name` 属性，并调用得到的 Python 对象。该对象可以是：

- Python 函数。
- Python class，此时调用执行该类的构造过程并返回实例。
- 实现 `__call__` 的其他 Python 对象。

TypePHP 不需要也不能仅根据 `np\array()` 的语法判断它是函数还是类构造；可调用性由 Python 在运行时判断。成员不存在时产生 Python `AttributeError`，成员不可调用时产生 Python `TypeError`，并统一映射为 `PyError`。

初版只支持读取模块属性。PHP namespace constant 语法本身不能作为赋值目标；需要写入时应通过 Python 对象 API 显式完成：

```php
$os = PyCore::import('os');
python\setattr($os, 'name', $value);
```

## 8. Python 内置函数与 phpy 语法糖

`python\name()` 表示调用 Python builtins：

```php
python\print('hello'); // 等价于 PyCore::print('hello')
$length = python\len($value)->toValue()->toInt();
$range = python\range(0, 10);
$type = python\type($value);
```

它不是普通 TypePHP 命名空间函数。编译器使用 class/func map 解析 `PyCore` 对应的 `zend_function*` 并动态调用，运行时语义与直接编写对应 `PyCore` 调用一致。

名称严格区分大小写。对于编译器内建映射中已知的错误名称，可以在编译期报错；其他动态 builtin lookup 失败时产生 Python `AttributeError`。

一部分名称是现有 phpy 类型构造器的语法糖，而不是直接调用同名 Python builtin：

| TypePHP 语法 | 等价 phpy API |
|---|---|
| `python\dict($array)` | `new PyDict($array)` |
| `python\list($array)` | `new PyList($array)` |
| `python\tuple($array)` | `new PyTuple($array)` |
| `python\set($array)` | `new PySet($array)` |
| `python\str($value)` | `new PyStr($value)` |
| `python\object($value)` | `new PyObject($value)` |
| `python\print(...)` | `PyCore::print(...)` |
| `python\scalar($value)` | `PyCore::scalar($value)` |

例如：

```php
$dict1 = new PyDict([1, 2, 3, 4]);
$dict2 = python\dict([1, 2, 3, 4]);
```

二者必须具有完全相同的运行时语义。这里不能简单转发 CPython `dict([1, 2, 3, 4])`，因为原生 Python builtin 会把参数解释为 key/value pair iterable，与 `PyDict` 的 PHP array 构造规则不同。

所有语法糖的映射必须形成封闭、经过测试的表，不能仅凭函数名猜测。

该映射同时决定编译期静态类型：

```php
$list1 = new PyList();
$list2 = python\list();

$dict1 = new PyDict();
$dict2 = python\dict();
```

- `$list1` 与 `$list2` 都是 `PyList` typed object。
- `$dict1` 与 `$dict2` 都是 `PyDict` typed object。
- 两种写法必须使用相同的类型检查、方法解析和 Native Call 优化。
- 语法糖不能退化成 `mixed`、`var` 或只有基础类型 `PyObject`。
- Python builtin 调用同样遵守对象保持规则，例如 `python\len()` 返回包装 Python int 的 `PyObject`；需要先以 `toValue()`（或函数入口 `python\scalar()`）离开 Python 对象规则，再使用普通 TypePHP 转换得到确定类型。`python\print()` 的 Python `None` 结果也保持为 `PyObject`，作为独立语句使用时可直接丢弃。
- `PyObject::toValue()` 和 `python\scalar()` 都不是普通 Python builtin 调用，而是明确要求退出 Python 类型规则的转换边界，因此返回 TypePHP `var`。
- 动态 Python module 成员调用统一返回 `PyObject`。

## 9. Python 对象类型

所有无法在编译期确定静态类型的 Python 值统一表示为：

```php
PyObject
```

`PyObject` 是现有 phpy 的公开类型，也是 TypePHP 的正式运行时类型。不会再引入 `python\Object` 或 `python\Any`。

Python 内建类型继续使用 phpy 已有的具体代理类，例如 `PyDict`、`PyList`、`PyTuple`、`PySet`、`PyStr`、`PyType`、`PyFn` 和 `PyIter`。这样普通 PHP 与 TypePHP 用户看到的是同一套类型体系。

Python 的 `None` 也是一个合法 Python 对象。它与 TypePHP `null` 的自动转换规则需要单独定义，不能通过空指针表示 Python `None`。

## 10. 对象操作

### 10.1 属性和方法

```php
$env = os\environ;
$items = $env->items();
$name = $object->name;
$object->name = 'new value';
unset($object->name);
```

分别映射为 Python 的 `getattr`、call、`setattr` 和 `delattr` 协议。

`PyObject` 明确提供 `toValue()` 和 `toArray()` 两个 PHP Facade 方法。`toValue()` 等价于 `PyCore::scalar()` / `python\scalar()`，把 Python 值递归转换为 PHP 内置值。其返回值再使用普通 TypePHP 转换方法确定类型：

```php
$pyValue = np\int64(42); // PyObject
$value = $pyValue->toValue()->toInt(); // TypePHP int
```

这里的 `toInt()` 作用于 `toValue()` 已返回的 TypePHP 值，并非作用于 `PyObject`。

`toArray()` 仅转换 Python `list`、`tuple`、`set`、`dict` 以及 iterator。容器元素递归转换为 PHP 值；iterator 会被消费，后续再次转换只能得到其剩余元素。不支持转换的 Python 类型返回空数组。`toArray()` 同时是 TypePHP 关键词方法，但 PHPX 的对象转换路径会调用 `PyObject::toArray()`；`toString()` 则继续通过关键词方法调用 `PyObject::__toString()`，phpy 不重复声明 `toString()`。

### 10.2 下标

```php
$value = $object[$key];
$object[$key] = $value;
unset($object[$key]);
isset($object[$key]);
```

分别映射到 Python mapping/sequence protocol。

`isset()` 保持 PHP 的空值语义：键或索引不存在时返回 `false`，对应值为 Python `None` 时也返回 `false`。运行时只把 `KeyError` / `IndexError` 识别为“缺失”；Python protocol 抛出的其他异常必须继续映射为 `PyError`，不得被 `isset()` 吞掉。list 和 tuple 的整数下标遵循 Python 负索引规则。

### 10.3 调用对象

```php
$result = $callable($arg1, $arg2);
```

运行时使用 `PyObject_Call`。不可调用对象产生 Python `TypeError`，并映射为 TypePHP 可捕获的 Python 异常。

### 10.4 迭代

```php
foreach ($pythonIterable as $value) {
    // Python __iter__ / __next__
}
```

带 key 的形式：

```php
foreach ($pythonIterable as $index => $value) {
}
```

通用 Python iterator 使用从 `0` 开始的 TypePHP 迭代序号作为 `$index`，`$value` 是 `__next__()` 产出的对象。`PyDict` 是 phpy 的专用 mapping wrapper，带 key 的 `foreach` 使用 PHP mapping 习惯：`$index` 是 dict key，`$value` 是对应 dict value。`__iter__()` / `__next__()` 的 Python 异常必须传播为 `PyError`，不能当作正常迭代结束。

## 11. 参数与关键字参数

普通参数按从左到右顺序求值，然后构造 Python positional args：

```php
$model = AutoModel\from_pretrained(
    'model-name',
    trust_remote_code: true,
    device_map: 'auto',
);
```

TypePHP 命名参数映射为 Python keyword arguments。参数名严格区分大小写。

PHP/TypePHP 数组展开规则可用于构造位置参数和关键字参数，但必须满足：

- 整数 key 生成 positional argument。
- 字符串 key 生成 keyword argument。
- positional argument 不能出现在 keyword argument 之后。
- 重复 keyword 产生 Python `TypeError`。

是否增加显式的 `python\args()` / `python\kwargs()` 类型，留待后续讨论；初版尽量复用现有调用和数组展开语法。

## 12. 显式转换原则

TypePHP 不继承 phpy 在 ZendVM Facade/opcode 层面的返回值隐式转换行为。语言层采用“参数进入 Python 边界时自动转换、Python 返回值保持对象、返回 TypePHP 时显式转换”的原则。

允许自动转换的场景必须由语法明确指出正在进入 Python：

- `python\name(...)`。
- Python module 调用，例如 `np\array(...)`。
- `PyObject` 的方法或 callable 调用。
- 显式 Python 容器构造，例如 `new PyList(...)` 或 `python\list(...)`。
- 参数声明要求 `PyObject`、`PyDict` 等 phpy 类型。
- `PyObject` 与 TypePHP 值组成的混合运算表达式。

在这些调用边界内，所有参数表达式先严格按照 TypePHP 从左到右的顺序求值，再转换为 Python 能接受的对象。TypePHP 标量转换为对应 Python scalar；TypePHP 数组递归转换为 Python list/dict，这一过程会产生深拷贝。这不应扩散为不含 Python 对象的普通 TypePHP 表达式中的全局隐式转换。

“所有参数自动转换”只适用于转换表明确支持的 TypePHP 类型；resource 或其他没有 Python 表示形式的值必须抛出清晰的类型错误，不能静默转换或传递无效指针。

以下场景不允许隐式转换：

- 将 `PyObject` 直接赋给 `int`、`float`、`bool`、`string` 或 `array`。
- 将 Python 容器隐式深拷贝成 TypePHP array。
- 因算术、比较或字符串上下文而擅自把 Python 对象变成 TypePHP 标量。
- 根据运行时 Python 类型改变 TypePHP 变量的静态类型。

`echo $pyObject` 可继续兼容现有 `PyObject::__toString()`，但这只属于输出协议，不能被编译器当作一般的字符串隐式转换。

## 13. TypePHP 到 Python 的转换

Python 调用边界允许以下自动转换：

| TypePHP | Python | 语义 |
|---|---|---|
| `null` | `None` | 单例，不是空 `PyObject*` |
| `bool` | `bool` | 值转换 |
| `int` | `int` | Python 任意精度整数 |
| `float` | `float` | double |
| `string` | `str` | 要求合法 UTF-8 |
| list array | `list` | 递归复制 |
| map array | `dict` | 递归复制 |
| `PyObject` 及其子类 | 原对象 | 零拷贝，只传递引用 |
| TypePHP callable | Python callable proxy | Python 可同步回调 TypePHP |
| TypePHP object | Zend object proxy | 不自动复制对象属性 |

PHP array 使用 `zend_array_is_list()` 一类规则决定转换为 Python `list` 还是 `dict`。空数组默认转换为 Python `list`；如需空 dict，必须提供显式构造 API。

数组和普通 TypePHP 字符串每次进入 Python 边界都可能产生分配与复制。文档和性能诊断应建议高频调用、循环调用或大数据场景尽早构造并复用 `PyDict`、`PyList`、`PyStr` 等原生 Python 代理类型，避免重复深拷贝。`PyObject` 及其子类进入 Python 边界时只传递原对象引用，不做内容复制。

推荐写法：

```php
// 只转换一次，后续调用传递同一个 Python 对象。
use python\processor;

$pyItems = python\list($items);
for ($i = 0; $i < 1000; $i++) {
    processor\consume($pyItems);
}
```

应避免在循环中反复把同一个 TypePHP 容器作为参数传入，因为每次跨越 Python 调用边界都会重新深拷贝：

```php
for ($i = 0; $i < 1000; $i++) {
    processor\consume($items);
}
```

字符串与 bytes 必须区分。TypePHP `string` 默认映射到 Python `str`；二进制内容使用显式 `python\bytes()`。

递归数组、循环引用和超深嵌套必须检测并抛出异常，不能无限递归。

## 14. Python 到 TypePHP 的转换

### 14.1 默认规则

TypePHP 的 Python 专用调用路径必须关闭 phpy 的返回值隐式转换，所有 Python 函数、方法、构造调用和运算结果均保持为 phpy 对象。动态调用的静态返回类型统一为 `PyObject`，不能因为运行时结果恰好是 Python `bool`、`int`、`float`、`str`、`list` 或 `dict` 就隐式转换为 TypePHP 值。

当前实现由生成代码在首次实际执行 Python 表达式时，动态调用 `PyCore::setOptions(['return_as_object' => true])`。该初始化是请求级 lazy guard：只写 `use python\module` 而不访问 Python 符号不会触发 phpy；constructor-only 程序也会在构造前完成配置；request clean 会重置 TypePHP 自身的 guard。后续若 phpy 提供无全局模式的对象保持型独立入口，可在不改变语言语义的前提下替换这一运行时实现。

编译器已知的 phpy 构造语法糖仍保留精确子类，例如 `python\list()` 返回 `PyList`、`python\dict()` 返回 `PyDict`；这些类型本身都是 `PyObject` 子类，不构成返回值隐式转换。

phpy Zend Facade 应提供相互独立的“保持 Python 对象”和“显式转换为 TypePHP”入口。不能通过修改进程级全局函数指针或全局转换模式来临时切换，否则嵌套调用、同步重入和异常路径可能把错误策略泄漏给后续调用。TypePHP 生成的普通 Python 调用只动态调用对象保持入口；`PyObject::toValue()` 与 `python\scalar()` 最终都调用明确的标量转换入口。

phpy 内部已使用 `PythonToPhpConverter` 与 `PhpToPythonConverter` 实现这一约束。每次顶层转换拥有独立实例，递归子值复用同一实例；容器进入与退出由 RAII guard 管理，循环容器和超过深度限制的输入会抛出 `PyError`，不会污染后续转换或导致进程崩溃。

原因：

- 保留 Python 对象身份和精确类型。
- 避免容器返回时立即深拷贝。
- Python `int` 可能超过 TypePHP `int` 范围。
- Python 类型的子类可能重载协议，不能按基础容器强制展开。
- 避免 phpy 当前“部分标量自动转换、部分对象保留包装”的行为进入 TypePHP 静态类型系统。

### 14.2 显式转换

Python 对象只有通过 `toValue()`、`python\scalar()`（或手写等价的 `PyCore::scalar()`）才能进入 TypePHP 类型规则：

```php
$nativeValue1 = PyCore::scalar($value);
$nativeValue2 = python\scalar($value); // 完全等价的语法糖
$nativeValue3 = $value->toValue();
$integer = $value->toValue()->toInt();
$float = $value->toValue()->toFloat();
$boolean = $value->toValue()->toBool();
$string = $value->toValue()->toString();
$array = $value->toArray();
```

规则：

- `toValue()` 是 `PyObject` 的普通公开方法，不注册为 TypePHP 关键词方法；它在 phpy 内部复用与 `PyCore::scalar()` 相同的转换器。
- `toArray()` 保留 TypePHP 全局关键词方法语义。PHPX 对对象执行数组转换时优先调用其公开的 `toArray()`，因此会进入 phpy 实现。
- 显式转换完成后，结果完全进入 TypePHP 的静态类型、运算符和参数传递规则，不再采用 Python protocol。
- 容器转换属于显式深转换，并检测递归引用。
- Python 大整数不能静默溢出；现有转换规则需要 review 后再确定与 TypePHP `BigInt` 的精确映射。
- Python `str` 与 `bytes` 必须区分，不能都无条件转换为 TypePHP string。
- phpy 负责 `PyObject::toValue()` / `PyCore::scalar()` 的通用值转换，以及受限的 `PyObject::toArray()` 容器转换。`toInt/toFloat/toBool` 属于转换后的 PHP 值；`toString()` 仍由 TypePHP 关键词方法调用 `PyObject::__toString()`。

现有 phpy 的 PHP 用户仍可保留兼容行为；TypePHP 调用 phpy 的对象保持型 Zend API。为此可以重构或新增 phpy internal class method，但不增加 TypePHP 到 phpy 的 C++ 链接依赖。

## 15. 通过 Python `operator` module 实现运算符

对于 `PyObject` 及其子类：

- `+ - * / % ** << >> & | ^` 映射为 Python 标准库 `operator` module 的对应函数。
- `/` 映射 `operator.truediv()`，不能映射 `operator.floordiv()`。
- Python floor division 暂用 `python\floordiv($a, $b)`，因为 TypePHP 没有 `//` 运算符。
- `== != < <= > >=` 分别映射 `operator.eq/ne/lt/le/gt/ge()`。
- `===` / `!==` 分别映射 `operator.is_()` / `operator.is_not()`。
- `if ($object)`、`!$object` 使用 `operator.truth()`。
- compound assignment 映射 `operator.iadd/isub/...()`，并用返回对象更新左值。

基础映射：

| TypePHP | 生成的动态调用 |
|---|---|
| `$a + $b` | `operator\add($a, $b)` |
| `$a - $b` | `operator\sub($a, $b)` |
| `$a * $b` | `operator\mul($a, $b)` |
| `$a / $b` | `operator\truediv($a, $b)` |
| `$a % $b` | `operator\mod($a, $b)` |
| `$a ** $b` | `operator\pow($a, $b)` |
| `$a << $b` | `operator\lshift($a, $b)` |
| `$a >> $b` | `operator\rshift($a, $b)` |
| `$a & $b` | `operator\and_($a, $b)` |
| bitwise OR | `operator\or_($a, $b)` |
| `$a ^ $b` | `operator\xor($a, $b)` |
| `-$a` | `operator\neg($a)` |
| `+$a` | `operator\pos($a)` |
| `~$a` | `operator\invert($a)` |
| `$a += $b` | `$a = operator\iadd($a, $b)` |

所有操作数必须严格从左到右求值。

即使源码没有显式写出 `use python\operator`，出现 Python 运算符时，编译器也将其视为一个仅供内部 lowering 使用的隐式 module binding，并通过同一 `pythonModuleMap` 取得 `operator` module。它不向用户文件注入可见别名，因此不会与用户自己定义的 `operator` class 或 use alias 冲突。用户显式 `use python\operator` 时，内部 lowering 和用户访问复用同一个 module ID。

identity 比较调用 `operator\is_()` / `operator\is_not()`。即使两个对象的 `operator\eq()` 结果为真，只要不是同一个 Python object，`===` 仍为假。

允许 Python 对象与 TypePHP 值直接混合运算。只要当前运算节点的一侧静态类型为 `PyObject` 或其子类，另一侧的 TypePHP 表达式先完整地按 TypePHP 规则求值，再把所得值转换为 Python 对象，最后由 CPython 执行当前运算节点对应的 protocol。

例如：

```php
$result1 = $pyInt + 10;          // 10 转为 Python int，由 Python 执行加法
$result2 = $pyList * getCount(); // 先求值 getCount()，再转为 Python int
$native = $pyInt->toValue()->toInt() + 10; // 已显式转为 TypePHP int，使用 TypePHP 加法
```

`operator` 调用结果仍为 `PyObject`，以保留 Python 自定义运算符可能返回的任意对象。`===` / `!==` 和条件分支是例外：`operator.is_/is_not/truth()` 的 Python bool 结果随后通过显式 phpy 转换入口得到 TypePHP `bool`。两侧操作数必须严格从左到右各求值一次，转换过程不得导致表达式重复执行。

phpy 作为普通 PHP 扩展时，可以继续使用 Zend opcode handler 提供运算符重载兼容性；TypePHP 不依赖这些 handler。

动态 ZendVM 代码存在一个明确保留的限制：Zend 会把 `-$value` / `+$value` 编译为乘以 `-1` / `1`，phpy 的 opcode handler 无法再识别源码中的一元运算。因此动态代码保持 `$value * -1` / `$value * 1` 的协议行为，不通过全局 AST hook 改写普通 PHP 代码；自定义 Python 对象的 `__neg__()` / `__pos__()` 与 `__mul__()` 不一致时，结果可能不同。TypePHP AOT 仍按照上表生成 `operator.neg()` / `operator.pos()`。外部用户文档 `python.md` 已明确说明这一限制。

TypePHP 编译器在识别到静态类型为 `PyObject`、`PyDict` 等 phpy 对象时，把运算符改写为普通 Python module callable 调用：

```text
TypePHP operator
    -> compile-time lowering
    -> implicit python\operator module binding
    -> operator\add/sub/... dynamic call
    -> CPython complete operator protocol
```

该抽象不直接链接 phpy，也不经过 phpy 的 user opcode handler，但它不是无调用成本的 C++ inline 操作：

- `zend_function*` 和 class entry 使用现有 func/class map lazy cache。
- 参数仍需要构造为 Zend values，并由 phpy 转为 Python 对象。
- Python module member lookup、GIL、CPython call 和引用计数成本仍然存在。
- 优点是 TypePHP 二进制只依赖 ZendVM/PHPX，phpy 可以作为真正的可选运行时扩展。

使用标准库 `operator.add()` 而不是直接调用 `__add__()`，可以复用 CPython 对 `NotImplemented`、`__radd__()`、右操作数子类优先级等完整规则，TypePHP 不实现 reflected-operation fallback。

当前实现已经覆盖二元算术和位运算、比较、identity、一元运算、条件真假值、短路逻辑，以及 variable、属性和下标左值的复合赋值。Python module function/property、builtin、动态方法、属性、下标和 callable 的结果都会继续传播 `PyObject` 静态类型，因此可以直接链式访问或参与后续 Python 运算。

## 16. 异常

Python 调用失败时抛出统一的 TypePHP 异常类型，暂定：

```php
PyError
```

异常至少保留：

- Python exception type。
- message。
- Python traceback 对象。
- 格式化后的 traceback 字符串。
- 原始 Python exception instance。

示例：

```php
try {
    np\array('invalid')->reshape(2, 2);
} catch (PyError $error) {
    echo $error->pythonType();
    echo $error->pythonTraceback();
}
```

Python 同步调用 TypePHP callable 代理时，如果 TypePHP 抛出异常，应转换为普通 Python 异常，并保留原始 TypePHP 类名和消息。该异常只沿当前动态调用栈传播，不要求注册 `typephp` Python module 或专用的全局异常类型。

异常跨 VM 后必须清理源 VM 的 pending exception 状态。任何异常转换失败都不能导致 coredump、重复抛出或遗留错误状态。

## 17. TypePHP callable 传给 Python

TypePHP 函数、闭包和可调用对象可以自动包装为 Python callable：

```php
$values = python\list([1, 2, 3]);
$result = python\map(fn (int $value): int => $value * 2, $values);
```

Python 调用代理时：

1. Python 参数按边界规则转换或包装为 TypePHP 值。
2. 进入 ZendVM 调用 callable。
3. 返回值转换为 Python 值。
4. TypePHP 异常转换为 Python 异常。

闭包代理必须持有 Zend callable，防止 callable 在 Python 仍引用它时被释放。跨 VM 引用环必须由运行时显式检测或提供可预测的回收策略。

TypePHP callable 代理只是参数值，不是导出机制：只有 TypePHP 主动把代理传给 Python 后，Python 才能在该对象存活期间动态调用它。TypePHP 不生成可供 Python 独立导入的 module，也不注册全局函数或类。

## 18. phpy 生命周期与集成方式

TypePHP 复用 phpy 自己的 PHP 扩展入口和生命周期，不增加独立的 CPython bootstrap：

1. phpy 的 `MINIT` 初始化共享运行时、CPython 以及 `PyObject`、`PyDict` 等 Zend 类。
2. phpy 的 `RINIT` 建立本次请求需要的状态。
3. TypePHP 程序在请求期间通过 phpy 注册到 ZendVM 的 internal classes、methods 和 object handlers 动态调用 Python。
4. phpy 的 `RSHUTDOWN` 释放请求级资源和代理。
5. phpy 的 `MSHUTDOWN` 在所有代理均已安全释放后关闭共享运行时和 CPython。

TypePHP 应通过与其他静态或动态链接 PHP 扩展相同的机制执行这些入口，不能重复初始化 CPython，也不能绕过 phpy 生命周期直接调用 `Py_Initialize()` 或 `Py_Finalize()`。

唯一产物是以 TypePHP 为入口的主程序或库。不会生成可被 CPython 导入的 `.so` / `.pyd`，不会向 Python 注册 TypePHP module、函数或类，也不存在 `#[PythonExport]`。

## 19. 性能原则

- `PyObject` 传参只增加必要的引用计数，不复制 Python 对象。
- `pythonModuleMap` 只缓存已经绑定的 `PyModule` Zend object zval，真实加载和全局 identity 直接复用 CPython `sys.modules`；builtin/member lookup 初版保持简单，只有基准测试证明必要时才单独设计缓存。
- 参数应直接构造 vectorcall 所需数组，优先使用 CPython vectorcall API。
- 避免先构造 PHP 数组，再由 phpy 二次转换为 Python tuple/dict。
- TypePHP 数组到 Python 容器属于显式 O(n) 转换，不宣称零成本。
- 对进入热点 Python 调用的 TypePHP 数组和字符串，应提升为可复用的 `PyList`、`PyDict`、`PyStr`；编译器不擅自缓存转换结果，因为原 TypePHP 值可能已经改变。
- GIL guard 应覆盖最小必要区域；单线程同步重入期间必须保持正确的解释器状态。
- 异常路径与正常路径必须同等测试引用计数和内存泄漏。

## 20. 永久边界与不支持能力

- Python 线程，包括 `threading` 创建线程以及任何从非主线程进入 phpy/TypePHP bridge 的调用。
- `asyncio`、Python coroutine、`async`/`await` 及跨语言事件循环调度。
- CPython subinterpreter 和 per-interpreter GIL 模式。
- Python 作为入口独立加载 TypePHP 程序。
- 生成 Python extension 或将 TypePHP 函数、类、对象注册为可导入的 Python module。
- 运行时反射生成 TypePHP 静态类型。
- 自动导入 `from module import *`。
- pickle/serialize Python 对象。
- 跨进程传递 `PyObject`。
- WASM target 中的 Python 互调用。

禁止能力必须有明确防线：编译器对能够静态识别的 `threading`、`_thread`、`asyncio` 和 subinterpreter API 给出 FatalError；phpy 记录创建运行时的 owner thread，并拒绝从其他线程进入 ZendVM bridge。动态导入、反射或第三方包不能被编译器完整识别，因此运行时检查不能省略。

第三方 native package 内部完全封闭、从不进入 CPython API 或 phpy/ZendVM bridge 的计算线程不属于这里的 Python 线程能力；它们对 TypePHP 不可见，也不得产生跨线程回调。

## 21. TDD 与测试门禁

本项目的实现和重构必须严格遵循 TDD，顺序不可颠倒：

1. 根据已确认的设计语义编写测试。
2. 运行测试，确认它因为目标能力尚未实现或现有 BUG 而失败。
3. 编写使该测试通过的最小实现。
4. 运行相关测试和完整回归。
5. 在测试保护下重构、清理和优化。
6. 再次运行完整回归、内存检查和覆盖率检查。

禁止先完成实现，再补写只能验证当前实现细节的测试。每个 BUG 必须先添加能够稳定复现问题的回归测试。

### 21.1 三层强制测试

#### PHPUnit

TypePHP 仓库的 PHPUnit 用于验证编译器自身：

- Python import 和特殊名称解析。
- AST、符号表和类型推断。
- C++ 代码生成。
- 编译期错误和诊断位置。
- 永久禁用能力的编译期诊断，以及无 phpy 环境仍能成功生成代码。
- 不需要启动 CPython 的边界逻辑。

phpy 仓库现有 PHPUnit 用于验证 ZendVM/PHP Facade 与共享 Runtime：

- `PyCore`、`PyObject`、`PyDict` 等公开 PHP API。
- PHP 值与 Python 对象转换。
- Python 异常映射为 `PyError`。
- opcode handler 与 TypePHP 使用的 Zend dynamic-call API 具有一致语义。
- TypePHP 需要的对象保持型调用路径。
- GIL、引用计数、析构和异常路径。

#### PHPT

用于从 TypePHP 用户视角验证语言和运行时的端到端行为：

- 导入、`module\name` 包变量读取、`module\name()` callable 调用和关键字参数。
- `use python\module as alias` 与手写 `$alias = PyCore::import('module')` 的结果、异常和对象 identity 等价。
- 多个别名、嵌套模块和跨 `.cc` 重复导入。
- 只有 `use python\module` 而未访问任何相关符号时，不生成 helper、不调用 import，也不检查该 Python module 是否存在。
- 同一完整 module 名称跨函数、跨 `.cc` 只分配一个 ID，并只在首次访问时调用 import API。
- import 失败保持 map slot 为 `UNDEF`；异常被捕获后，下一次访问可以重新尝试。
- request clean 对 module zval 逐项执行 `zval_ptr_dtor()` 并恢复为 `UNDEF`，不得直接 `memset` 有效 Zend object。
- 删除或替换 `sys.modules` 条目不会改变已经缓存的 TypePHP module binding。
- `module::name` / `module::$name` 旧 class member 语法的编译期 FatalError，以及不存在成员和不可调用成员的运行时异常。
- 属性、下标、迭代、运算符和 truthiness。
- TypePHP 参数到 Python 的转换，以及 Python 返回值的显式转换。
- 空 TypePHP 数组默认转换为 Python list，以及数组递归深拷贝、异常中止和重复转换行为。
- Python builtin、模块函数、方法和运算结果不会隐式变成 TypePHP 标量。
- `$obj->toValue()->toInt()`、`$obj->toArray()`、`python\scalar($obj)->toInt()` 等显式边界及其后的普通 TypePHP 转换恢复静态类型和运算规则。
- Python 异常到 TypePHP 异常。
- phpy 未加载时首次 Python 调用抛出 PHP `Error`，而仅声明未使用的 Python `use` 不报错。
- TypePHP callable 被 Python 回调。
- 引用计数、对象析构和重复调用。
- 编译后的真实程序输出，而不是只检查生成代码字符串。

#### pytest

pytest 用于 phpy 自身已有 Python-facing bridge 的回归测试；它不表示 TypePHP 会生成 Python extension。需要验证：

- Python 调用 PHP 函数、对象和 callable。
- 同步重入和 phpy module 生命周期。
- Python 对 Zend callable/object proxy 的持有、释放和异常映射。
- 永久禁止从 Python 线程进入 ZendVM 的防护。

三层测试不能互相替代。C++/GoogleTest 可以覆盖 phpy 内部的引用计数、RAII 和低层转换，但不能代替 PHPUnit、PHPT 或 pytest。

### 21.2 每项语义的测试矩阵

每个已支持能力至少考虑以下维度：

- 正常路径。
- 错误类型和错误消息。
- 边界值及空值。
- Python 子类和动态协议。
- TypePHP → Python → TypePHP 重入。
- 仅由 TypePHP 发起、经 callable 代理发生的 Python → TypePHP → Python 同步重入。
- 正常析构和异常析构。
- 重复执行、`sys.modules` identity 和重复 import 不重新执行 module 代码。
- Debug、Release 以及支持的平台。

转换测试必须包含：

- `PHP_INT_MIN/PHP_INT_MAX` 及超出范围的 Python int。
- `NaN`、`INF`、`-INF` 和负零。
- 空字符串、Unicode、无效 UTF-8、内含 NUL 的 bytes。
- 空 list/dict、混合 key、深层容器、递归容器和循环引用。
- 同一 Python 对象经多次包装后的 identity。

### 21.3 内存与稳定性测试

涉及 `PyObject*` 或 `zval` 所有权的修改，除功能测试外还必须执行：

- PHP memory leak report。
- Python debug build/refcount 检查（环境可用时）。
- ASan/UBSan 构建。
- 异常注入测试，覆盖每一个可能提前返回的分支。
- 循环创建和销毁对象的压力测试。
- 进程退出时仍存在跨 VM 代理对象的测试。

不允许把 coredump、泄漏或未清理的 pending exception 标记为“预期行为”来绕过测试。

### 21.4 覆盖率要求

- 设计文档中每一条规范性行为都必须能够对应到至少一个测试。
- 新增和修改的桥接代码需要覆盖正常分支与错误分支。
- 项目整体覆盖率不得因本功能下降。
- 对 GIL、引用计数、异常和析构代码，不能只依赖行覆盖率，必须人工检查分支矩阵。
- 最终 coding 计划必须先列出测试清单，再列实现任务。

## 22. 已确认与待确认问题

已确认：

1. Python 互调用是可选的扩展级特性；TypePHP 不链接或在编译期检查 `libphpy.so`，首次实际调用时若 phpy 未加载则由 Zend 抛出 PHP `Error`。
2. TypePHP 尽可能采用显式转换，不继承 phpy 的全部隐式转换行为。
3. TypePHP 运算符在编译期改写为 `operator\add($left, $right)` 一类 Python 标准库调用，不使用 phpy opcode handler，也不生成 phpy C++ 符号调用。
4. `python` 根命名空间大小写不敏感，其后的所有 Python 符号大小写敏感。
5. `python` 是编译器处理的特殊语言命名空间。
6. 运行时类继续使用 `PyObject`、`PyDict` 等 phpy 公开名称。
7. `python\dict()` 等构造语法是现有 phpy 类构造器的语法糖；`python\print()` 等是 `PyCore` API 的语法糖。
8. `new PyList()` 与 `python\list()` 具有相同的 `PyList` typed object 类型和优化能力。
9. phpy 解决运行时问题，TypePHP 只通过缓存的 `zend_function*` 和 PHPX/Zend 通用对象 API 动态调用 phpy Facade。
10. TypePHP 的 Python 专用实现与测试放入独立子目录，通过受控入口接入通用编译流程。
11. Python 线程、`asyncio` 和 subinterpreter 永久禁止，且不作为后续兼容目标。
12. `===` / `!==` 分别映射 Python identity 的 `is` / `is not`；`==` / `!=` 使用 Python 值比较。
13. 仅支持 TypePHP 主动调用 Python；不生成 Python extension，不提供 `#[PythonExport]`，不向 Python 注册 TypePHP 符号。
14. CPython 和 bridge 生命周期完全复用 phpy 的 `MINIT/RINIT/RSHUTDOWN/MSHUTDOWN` 入口。
15. Python 包变量使用 PHP namespace constant 语法 `math\pi` 读取，但在运行时执行动态 Python attribute lookup；`math::pi` 和 `math::$pi` 是错误的 class member 表达法。
16. `np\array()` 表示读取并调用 Python 包成员；该成员可以是函数、class 或其他 callable，具体类型由 Python 运行时决定。
17. `PyObject` 可以与 TypePHP 值混合运算；TypePHP 操作数转换为 Python 对象后，整个运算由 CPython protocol 执行，结果保持为 `PyObject`。
18. Python 函数、方法、class 构造和 builtin 调用的结果一律保持为 `PyObject` 或已知的 phpy 子类；禁用 phpy 返回值隐式转换。
19. `PyObject::toValue()` 是显式标量/容器转换方法，等价于 `python\scalar()`；`PyObject::toArray()` 只接受可转换容器和 iterator，不支持的类型返回空数组。转换后可继续使用普通 TypePHP 转换，例如 `$obj->toValue()->toInt()`。
20. TypePHP 调用 Python 时，所有参数自动转换为 Python 类型；TypePHP 数组递归深拷贝，空数组默认转换为 Python list。
21. 性能敏感代码应复用 `PyDict`、`PyList`、`PyStr` 等代理对象，避免同一 TypePHP 值反复转换和深拷贝。
22. TypePHP 的主要语言增量是 `use python\...` 和模块别名；使用别名时通过与 `funcMap` 同类的 lazy indexed map 调用 phpy import，其他运行时能力优先直接复用 phpy。
23. `use python\module` 完全交由 PHP namespace 解析处理；当前 `.php` 文件没有实际访问解析到该 module 的符号时，不生成 helper，也不执行运行时 import。
24. 发现 `module\attr` 或 `module\func()` 时，才为完整 module 名称分配 ID；未使用的 `use` 不占 map slot，也不执行 import。
25. `pythonModuleMap` 与 `funcMap` 一样集中声明、按 ID lazy lookup；区别是 module 保存为拥有引用的 Zend object zval，必须在 request clean 中逐项 `zval_ptr_dtor()` 并恢复为 `UNDEF`。
26. `sys.modules` 负责全局加载状态和 identity，`pythonModuleMap` 只表示 TypePHP 已经完成并缓存的 module binding。
27. TypePHP 生成代码只依赖 PHPX/ZendVM；`PyCore::import()`、builtin、对象方法和转换均解析为 `zend_function*` 动态调用。
28. Python 运算符隐式使用 `python\operator` module；完整运算协议由 CPython `operator` 函数处理，不直接调用 dunder，也不由 TypePHP 实现 reflected fallback。

模块 namespace attribute 初版只读。PHP namespace constant 表达式不能作为赋值目标；后续若增加写入能力，应采用显式 API，并在确定语义后先补测试。

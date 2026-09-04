# Python 工具子模块

TypePHP 将 Python IDE helper 生成器和 Python 源码转换器集成到了 `tpc`。两者位于独立的
`src/PythonTools` 目录，只复用 `tpc` 命令入口，不进入正常的 PHP 预处理、C++ 生成和编译流水线。

## Python namespace IDE helper

```shell
./tpc --gen-python-helper math
./tpc --gen-python-helper numpy.linalg
./tpc --gen-python-helper numpy --output-dir .ide-helper
```

命令通过 PHPy 导入指定 Python module，并使用 Python `inspect` API 采集函数、参数、类、方法和
module attribute。PHPy 扩展以及目标 Python module 必须安装在执行 `tpc` 的主机环境中。

默认生成文件位于当前目录的 `ide-helper` 中。`--output-dir` 可以替换这个输出根目录，既支持
相对当前目录的路径，也支持绝对路径：

```text
ide-helper/python/math.php
ide-helper/python/numpy/linalg.php
ide-helper/python.php
ide-helper/PyObject.php
```

每次生成 module helper 时，会同时扫描 Python `builtins` 并生成根命名空间文件
`python.php`，为 `python\tuple()`、`python\len()` 等内置符号提供 IDE 补全。该文件会
随当前 Python 环境重新生成。

首次生成 module helper 时，还会生成公共的 `PyObject.php`。它包含 `PyObject` 的动态访问、调用、
数组访问、迭代以及 `toArray()`、`toValue()` 等方法提示，供所有 Python module helper 共享。若该文件
已经存在，生成器会保留原文件，不进行覆盖。

生成内容使用 TypePHP 的 module-as-namespace 形式，例如 `python\math\sqrt()`，并兼容普通
`use`、`use function` 和 `use const` 的 IDE 名称解析。文件末尾包含 `die`，用于在误执行时明确
终止程序。helper 只能交给 IDE 索引，不能被 include，也不能加入 TypePHP 项目的 sources 或编译输入。

`PyObject::IDE_HELPER_ONLY` 是所有 helper 共用的提示常量。非 `void` stub 的方法体使用
`die(\PyObject::IDE_HELPER_ONLY)`，以满足 IDE 对返回类型控制流的检查，不会再产生“缺少 return
语句”的诊断。module attribute 使用命名空间 `const` 声明，支持 IDE 的常量补全和 `use const`。
PHP 8.1 及以上允许在常量初始化表达式中使用 `new`。module attribute 因此直接使用仅供 IDE
分析的 `PyObject` 实例作为占位值：

```php
const pi = new \PyObject();
```

这样 IDE 会将常量精确识别为 `PyObject`，而不是从 `null` 推断出错误类型。

公共 `PyObject` helper 还声明了 TypePHP 的虚拟关键词方法，包括 `toInt()`、`toFloat()`、
`toString()`、`toBool()`、`toStream()`、高精度类型转换、`toObject()`、`toAny()` 和 `toRef()`。
这些声明仅用于 IDE 补全；调用会在编译期展开，并不是 PHPy `PyObject` 运行时类的实体方法。
`toArray()` 和 `toValue()` 则仍是 PHPy 提供的真实方法。

Python class 的构造函数会显式调用 `parent::__construct()`。Python 对象若定义了 `count()`，helper
不会重复声明它，因为 `PyObject::count(): int` 已用于 PHP `Countable`。需要调用 Python 自身的
`count()` 时，应显式写为 `$object->__call('count', $arguments)`。

PHP function/class 名称大小写不敏感，而 Python 名称大小写敏感；PHP 保留字也不能声明为普通
stub symbol。生成器会以注释报告无法用合法 PHP 声明表达的符号，不会擅自重命名 Python API。
`python\print()` 的调用语法合法，但 PHP 禁止声明名为 `print` 的函数，因此单纯的
PHP helper 文件无法为它提供无语法错误的符号声明。`list`、`int`、`float` 等 PHP
保留字存在同样的限制。

## Python 转 TypePHP

```shell
./tpc --convert-python-to-php script.py > script.php
```

转换器调用 PATH 中的 `python3` 解析 Python AST，然后输出使用 TypePHP Python namespace
语法的 PHP 源码。普通 module import 会转换为 namespace import：

```python
import math
print(math.sqrt(16))
```

```php
use python\math;

function main(): void
{
    python\print(math\sqrt(16));
}
```

当前支持普通 import、函数、赋值、调用、容器字面量、基础运算、单项比较、if/while/for、
lambda 和基础 f-string。module 顶层变量会转换为 PHP global，以保持函数读取 module 变量的能力。
当语义可以严格保持时，转换器会直接使用 PHP 原生语法：无参数或可安全转换的
`print()` 生成带换行的 `echo`，`sys.exit()` 和整数字面量退出码生成 `exit`。具有
`sep`、`end`、`file`、`flush` 参数的 `print()`，以及字符串或对象形式的 `sys.exit()`
与 PHP 行为不完全一致，仍保留为 Python 调用。

转换器遵循“不能可靠保持语义就拒绝”的原则。class、async、generator、try/with、decorator、
destructuring assignment、chained comparison、嵌套函数以及 loop-else 等尚未完成的语法会抛出带
源文件和行号的错误，不会生成看似可用但语义错误的 PHP 代码。

# py2php：Python → TypePHP 源码转换工具

## 用法

```bash
./bin/tpc.php --convert-python-to-php examples/python/version.py > examples/python/version.php
```

生成的 PHP 源码输出到 stdout，错误输出到 stderr，退出码 0 成功 / 1 失败。

## 架构

```
.py 源码
  └─ PythonAstLoader      python3 子进程（ast 模块）→ JSON AST
       └─ PythonToTypePhpConverter   AST → TypePHP 源码字符串
            └─ Command::execute      CLI 分发（--convert-python-to-php）
```

- 源码：`src/PythonTools/Command.php`、`src/PythonTools/Converter/`
- 不支持的语法抛出 `RuntimeException("{file}:{line}: unsupported Python syntax {节点类型}[: 详情]")`，CLI 层转为 stderr + 退出码 1。
- 测试：`phpunit/src/PythonTools/`（`PythonToTypePhpConverterTest`、`PythonAstLoaderTest`、`PythonToolsCommandTest`），与本文档逐项对应。

## 语句支持矩阵

| Python 语法 | 状态 | 转换规则 / 报错 |
|---|---|---|
| `x = expr` | ✅ | `$x = expr;`，模块级变量自动注入 `global` |
| `x = y = 1`（链式赋值） | ✅ | `$x = $y = 1;`（仅限名称目标；含属性/下标目标时报错） |
| `x += expr` 等增强赋值 | ✅ | 支持 `+ - * / % ** << >> \| ^ &` 系列；`//=` `@=` 展开为 `python\operator\floordiv/matmul($x, ...)` 调用 |
| `x: int = expr` | ✅ | 忽略注解，转换为普通赋值 |
| `x: int`（纯注解） | ✅ | 转为注释 `// annotation-only declaration: x`，不登记为模块全局 |
| `a, b = x`（解构） | ✅ | `[$a, $b] = $x->toArray();`（PyObject 转 PHP 数组后解构；元素允许名称/属性/下标。嵌套解构、星号解构 `a, *b = x`、链式解构不支持。元素个数不匹配时按 PHP 语义补 null，不报 Python 的 ValueError） |
| `def f(...)` | ✅ | 见「函数签名」；名为 `main` 的函数重命名为 `main_`（避免与 TypePHP 入口冲突），调用点同步改写 |
| 嵌套 `def` | ❌ | `FunctionDef: nested functions require Python closure scope analysis` |
| `@decorator` | ✅ | 见「函数装饰器」 |
| `return [expr]` | ✅ | `return [expr];` |
| `if / elif / else` | ✅ | 同构转换 |
| `while` | ✅ | 同构转换；`while/else` 不支持 |
| `for i in iter` | ✅ | `foreach (iter as $i)`；`for/else`、元组目标不支持 |
| `break` / `continue` / `pass` | ✅ | `pass` → `// pass` 注释 |
| `global x` | ✅ | `global $x;`（与自动注入的 global 并存时会重复出现，冗余但合法，属已知行为） |
| `del x` / `del o.a` / `del d[k]` | ✅ | `unset(...)`；`del (a, b)` 元组/列表目标逐项展开；非法 del 目标（如 `del f()`）由 Python 解析器先行拒绝 |
| 模块级字符串字面量（docstring） | ✅ | 转为 `/** ... */` 注释（`*/` 转义为 `* /`） |
| `import a.b` | ✅ | `use python\a;`（仅首段作为别名，见「已知行为」） |
| `import a.b as x` | ✅ | `use python\a\b as x;`（别名等于末段时省略 `as`） |
| `from m import f [as g]` | ✅ | 调用点映射为 `python\m\f(...)` |
| `from . import m` | ❌ | `ImportFrom: relative imports are not supported yet` |
| `from m import *` | ❌ | `ImportFrom: star imports are not supported` |
| `class` | ❌ | `ClassDef` |
| `with` | ❌ | `With` |
| `raise` / `try` / `assert` | ❌ | `Raise` / `Try` / `Assert` |
| `async def` / `await` | ❌ | `AsyncFunctionDef`（`await` 不可达，外层先报错） |
| `match` | ❌ | `Match` |
| `nonlocal` | ❌ | `Nonlocal` |

## 函数签名

| Python 形态 | 状态 | TypePHP 输出 |
|---|---|---|
| `def f(x, y=4)` | ✅ | `function f($x, $y = 4)` |
| `def f(a, *, b)` | ✅ | `function f($a, $b = null)`（无默认值的仅关键字参数补 `null`） |
| `def f(*args)` / `def f(**kw)` | ✅ | `function f(...$args)` |
| `def f(*a, **kw)` | ❌ | `FunctionDef: simultaneous *args and **kwargs cannot be represented by one PHP signature` |
| `lambda a, b=2: a + b` | ✅ | `fn ($a, $b = 2) => $a + $b` |

## 表达式支持矩阵

| Python 语法 | 状态 | 转换规则 / 报错 |
|---|---|---|
| 字面量 `int / float / str / True / False / None` | ✅ | `var_export`；`None` → `null` |
| `b'...'` bytes | ❌ | `{file}: Python bytes literals are not supported yet`（无行号） |
| `1j` complex | ❌ | `{file}: Python complex literals are not supported yet`（无行号） |
| 变量名 | ✅ | `$name`；`this` 转义为 `$this_` |
| 模块别名作为值 | ❌ | `a Python module cannot be used as a first-class value in TypePHP namespace syntax` |
| 属性链 `o.a.b` | ✅ | `$o->a->b`；模块别名链仅首段为模块成员：`sys.version_info.major` → `sys\version_info->major` |
| 模块属性赋值/删除 | ❌ | `Attribute: Python module attributes cannot be assigned or deleted` |
| 函数调用 | ✅ | 已定义函数直连 `f(...)`；内置函数映射 `python\len(...)`；`from m import f` 映射 `python\m\f(...)`；其他名字按变量可调用 `$f(...)` |
| 关键字参数 / `*args` / `**kwargs` 调用 | ✅ | `f(x: 1, ...$args)` |
| 容器字面量 `[] () {} {:}` | ✅ | `python\list/tuple/set/dict([...])`，支持 `...` 解包 |
| 二元运算 `+ - * / % ** << >> \| ^ &` | ✅ | 同构转换 |
| `//` 整除 / `@` 矩阵乘 | ✅ | `python\operator\floordiv(a, b)` / `python\operator\matmul(a, b)` |
| 一元运算 `- + not ~` | ✅ | `- + ! ~` |
| 比较 `== != < <= > >=` | ✅ | 同构转换 |
| `is` / `is not` | ✅ | `===` / `!==` |
| `in` / `not in` | ✅ | `python\operator\contains(b, a)`（参数交换）/ 取反 |
| 链式比较 `a < b < c` | ❌ | `Compare: chained comparisons require explicit temporary variables` |
| `a and b` / `a or b` | ❌ | `BoolOp` |
| `x if c else y` | ✅ | `(c ? x : y)` |
| 下标 `a[i]` / 切片 `a[l:u:s]` | ✅ | `$a[$i]` / `$a[python\slice(l, u, s)]`（缺省为 `null`） |
| f-string | ✅ | 拼接 + `->toString()`；运算符等优先级敏感表达式整体加括号 |
| f-string 的 `!r` 转换 / `:03d` 格式说明 | ❌ | `FormattedValue: formatted f-string conversions are not supported yet` |
| 海象 `:=` | ✅ | 表达式内赋值 `($n = 10)` |
| 推导式 / 生成器表达式 | ❌ | `ListComp` / `SetComp` / `DictComp` / `GeneratorExp` |
| `yield` / `yield from` | ❌ | `Yield` / `YieldFrom` |

## 函数装饰器

装饰器在 `main()` 起始处（其他顶层语句之前）按 Python 语义**自底向上**重绑定到同名模块变量：

```python
@a
@b
def greet(): ...
```

```php
function greet() { ... }

function main(): void
{
    global $greet;
    $greet = b('greet');
    $greet = a('greet');
    ...
}
```

- 装饰器可以是已定义函数、`from m import f` 导入符号、模块属性或装饰器工厂（`@dec('x')` → `$greet = dec('x')('greet');`）
- 被装饰函数名登记为模块全局，所有调用点（包括其他函数体内）经 `global` + 变量间接调用装饰结果：`$greet()`
- 被装饰函数体内的递归调用同样解析到装饰后的变量，与 Python 语义一致

## print / sys.exit 降级规则

仅当 PHP 行为与 Python 完全一致时才降级为原生语句：

| 形态 | 输出 |
|---|---|
| `print()` | `echo "\n";` |
| `print("a", "b")`（字符串/整数常量、模块属性、容器、f-string） | `echo 'a', ' ', 'b', "\n";` |
| `print(1.5)`、`print(True)`、`print(x, sep=...)` | 不降级：`python\print(...)` |
| 用户定义/导入/赋值遮蔽 `print` 后 | 不降级 |
| `sys.exit()` / `sys.exit(2)`（含 `from sys import exit` 形式） | `exit;` / `exit(2);` |
| `sys.exit("fail")` | 不降级：`sys\exit('fail');` |

## 已知行为（非错误，但需留意）

1. `import os.path`（无别名）只引入首段 `use python\os;`。
2. 函数内显式 `global x` 与按模块全局自动注入的 `global x` 会重复出现（合法 PHP）。
3. `print = str` 这类把内置名赋给变量的写法，右侧按变量处理（`$print = $str;`），不做内置名解析。
4. bytes/complex 字面量的报错没有行号（常量在 AST 加载阶段编码，位置信息未传递）。
5. 装饰器重绑定统一在 `main()` 起始处执行，与 Python "def 处即装饰" 的精确位置略有差异；装饰器表达式若依赖顶层语句后段的赋值，求值时机可能不同。
6. 被装饰函数名会登记为模块全局，导致所有函数的自动 `global` 注入清单中出现该名字（冗余但合法）。

## 运行测试

```bash
vendor/bin/phpunit --filter 'PythonToTypePhpConverterTest|PythonAstLoaderTest|PythonToolsCommandTest'
```

转换器测试依赖真实 `python3` 解析 AST，环境缺失时自动跳过。

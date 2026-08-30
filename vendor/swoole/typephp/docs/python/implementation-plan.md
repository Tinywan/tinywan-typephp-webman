# TypePHP Python 互调用分阶段实施计划

> 本计划以 `python/design.md` 为规范。每个阶段严格执行：先增加 PHPUnit/PHPT/pytest 测试并确认失败，再实现，再运行相关测试和完整回归。

## 阶段 1：Python module name、use 与 lazy binding

目标是完成最小可运行闭环，不实现运算符和通用转换：

1. 识别全局 namespace 中的 `python\module\member()` / `python\module\member`、其他 namespace 中的 `\python\module\member()` / `\python\module\member`、可选的 `use python\module` 简写、根名称大小写不敏感和 Python 后续名称大小写敏感。
2. 不建立 Python 专用 alias 表，也不特殊处理 `use`；使用 PHP 的普通 namespace、`use function`、`use const`、`as` alias、冲突检查和完整名称解析。在 namespace 内必须写成 `\python\module`，相对的 `python\module` 仍解析为当前 PHP namespace 下的名称。
3. 仅在出现 `python\module\attr`、`python\module\func()` 或其 alias 形式时分配 module ID。
4. 生成与 `funcMap` 同类的 `pythonModuleMap`、lazy getter 和 request-clean 代码。
5. 使用 Zend class/function map 动态调用 `PyCore::import()`；不 include、link 或检测 phpy。
6. 使用 Zend object API 读取 module 属性及调用 module callable。
7. phpy 未加载时在首次实际使用处抛出 PHP `Error`；未使用的 Python use 不触发错误。

测试顺序：PHPUnit 代码生成与诊断测试 → PHPT 运行时测试 → 现有 compiler 回归。

实现状态：已完成。完整名称与任意 alias 按 Python dotted module name 共享同一个 runtime slot；完整名称不要求 `use`，两种语法都在首次实际执行时 lazy import。识别前严格采用 PHP namespace resolution：例如在 `namespace App` 中，相对名称 `python\math\sqrt()` 是普通的 `App\python\math\sqrt()`，只有 `\python\math\sqrt()` 指向 Python 根命名空间。

## 阶段 2：builtins、构造语法糖与静态类型

1. `python\name()` 通过 phpy Zend Facade 动态调用：显式 `PyCore` 方法直接复用，其他名称经 Python `builtins` module lookup。
2. `python\list/dict/tuple/set/str/object()` 映射既有 phpy Zend 类或方法。
3. `new PyList()` 与 `python\list()` 等写法获得相同的逻辑静态类型。
4. Python 调用结果保持 `PyObject` 或已知 phpy 子类，关闭 TypePHP 路径的隐式 scalar conversion。
5. 缺少 phpy、builtin 不存在、参数错误和异常映射测试。

实现状态：已完成。TypePHP 在首次实际执行 Python 表达式时延迟启用 phpy 的 `return_as_object`，仅声明未使用的 Python 符号仍不触发运行时依赖。

## 阶段 3：参数转换与显式结果转换

1. TypePHP 参数从左到右求值后自动转换为 Python 值。
2. 标量、数组、空数组、嵌套容器及 TypePHP callable 转换。
3. 通过 `$py->toValue()` 或 `python\scalar($py)` 离开 Python 对象规则；需要确定原生类型时继续使用普通 TypePHP 转换，例如 `$py->toValue()->toInt()`。容器和 iterator 可直接使用 `$py->toArray()`。
4. 深拷贝、递归容器、溢出、Unicode/bytes 和异常路径测试。
5. review 并重构 phpy 转换策略，移除影响同步重入的全局临时转换状态。

实现状态：核心边界已完成。TypePHP 参数严格从左到右求值，支持标量、空数组、嵌套 list/dict 与 callable；`PyObject::toValue()` 与 `python\scalar()` 复用 phpy 的显式转换入口，`PyObject::toArray()` 转换受支持的容器和 iterator。phpy 已移除进程级转换函数指针，改为局部有状态转换器、RAII 递归保护和 128 层深度限制，并覆盖无效 UTF-8、PHP 自引用数组及 Python 循环容器错误路径。Python 大整数与 bytes 的最终语言映射仍保留在本阶段后续工作中。

## 阶段 4：运算符

1. 将运算符改写为 Python 标准库 `operator` module 的动态调用。
2. 混合操作数先转换为 `PyObject`。
3. 严格保证从左到右、各求值一次。
4. 使用 `operator.is_/is_not/truth` 实现 identity 和 truthiness，使用 `iadd/isub/...` 实现 compound assignment。
5. 验证 `operator` 自动处理 `NotImplemented`、reflected dunder 和子类优先级。
6. 对照 phpy opcode-handler 行为，修复 `/` 错误映射 floor division 等既有问题。

实现状态：已完成。二元算术、位运算、比较、`===`/`!==`、一元运算、条件真假值、短路逻辑和复合赋值均通过隐式 `operator` module binding 执行；`/` 使用 `truediv`。混合 TypePHP 操作数由 phpy 在调用边界转换，结果继续保持 `PyObject`，比较和真假值结果显式收敛为 TypePHP `bool`。属性和下标左值由阶段 5 的动态写入协议完成回写。

## 阶段 5：完整对象协议

1. Python 对象属性读写和删除。
2. 下标读写、删除、`isset()`。
3. iterator/foreach。
4. Python callable 和 TypePHP callable proxy 的同步重入。
5. keyword argument、argument unpacking 和错误语义。

实现状态：已完成。Python proxy 的动态属性、未知方法、下标、删除、`isset()`、`foreach` 和 callable 均复用 phpy 的 Zend object protocol，不生成 phpy C++ 符号。方法、属性、下标和 callable 结果会继续传播为 `PyObject`，支持链式访问和后续 Python 运算。named argument 与 unpacking 复用统一调用参数管线并保持从左到右求值；属性和下标复合赋值使用 `operator.i*()` 的返回对象回写原左值。

phpy 同步完成了对象协议加固：`__set()` 转换引用释放、`__unset()`、list/tuple 负索引、list 删除、缺失键与 Python `None` 的 `isset()` 语义、删除和 contains 状态检查，以及 iterator/count 异常传播。相关 BUG 均由 phpy PHPUnit 与 TypePHP PHPT 独立覆盖。

## 阶段 6：phpy 稳定性与性能收尾

1. CPython/ZendVM 生命周期、GIL、owned/borrowed/stolen reference 全量审计。
2. Python/Zend 异常状态和 traceback 审计。
3. 跨 VM 引用环、析构和异常注入测试。
4. ASan/UBSan、PHP leak report、Python debug build 和压力测试。
5. 基准测试动态 Zend call、module map、参数转换和 `operator` module 调用；只优化被数据证明的热点。
6. 完整 PHPUnit、pytest、PHPT 和现有 TypePHP compiler 回归。

实现状态：已完成。第一轮 CPython 失败路径审计已覆盖通用对象、list、dict、tuple、set 的构造和下标写入，以及 sequence/set 的 `contains()`。PHP 到 Python 的 key/value 转换失败现在会立即映射为 `PyError`，所有已取得的新引用均由作用域守卫释放；构造失败不再留下未处理的 CPython error indicator，`contains()` 的 `-1` 错误结果也不再被误判为 `true`。无效 UTF-8、unhashable set member、失败后容器仍可继续使用等路径已有 phpy PHPUnit 回归测试。

第二轮审计覆盖 module import、异常转换、callable 检查和显式 iterator API。`PyImport_ImportModule()`、`PyErr_Fetch()` 和 `PyIter_Next()` 转移给调用方的新引用现在都会在 Zend wrapper 取得独立引用后统一释放；重复 import、Python 异常或显式 iterator next 不再持续增加引用计数。调用非 callable 的 Python 属性或 `PyObject` 会稳定抛出 `PyError(TypeError)`，不再因为 `PyCallable_Check()` 未设置 error indicator 而静默返回 `null`。`PyCore::next()` 也会区分正常迭代结束和 iterator 异常。上述路径均先建立失败的 phpy PHPUnit 回归测试，其中对象调用行为另有 TypePHP PHPT 集成覆盖。

第三轮审计覆盖 `PyCore` Facade 的转换失败与函数缓存。`PyCore::eval()` 会在 globals 转换失败后立即抛出 `PyError`，`PyCore::bytes()` 对非字符串标量使用转换后的 `zend_string`，两者不再解引用空指针或错误的 zval union 字段而导致进程崩溃。`PyCore::next()` 同时释放参数转换产生的 iterator 引用。builtin/operator 函数缓存改用 `std::string` 内容键，不再把请求级 `char*` 地址作为长期 key，也避免同名动态调用不断重复缓存并增加 Python function 引用计数；调用存在但不可调用的 builtin 会释放临时引用并抛出 `PyError(TypeError)`。所有问题均由先失败的独立 PHPUnit 覆盖，其中两个崩溃用禁用 core dump 的隔离进程确认退出码 139 后再修复。

第四轮审计覆盖 Python 到 PHP 的同步回调边界。phpy 会将 Python keyword arguments 转换为 Zend named parameters，并在任一位置参数或命名参数转换失败后立即停止，不会执行只接收到部分参数的 PHP callable。PHPX 为 AOT 原生闭包生成并管理 Zend `arg_info` 参数名元数据，因此 Python kwargs 可以按名称绑定到 TypePHP 闭包，而不是依赖参数位置或降级为字符串 callable。phpy PHPUnit、PHPX 单元测试和 TypePHP PHPT 分别覆盖了转换失败、Zend 命名绑定以及完整的 Python→TypePHP 回调链路。

第五轮审计覆盖 Python 字符串跨 Zend 边界时的异常和所有权。Python 孤立代理字符无法编码为 UTF-8 时，`phpy.String`、动态 PHP 类名、字典键、`PyObject::__toString()` 和 Python 异常消息格式化都不会再使用空指针或未初始化长度；修复前相关隔离测试会退出 139 或尝试分配异常大的内存。`StrObject` 现在具有显式有效状态，所有调用方必须在访问指针前检查转换结果；异常消息的字符串化仅作为 best-effort 辅助信息，失败时保留原始 Python error/type/value，并清理临时 CPython error indicator。`new_string()` 同时补齐 Zend carrier 析构注册，定长字符串直接取得唯一的 `zend_string` 引用，消除了成功路径的泄漏和未初始化 zval。

第六轮引用审计修复了 `PySequence::slice()` 的 new-reference 泄漏。切片在包装为 Zend `PyObject` 后会释放 CPython API 返回的原始所有权，同时保留 wrapper 自己持有的引用；由 `sys.getrefcount()` 压力测试验证重复创建并销毁切片不会继续增加元素引用计数。切片创建失败也会在接触空指针前转换为 `PyError`。

第七轮审计覆盖动态 PHP 的 Python 运算符协议。phpy opcode handler 现在使用 CPython `PyNumber_*` / `PyObject_RichCompareBool()`，`/` 与 `/=` 使用 true division，复合赋值会用 in-place API 返回的对象更新 Zend 左值，并正确处理不可变 Python 对象、Zend 引用变量、表达式结果和失败后左值状态。`===` / `!==` 使用 Python object identity，bool cast、`!` 和条件分支使用 Python truth protocol；PHP 操作数转换、结果和异常路径的新引用统一由 RAII 守卫管理。TypePHP 的 `operators.phpt` 同时以 ZendPHP + opcode handler 和 AOT + `operator` module 运行，因此是两条实现的输出一致性门禁。

动态代码的一元正负运算已确定为兼容性边界：PHP 会把 `-$value` / `+$value` 编译成乘以 `-1` / `1`，opcode handler 无法区分它与源码中的显式乘法。动态 ZendVM 代码不再尝试改写 AST，而是明确保留 `$value * -1` / `$value * 1` 的行为。Python 内置数值和 NumPy 等常见对象的结果通常一致，但自定义对象的 `__neg__()` / `__pos__()` 可能与 `__mul__()` 不同。AOT TypePHP 保留原始 AST，仍分别 lowering 为 `operator.neg()` / `operator.pos()`，语义不受影响。外部用户文档 `python.md` 已将这一差异列入兼容性限制。

性能基准分别覆盖 module property、operator module call、已有 `PyObject` 参数和 PHP 标量参数转换。在当前未优化构建中，module property 约为 0.7–0.9 μs/op，operator call 约为 1.8–2.5 μs/op；参数是否预先包装为 `PyObject` 没有呈现稳定差异。尝试以 indirect zval wrapper 消除 module map 每次访问的引用计数后，A/B 中位数仍处于同一噪声区间，因此没有保留生命周期更敏感且收益未经证明的优化。

phpy 的 CMake Python-extension 目标同时完成了 out-of-tree 构建修复，并通过从 Zend 标准 cast handler 推导返回类型来兼容旧版 PHP 的 `int` ABI 与新版 PHP 的 `zend_result` ABI。PHP 8.1 和 PHP 8.4 均已完成全新构建验证。

内存门禁使用 Valgrind Memcheck 执行。测试关闭 Zend allocator 与 PCRE JIT，在最小独立进程中分别循环 100 次 PHP Closure kwargs 回调、可调用 PHP 对象 kwargs 回调、sequence slice 创建销毁、无效 Unicode 的对象字符串化、字典键转换和异常格式化。结果为 0 invalid-access、0 definite leak、0 indirect leak；进程退出时由 PHP/CPython 保留的 493,106 bytes 均为 still-reachable，不计为泄漏。ASan 扩展无法安全 `dlopen` 到当前启用了 `RTLD_DEEPBIND` 的非 ASan PHP，因此本轮采用不要求 PHP 同步重编译的 Valgrind 作为内存检查工具。

阶段 6 最终门禁结果：phpy PHPUnit 135 tests / 469 assertions 通过（1 个既有 warning、1 个环境相关 skip），pytest 26/26 通过，TypePHP Python PHPT 14/14 通过，TypePHP PHPUnit 1103 tests / 2729 assertions 通过；TypePHP compiler 全量 PHPT 共 934 项，其中 932 PASS、2 SKIP、0 FAIL、0 WARN。动态 operator 压力测试在 Valgrind 下为 0 invalid access、0 definite leak、0 indirect leak。

## 阶段门禁

- 当前阶段的失败测试未先建立，不开始实现。
- 当前阶段所有测试未通过，不进入下一阶段。
- phpy 的行为变更必须先在 phpy 仓库增加 PHPUnit/pytest 测试。
- 每个已修复 BUG 必须保留独立回归测试。
- 不以修改第三方测试期望来掩盖实现差异。

# 局部非逃逸 Closure 原生化设计

## 1. 背景

TypePHP 当前把所有匿名函数和箭头函数都实现为真正的 Zend `Closure`。编译器先生成
一个 `php::ClosureFn`，PHPX 再为每个闭包创建：

- `zend_function` 及其参数信息；
- 保存 C++ 回调、绑定对象和捕获值的 `ClosureCarrier`；
- 由 `zend_create_fake_closure()` 创建的 Zend `Closure` 对象。

调用闭包时仍需经过 callable 解析、Zend 调用帧和参数数组。这个实现是 PHP 可见
Closure 的必要兼容路径，但对于只在当前函数内直接调用的闭包，Closure 对象从未被
观察，以上分配和动态调用没有语义价值。

本优化把编译器能够证明不逃逸的局部闭包生成为具体 C++ lambda，并把其调用点生成为
直接 C++ 调用。不能证明安全时必须完整回退到现有 Closure Carrier 路径。

## 2. 目标

第一阶段目标如下：

1. 消除局部闭包创建时的 `zend_function`、Carrier 和 Zend Closure 分配；
2. 消除每次调用的 callable 查询及 Zend execute frame；
3. 保留 PHP 的闭包创建时捕获、按值复制、可变捕获状态及参数求值顺序；
4. 不改变任何可能观察 Closure 对象身份或 Zend 调用帧的代码；
5. 以保守证明为前提，分析失败不产生诊断，只回退到原实现。

本优化属于 TypePHP 编译器内部实现，不新增 PHPX 公共 API。

## 3. 第一阶段适用范围

仅优化以下形态：

```php
function calculate(int $value): int
{
    $offset = 1;
    $callback = static fn (int $item): int => $item + $offset;
    return $callback($value);
}
```

候选闭包必须同时满足：

- 赋值是函数顶层的一条独立语句；
- 左值是普通局部变量；
- 该变量在函数中只有这一个定义；
- 至少存在一个调用点；
- 每个读取位置都只能是 `$callback(...)` 的直接调用目标；
- 赋值在源码和控制流上支配全部调用点；
- 调用只使用固定位置参数，暂不支持命名参数和 unpack；
- 参数没有默认值、引用或 variadic；
- 按值捕获，或对固定 `int/string/float/bool/array` 存储的按引用捕获；
- 闭包不是 generator，不按引用返回，也不包含嵌套函数或闭包；
- 闭包体不依赖 Zend 调用帧，例如不调用 `func_get_args()` 或
  `debug_backtrace()`；
- 第一阶段只处理全局函数体，不处理类方法中的 `$this`、词法 class scope 和
  late static binding。

这里的“函数顶层”限制很重要。TypePHP 通常把普通局部变量声明提升到 C++ 函数入口，
但 C++ lambda 的匿名类型必须通过 `auto` 在初始化位置声明；同时 PHP 的按值捕获发生
在闭包表达式求值时，不能把初始化提前到函数入口。

## 4. 必须回退的情况

只要 Closure 值可能被 PHP 代码观察，就继续生成真实 Closure：

```php
return $callback;
$array[] = $callback;
$object->callback = $callback;
$other = $callback;
array_map($callback, $values);
is_callable($callback);
new ReflectionFunction($callback);
```

以下情况也回退：

- 多次赋值、条件赋值、循环内创建或 `unset()`；
- 动态 `var-ref` 捕获、引用参数或递归闭包；
- 不同闭包在同一个变量中汇合；
- generator Closure；
- 命名参数、参数展开、variadic 或默认参数；
- 捕获 Native Object、强类型容器或其他需要专门 GC root 的值；
- 嵌套闭包以及任何分析器未明确识别的使用方式。

回退不是错误，也不应产生 warning。优化器只能在正向证明完整时启用。

## 5. 生成模型

原始代码：

```php
$base = 10;
$callback = static function ($value) use ($base) {
    return $base + $value;
};
$result = $callback(5);
```

目标代码的结构为：

```cpp
auto callback = [base = base](php::Var value) mutable -> php::Var {
    return base + value;
};
result = callback(5);
```

使用显式 init-capture 有两个原因：

1. 捕获在闭包创建位置形成快照，而不是引用外层变量；
2. `mutable` 允许闭包内部修改自己的捕获副本，并让该状态在多次直接调用之间保留。

`php::Var` 的复制构造会解除普通 PHP reference，符合 `use ($value)` 的按值语义。
固定 `int/string/float/bool/array` 的 C++ 值则直接复制。第一阶段不接受需要特殊
生命周期处理的 Native Object 和 std container 捕获。

固定类型的引用捕获不需要先制造 Zend reference。因为分析器已经证明 lambda 不逃逸，
其生命周期完全包含在外层 C++ 函数帧内，所以：

```php
$callback = function () use (&$number, &$text, &$items): void {
    $number++;
    $text .= '!';
    $items[] = $number;
};
```

可直接生成：

```cpp
auto callback = [&number, &text, &items]() mutable -> php::Var {
    // ...
};
```

闭包内部把这三个名字登记为对应的 `INT_REF/STR_REF/ARRAY_REF`，继续复用 TypePHP
现有 typed-ref 赋值检查。这样没有 `php::Ref`、`RefWrap`、`zend_reference` 或写回步骤。
动态 `php::Var` 引用以及对象、resource、stream、Native Object、std container 等捕获
仍回退到真实 Zend Closure 路径。

第一阶段继续使用 `php::Var` 作为闭包参数和返回 ABI，以复用现有闭包体生成逻辑并
降低语义风险。后续可以在独立优化中，根据参数和返回声明把 ABI 收窄到
`php::Int`、`php::Str` 等固定类型。

## 6. 参数求值顺序

PHP 固定为从左到右求值参数，而 C++17 不保证不同函数实参之间的求值顺序。直接调用
不能简单输出：

```cpp
callback(parse(a), parse(b));
```

多参数调用必须沿用 TypePHP 的 ordered operand 机制，在调用前按源码顺序物化存在
副作用的值，然后再调用 lambda。第一阶段即使参数表达式较简单，也不能依赖 C++
编译器碰巧采用的顺序。

## 7. 分析与代码生成

新增局部 Closure 分析器，在函数 SSA 分析完成后、convert 生成语句前运行：

1. 收集函数顶层 `$var = Closure/ArrowFunction` 候选；
2. 遍历当前函数 AST，分类该变量的每个定义和使用；
3. 任一使用不是直接调用目标即淘汰候选；
4. 检查闭包和调用参数是否位于第一阶段支持集合；
5. 把证明结果写入当前 `FunctionContext`；
6. 赋值生成器在原始语句位置输出 `auto` lambda；
7. 函数调用生成器识别该局部变量并输出直接调用；
8. 普通局部变量声明生成器跳过已经在源码位置声明的 lambda。

分析数据只属于当前函数的 `FunctionContext`，不得写入 persistent/request runtime
cache，也不得跨函数复用。

## 8. 安全不变量

实现必须始终满足：

1. **无误优化**：不能证明时回退，绝不猜测；
2. **创建时捕获**：不能把 lambda 初始化提升到原 PHP 表达式之前；
3. **生命周期包含**：lambda 及全部调用点位于同一 C++ 函数帧；
4. **无 PHP 可见身份**：优化后的值不能进入 zval、数组、属性、参数或返回值；
5. **求值顺序一致**：实参和闭包体中的副作用顺序必须与 PHP 一致；
6. **异常边界一致**：PHP 异常仍通过现有 C++ `zend_object *` 路径传播；
7. **回退等价**：移除优化标记后，同一源码必须仍可由 Carrier 路径编译运行。

## 9. 测试计划

正向测试至少覆盖：

- 无捕获箭头函数；
- 按值捕获及创建时快照；
- 捕获副本在多次调用之间保持内部修改；
- 固定 int/string/float/bool/array 的直接引用捕获；
- 普通匿名函数；
- 多次直接调用；
- 参数表达式的左到右求值。

回退测试至少覆盖：

- 作为参数传给 `array_map()`；
- 从函数返回；
- 写入数组或属性；
- 赋给另一个变量；
- 动态 `var-ref` 引用捕获；
- 默认参数、variadic、unpack 和命名参数；
- generator、递归及嵌套 Closure；
- class method 中的 `$this`；
- `ReflectionFunction`、`is_callable()` 等身份观察。

生成代码测试需要同时断言：正向候选不包含
`newClosureWithParameters`/`ClosureCarrier` 调用，而回退用例仍包含真实 Closure 创建。

## 10. 性能验证

`benchmark/dynamic-call/benchmark.php` 中的 `closure_monomorphic` 是第一阶段的主要性能
用例。还应保留 `closure_alternating` 作为不能原生化的对照组。

验证指标包括：

- Closure 创建次数和堆分配；
- 每次调用耗时；
- TypePHP/Zend PHP 比值；
- 生成 C++ 大小及编译时间；
- checksum 与 Zend PHP 一致。

性能提升不是放宽安全条件的理由。任何会暴露 Closure 对象或无法静态证明的场景，
即使处于热点，也继续使用 Closure Carrier。

# AOT 编译器优化优先级重新评估：考虑 GCC/Clang 二次编译

## 核心原则

AOT 的优化价值不在于做 GCC/Clang 已经做了的事情，而在于**提供 GCC/Clang 无法从 C++ 代码中推断出来的信息**。

GCC/Clang 在 `-O2`/`-O3` 下已经能做的：
- 常量折叠、常量传播 (SCCP)
- 死代码消除 (DCE)
- 公共子表达式消除 (CSE)
- 循环展开、向量化
- 指令选择、寄存器分配
- 函数内联 (同一编译单元内)
- 分支预测优化
- SIMD 自动向量化

GCC/Clang **不能做的**——因为语义被 `php::Var`、`php::Object`、虚函数调用等抽象层遮蔽：
- 将 `php::Var` 窄化为具体 C++ 类型
- 消除 `php::Object` 的虚调用
- 消除引用计数操作
- 将 `php::Array` 从堆分配移到栈分配
- 将 `php::BigInt` 降级为原生 `int64_t`
- 消除 `php::call` 函数查找/分发开销
- 跨翻译单元的全局分析（LTO 部分可以，但受限于可见性）

---

## 重新排序的优先级

### Tier 1: 直接改善 C++ 代码形状（GCC 无法修复）

这些优化改变的是**生成出来的是什么 C++ 代码**，而不是优化已达成的 C++ 代码。此处是 AOT 的最核心价值。

#### #1 类型推断 → 生成具体 C++ 类型而非 php::Var

**问题：** 当前 AOT 将大量变量映射为 `php::Var`（全局类型），GCC 只能对 `php::Var` 的成员函数做有限优化。

**收益：**
```
// 当前生成的代码 (GCC 能做的不多)
php::Var a = php::toInt(x);
php::Var b = php::toInt(y);
php::Var c = php::add(a, b);   // GCC 看不到这是加法

// 类型推断后
int64_t a = php::toInt(x);
int64_t b = php::toInt(y);
int64_t c = a + b;             // GCC 可以进行所有整数优化
```

**对 GCC 二次编译的价值放大：** 当类型变成 `int64_t` 后，GCC 可以进一步：
- 寄存器分配（不再走 `php::Var` 的内存布局）
- 常量折叠和传播
- 循环优化（循环内的 `php::Var` 操作变成纯整数运算）
- 自动 SIMD 化

**实施建议：** 这是**最高优先级**。不需要完整的 SSA 形式，只需在每个赋值点推断最精确的类型，并在 C++ 代码生成时使用该类型声明变量。已经做的 union/nullable 类型检查基础设施可以扩展为类型推断。

---

#### #2 去虚拟化 (Devirtualization)

**问题：** PHP 所有非 final 方法调用都是虚调用。AOT 生成 `obj->method()`（C++ 虚函数调用），即使只有一个子类实现了该方法。

**收益：**
```
// 当前生成 (虚调用，GCC 不敢消除 vtable 查找)
return self->foo();     // virtual call

// 去虚拟化后 (GCC 可以内联这个调用！)
return Aot_MyClass_foo(self);  // direct function call
```

**突破口：** 编译期收集所有类及其继承关系。如果：
- 方法是 private → 永远直接调用
- 方法是 final → 永远直接调用
- 类在所有编译文件中只有 1 个非抽象实现 → 直接调用
- `self::foo()` 调用自己类的方法 → 可以直接调用（如果该类没有未发现的子类）

**与 GCC 的协同：** 一旦变成直接调用，GCC 可以：
- 内联整个函数体
- 跨函数常量传播
- 连锁消除后续的冗余操作

---

#### #3 逃逸分析 → 栈分配 + 引用计数消除

**问题：** 每个 `new` 对象/数组都在堆上分配，且通过引用计数管理生命周期。

**收益：**
```
// 当前 (堆分配 + refcount)
php::Array arr = php::newArray();
arr.set("key", value);     // refcount 操作
return arr;                // 拷贝 + refcount

// 逃逸分析后 (栈分配 + 无 refcount)
zend_array arr;             // 栈上
zend_hash_update(&arr, "key", value);  // 无 refcount
return php::Array::fromStack(std::move(arr));  // 仅在返回点打包
```

**对 GCC 二次编译的影响：** 这是对 GCC 帮助**最大**的优化，因为：
- 堆分配 → 栈分配：消除了 `malloc` 调用，GCC 可以完全优化栈布局
- 消除 refcount：GCC 不需要分析互锁操作的副作用，可以自由重排指令
- GCC 可以在栈变量上做 SROA（Scalar Replacement of Aggregates），把数组/对象拆散成标量

**实施建议：** 即使是简单的局部逃逸分析（只分析对象是否被传到函数外部）也能消除大量分配。复杂版本（跨函数逃逸分析）收益继续增长。

---

#### #4 调用图驱动的跨文件内联

**问题：** 同一个 PHP 项目可能编译成多个 `.cc` 文件，GCC 的 LTO 可以跨文件内联但受限于编译时间。

**收益：** AOT 在编译时就知道整个调用图，可以在**生成 C++ 代码时就决定内联**：

```
// 当前
auto result = aot_smallHelper(x, y);  // 函数调用，即使函数体只有一行

// 内联后
auto result = x + y;  // GCC 可以继续优化
```

**关键判断信息：**
- 函数体大小（< 10 行 → 内联候选）
- 调用次数（只调用 1 次 → 内联可以完全消除函数）
- 递归标记（递归函数不内联）
- 跨函数常量传播机会（实参是常量 → 内联后 GCC 可以折叠整个函数）

**与 GCC 的协同：** AOT 做"决策"（该不该内联），把内联后的代码直接生成到调用点。GCC 在更大的内联体上继续做优化。AOT 掌握 GCC 不掌握的信息（来自调用图的全局视角）。

---

### Tier 2: 为 GCC 提供更精确的类型信息

这些优化改善的是传递给 GCC 的 C++ 类型质量。

#### #5 整数范围推断 → 选择最优整数类型

**收益：**
```php
// PHP 源码: 循环计数器，0 到 100
for ($i = 0; $i < 100; $i++) { ... }

// 当前 AOT 生成
int64_t i = 0;            // 总是用 int64_t (防止溢出)

// 范围推断后
int8_t i = 0;             // 0..100 足够，更好的缓存局部性
// 或者至少
int64_t i = 0;            // 但标记 "no overflow possible"，不插入 BigInt 升级
```

**对 GCC 的影响：**
- 更小的类型 → 更好的向量化（更多元素装入 SIMD 寄存器）
- "不会溢出" 的断言 → GCC 的 VRP (Value Range Propagation) 可以基于这个前提做更激进的优化

#### #6 避免不必要的 BigInt/BigFloat 分配

**相关于 #5。** PHP 的整数运算在溢出时自动升级为浮点或 BigInt。如果 AOT 能证明不会溢出，就不需要生成升级代码。

```
// 当前 (每个 int 运算都要考虑溢出)
php::Var result = php::BigInt::add(php::toBigInt(a), php::toBigInt(b));

// 范围推断后 (不会有溢出)
int64_t result = a + b;  // 纯整数，GCC 的世界
```

---

### Tier 3: 与 GCC 部分重叠但仍有价值

#### #7 常量折叠（PHP 层级）

**GCC 能做的：** C++ 层面的常量表达式在 GCC 的 SCCP pass 中完全折叠。

**AOT 独有的价值：**
- PHP 特有的常量：`PHP_INT_MAX`、`PHP_VERSION`、`__DIR__` 等在编译时完全解析
- 跨 PHP 命名空间/类名的常量解析（GCC 看不到 PHP 的符号语义）
- PHP 内建函数的结果：`strlen("hello")` → 5（GCC 不知道 `strlen` 的内部实现但是可以折叠已知参数的函数）

**评估：** 有限价值。大部分 PHP 源码中没有复杂的编译时可求值常量表达式。

#### #8 死代码消除（PHP 层级）

**GCC 能做的：** GCC 的 DCE + unreachable block elimination 非常成熟。

**AOT 独有的价值：**
- 基于 PHP 类型系统消除分支：`if (false)` 在 PHP 层消除 → 不生成任何 C++ 代码
- 基于类型窄化消除分支：`if ($x instanceof Foo)` 当 `$x` 的类型已确定为 `Bar` 且 `Foo ⊄ Bar` 时，false 分支不可达
- 消除未被调用的 PHP 函数/类（跨文件死代码）

**评估：** 中等价值。AOT 应该关注"基于 PHP 语义的 DCE"，而不是与 GCC 竞争"基于 C++ 语义的 DCE"。

---

### Tier 4: 改善编译过程而非输出质量

#### #9 文件缓存（编译加速）

**GCC 能做的：** ccache。但 ccache 需要文件内容哈希匹配。

**AOT 独有的价值：**
- 缓存 PHP→C++ 的翻译结果（解析过的 AST + 类型信息）
- 跨重启复用
- 增量更新（只重编译改动的 PHP 文件）

**评估：** 有用但非核心。先让输出代码质量达到最优，再优化编译速度。

#### #10 Pass Pipeline 架构

工程架构层面的基础设施，提高代码可维护性和扩展性。不直接影响输出质量。

---

## 优先级汇总

```
必须做 (直接影响 GCC 看到的 C++ 类型):
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⭐ #1  类型推断 → 避免 php::Var 泛化     【最大单点收益】
⭐ #2  去虚拟化 → 消除虚调用开销          【第二大收益】
⭐ #3  逃逸分析 → 栈分配 + 消除 refcount   【对数值计算代码收益极大】

强烈建议 (给 GCC 更好的前提条件):
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⭐ #4  调用图内联 → 跨越翻译单元边界
⭐ #5  范围推断 → 最优整数类型选择
⭐ #6  消除 BigInt/BigFloat 不必要分配

锦上添花 (有独特价值但非核心):
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  #7  PHP 层常量折叠
  #8  PHP 语义层 DCE
  #9  编译缓存加速

工程基础:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  #10 Pass Pipeline 架构
```

## #1 类型推断的进一步分析

这是最大单点收益项，值得进一步展开。以下是具体的技术路径：

### 关键洞察：不需要完整 SSA

AOT 是**生成 C++ 代码**，不是运行优化后的字节码。这意味着：

1. 不需要 φ 函数：C++ 的变量赋值天然就是 SSA 的"新鲜变量"形式
2. 不需要 dominance frontier 计算：C++ 编译器会自己做
3. 只需要在**每个表达式**计算其可能的类型，生成对应的 C++ 类型声明

### 当前问题

```
PHP 源码:     $a = 1;
              $b = $a + 2;
生成的 C++:   php::Var a = php::toInt(php::toVar(1));
              php::Var b = php::add(a, php::toVar(2));  // !!全 Var!!
```

此时 GCC 看到的：
- 所有值都是 `php::Var`（一个复杂结构体）
- 运算通过 `php::add()` 函数（外部符号，不可内联）
- 无法确定类型，无法窄化

### 目标

```
PHP 源码:     $a = 1;
              $b = $a + 2;
生成的 C++:   int64_t a = 1;
              int64_t b = a + 2;       // GCC 可以完整优化
```

### 实现路径

**Phase A: 局部类型传播（~300 行）**

遍历 AST，在每个赋值点：
1. 如果 RHS 是字面量 → 类型明确，记录
2. 如果 RHS 是已知类型的变量 → 传播类型
3. 如果 RHS 是二元运算且两操作数类型已知 → 推断结果类型
4. 如果变量被多次赋值为不同类型 → 退化为 `php::Var`

**Phase B: 条件窄化（~200 行，依赖 TypeSpecifier）**

```
if ($x instanceof MyClass) {   // 进入此分支时 $x 的取值确定是 MyClass
    $x->method();              // 这里可以用直接调用
}
```

**Phase C: 跨基本块交汇（~200 行）**

```
if (cond) { $x = 1; } else { $x = 2; }
// 交汇点: $x 是 int64_t (两个分支都是整数)
```

---

## 类型窄化的安全性约束：PHP instanceof 语义分析

### 问题

用户指出：`if ($x instanceof Foo)` 在 PHP 中的语义是——`$x` 可能是 `Foo` 的实例**或其任何子类**的实例。这与 C++ 的 `dynamic_cast<Foo*>` 相同。

这意味着：

```php
if ($x instanceof Foo) {
    $x->method();  // 虚调用！$x 的实际类可能重写了 method()
}
```

**不能**因为窄化为 `Foo` 就把 `$x->method()` 转为直接调用 `Foo::method()`——除非 `Foo` 是 final 类或 method 是 private。

### 窄化的三个安全层级

#### 层级 1: 类型成员可访问性（总是安全）

即使不知道精确类型，只要知道 `$x` 是 `Foo` 的子类型，就可以：
- 确认 `$x` 有某个方法/属性的访问能力
- 消除不可能的代码路径（`if ($x instanceof Foo)` 在 `$x` 已知为 `Bar` 且 `Foo` 与 `Bar` 不相关时为 false）
- 生成正确的 C++ 类型标注（`Foo*` 而非 `void*`/`php::Var`）

```php
// 窄化前: $x 是 php::Var，调用 foo() 可能要抛异常
// 窄化后: $x 是 Foo 的某个子类型，一定有 foo() 方法
$x->foo();  // 可以确认方法存在（虽然仍是虚调用）
```

**收益：** 消除方法不存在的运行时检查，改进错误检测（编译期发现）。

#### 层级 2: final 类 + private 方法（完全安全）

| 场景 | 可窄化为精确类型？ | 可直接调用？ |
|------|-------------------|------------|
| `$x instanceof FinalClass` | 是 | 是 |
| `$x->privateMethod()` | 是 (private 不可重写) | 是 |
| `self::method()` 且类无子类 | 是 | 是 |
| `$x instanceof NonFinalClass` | 否 | 否 |

```php
final class Logger {
    public function log(string $msg): void { ... }
}

if ($x instanceof Logger) {
    $x->log("test");  // 安全：直接调用 Aot_Logger_log($x, "test")
}
```

#### 层级 3: 有限子类集合 + 守卫（guard-based devirtualization）

当 `Foo` 不是 final，但 AOT 编译了整个代码库，可以确定**所有 Foo 的子类**：

```
已知类层次:
  Foo (abstract)
  ├── SubFooA    method() 实现
  └── SubFooB    method() 实现
```

此时 `$x instanceof Foo` 意味着 `$x` 只能是 `SubFooA` 或 `SubFooB`。可以生成：

```cpp
// 守卫式去虚拟化
if (x.getInstanceOf(get_class(SubFooA))) {
    Aot_SubFooA_method(x);          // 直接调用
} else {
    Aot_SubFooB_method(x);          // 直接调用（最后一种不用判断）
}
// 替代原来的: x->method(); (虚调用)
```

**收益：** 对于已知有限子类的类层次，用 if-else 分发替代虚表查找。如果只有 1 个子类（de facto final），则完全消除分支。

### PHP 自身的处理方式

Zend SSA 的 `zend_ssa_var_info` 结构体精确地追踪了这个区别：

```c
typedef struct _zend_ssa_var_info {
    uint32_t  type;
    zend_class_entry *ce;
    bool  is_instanceof : 1;  // 0 = class == ce, 1 = may be child of ce
    // ...
} zend_ssa_var_info;
```

- `is_instanceof = false`：变量**精确是这个类**（来自 `new Foo()` 或 `get_class($x) === 'Foo'` 检查）
- `is_instanceof = true`：变量**可能是这个类或其子类**（来自 `$x instanceof Foo`）

只有 `is_instanceof = false` 且 ce 非 abstract 时，才能安全地去虚拟化。

### 对 Tier 1 优化的影响

| 优化 | 受 instanceof 语义影响？ | 有效范围 |
|------|------------------------|---------|
| #1 类型推断 (生成具体 C++ 类型) | 不直接受影响 | 窄化为 `Foo*` 仍然有价值（比 `php::Var` 好） |
| #2 去虚拟化 | **是——instanceof 不提供精确类型** | 仅 final 类、private 方法、有限子类守卫 |
| #3 逃逸分析 | 不直接受影响 | 不需要精确类型，只需逃逸/非逃逸判断 |
| #4 调用图内联 | 不直接受影响 | 调用图分析的是函数关系，与 instanceof 窄化无关 |
| #5 范围推断 | 不直接受影响 | 整数范围与 OOP 继承无关 |

### 关键结论

`instanceof` 窄化虽然不足以确定性地去虚拟化所有调用，但它提供了**类型成员可见性**的保证，可以消除方法查找、改进错误检测、以及生成更精确的 C++ 类型签名（`Foo*` vs `php::Var`）。

真正能推动去虚拟化的信息来自：
1. **Final 类** — 由源码声明提供
2. **全程序类层次分析** — 编译所有代码后可知哪些类是 de facto final（无子类在代码库中）
3. **调用点特定的类型推断** — 从 `new Foo()` 赋值追踪到调用点

---

## 结论

在 GCC/Clang 二次编译的架构下，AOT 编译器的优化策略应该是：

1. **把 PHP 的动态类型信息"冻结"为静态 C++ 类型**——这是 GCC 无法自己做的
2. **把虚调用"落地"为直接调用**——让 GCC 可以内联
3. **把堆分配"提升"为栈分配**——让 GCC 可以 SROA
4. **不要与 GCC 竞争 SSA 层面的优化**——SCCP、DCE、CSE 留给 GCC
5. **关注跨翻译单元的信息**——调用图、类层次——这是 LTO 也难以覆盖的

# HHVM/HHBBC 编译器 Review：全程序分析、类型系统与优化管道

> 2026-06-11 · 基于 /home/swoole/workspace/cpp/hhvm 源码审查

---

## 一、架构概览

HHVM 的编译体系分为三层：

| 层级 | 组件 | 职责 |
|------|------|------|
| 前端 | HackC (hphp/hack) | Hack/PHP 源码 → HHAS (Hack Assembly) |
| 中端 | HHBBC (hphp/hhbbc) | HHBC bytecode → 优化后 HHBC bytecode |
| 后端 | JIT (hphp/runtime/vm/jit) | HHBC → x86-64 机器码 (运行时 tracing JIT) |

HHBBC（~80k 行 C++）是一个**全程序字节码优化器**，基于不动点迭代分析。核心文件：

| 文件 | 行数 | 用途 |
|------|------|------|
| `interp.cpp` | 6,685 | 抽象解释器（forward dataflow） |
| `index.cpp` | 30,132 | 全程序索引：类型信息、依赖追踪、增量重分析 |
| `type-system.h` | 1,610 | 类型 lattice 定义（trep + specialization） |
| `type-system.cpp` | 8,451 | 类型操作实现（meet/join/subtype/union） |
| `dce.cpp` | 3,102 | 类型感知的死代码消除（局部 + 全局） |
| `analyze.cpp` | 2,293 | 函数级数据流分析驱动 |
| `optimize.cpp` | 962 | 类型感知优化 pass |
| `cfg-opts.cpp` | ~300 | CFG 优化（不可达块删除、异常边简化） |

与 AOT Compiler 的对比：

| 维度 | HHBBC | AOT Compiler |
|------|-------|-------------|
| 编译目标 | HHBC → 优化 HHBC → JIT 机器码 | PHP → C++ → 二进制 |
| 分析级别 | 字节码级（全程序） | AST 级（单文件） |
| IR 形式 | php::Func (factored CFG + Bytecode) | PHP-Parser AST |
| 类型推理 | trep + specialization + 不动点迭代 | SSA + manual type annotations |
| 优化范围 | 全程序（跨文件/跨函数） | 单函数 + 类层次 |
| 调用图 | 动态构建（Index + assumption） | 静态（classExtends + classMethodOverride） |
| 并发 | 函数级并行分析 | 串行执行 |
| 运行时 | HHVM（JIT + refcounting GC） | phpx（RAII 包装 Zend API） |
| 代码量 | ~80k 行 C++（仅 HHBBC） | ~15k 行 PHP |

---

## 二、可借鉴的创新设计

### 2.1 全程序不动点分析（Whole-Program Fixed-Point）

**文件**: `hphp/hhbbc/README`, `analyze.cpp`, `index.cpp`

HHBBC 的核心算法：在**全程序范围**内进行迭代收敛分析。

```
算法流程：
1. 初始化 work list = 所有函数/类
2. 并行分析每个 work unit（只读 Index，产出一个新结果）
3. 单线程：将新结果合并到 Index
4. 如果 Index 有更新 → 把依赖该信息的函数加回 work list
5. 重复直到 Index 不动点（信息不再变化）
6. 最终并行优化 pass
```

关键设计原则：
- Index 中的信息**只能收缩**（shrinking types：变得更精确）
- 函数内分析的类型**只能增长**（growing types：在分析上下文中累积）
- Index 信息**永远不会错误**（soundness guaratee）——只可能不够精确

**AOT 借鉴优先级: P0**

当前 AOT 是单文件串行编译，没有跨文件/跨函数分析。可以借鉴：

1. **Index 结构**：存储每个函数的返回类型、参数类型、属性类型等推断结果
2. **依赖追踪**：记录每次查询（"函数 F 查询了类 C 的方法 M 的返回类型"），在 C::M 类型更新时将 F 加回 work list
3. **迭代收敛**：初始化所有函数为 "未知" → 逐轮分析 → 直至类型信息不再变化

实现建议：
```php
// Preprocessor 中构建
class AnalysisIndex {
    // 函数返回类型（每轮迭代中收缩）
    public array $returnTypes = [];   // funcName => TypeInfo

    // 依赖图：funcName => [被依赖的 funcName, ...]
    public array $dependencies = [];

    // 函数入参状态（从调用方收集）
    public array $paramTypes = [];    // funcName => [argIdx => TypeInfo]

    // 公共静态属性状态
    public array $publicStaticProps = []; // className::prop => TypeInfo
}
```

然后按拓扑顺序反复分析函数，直到 `$returnTypes` 和 `$paramTypes` 不变。

---

### 2.2 Trep 类型格（Bitset Type Lattice）

**文件**: `hphp/hhbbc/type-system.h`, `type-system-bits.h`, `type-system-detail.h`

HHBBC 的类型系统是整个优化器的基石，设计精巧：

**Base 机制 — trep (type representation):**
```cpp
// 每个"基本类型"是一个 bit
BUninit, BInitNull, BFalse, BTrue, BInt, BDbl,
BCls, BLazyCls, BFunc, BClsMeth, BEnumClassLabel,
BObj, BRes, BRFunc, BRClsMeth,
BSStr, BCStr,  // 不可数/可数字符串
BSVec, BCVec, BSVecE, BCVecE, BSVecN, BCVecN,  // 数组的 counted × empty 维度
// ... Dict, Keyset 同理
```

类型通过 bitset 组合表示联合类型。例如 `Int|String` 就是 `BInt|BStr`。

**Specialization（特化）——类型附带的额外信息:**
```
Int=n          — 已知常量整数值
Dbl=n          — 已知常量浮点值
{S,C}Str=s     — 已知常量字符串值
Obj{<}=c       — 已知类类型（精确或子类）
Arr(T1,T2,...) — 数组形状已知（packed array）
Arr([T1:T2])   — 数组键值类型已知
```

**关键创新:**
- `counted` / `uncounted` 维度：字符串和数组区分是否引用计数，允许编译器针对不可数（static/常量）值做更激进优化
- `empty` / `non-empty` 维度：数组区分空/非空，消除冗余的 empty 检查
- Monotonic lattice：类型只能向一个方向变化（Index 中的类型只能收缩，分析中的类型只能增长）

**AOT 借鉴优先级: P0**

当前 AOT 使用 `TYPE_INT`, `TYPE_STRING`, `TYPE_ARRAY` 等离散常量，没有联合类型、不可数标记等细粒度信息。可以：

1. 用 bitset 表示联合类型（`int|string` 而不是 `mixed`）
2. 添加 `Immutable` 标记（编译期可证明不可变的值）
3. 添加 `NonEmpty` 标记（编译期可证明非空的数组）
4. 构建类型格（lattice），定义 meet（⊓）和 join（⊔）操作

```php
// 当前
public const TYPE_INT = 1;

// 建议：bitset
class Type {
    const BINT = 1 << 0;
    const BSTRING = 1 << 1;
    const BBOOL = 1 << 2;
    const BFLOAT = 1 << 3;
    // ...
    const BIMMUTABLE = 1 << 16;  // 不可变标记
    const BNONEMPTY  = 1 << 17;  // 非空标记

    // union type: int|string = BINT | BSTRING
    // meet: narrower = more specific
    // join: wider = more general
}
```

---

### 2.3 抽象解释器（Abstract Interpreter）

**文件**: `hphp/hhbbc/interp.cpp` (6,685 行), `interp.h`

HHBBC 使用**抽象解释器**进行函数级的类型推导。这与传统的 SSA + constraint solving 不同：

```
算法（analyze_func）:
1. 初始化 entry block 的输入状态（参数类型 = Index 提供的保守假设）
2. Work list ← entry blocks
3. While work list not empty:
   a. 取出一个 block
   b. 在 block 上逐指令运行抽象解释器
   c. 遇到可能抛异常的指令 → 传播当前状态到异常边
   d. 遇到分支指令 → 传播分支后状态到 taken 边
   e. Block 可能 fallthrough → 传播最终状态到 fallthrough 边
   f. 如果目标 block 的输入状态改变了 → 加入 work list
```

抽象解释器的关键特性：
- **状态传播**: 每条指令后计算新的 `State`（locals + eval stack 类型信息）
- **Factored CFG**: 异常边被单独建模（`FactoredExitBlock`），普通指令不会因为 "可能抛异常" 而打断基本块
- **类型特化**: 分支条件自动收窄类型（如 `if (is_int($x))` → then 分支中 `$x` 收窄为 `Int`）
- **常量传播**: 内置常量折叠和值跟踪

**AOT 借鉴优先级: P1**

当前 AOT 的 `SsaTypeOptimizer` 做了一定程度的类型收窄，但：
- 没有 factored CFG 概念（异常不参与数据流）
- 没有抽象解释器框架（每条指令定义 `step(state) → new_state`）
- 没有迭代数据流分析

可以在 SSA 之上实现轻量级的抽象解释：
```php
class AbstractInterpreter {
    // 对每个 SSA 基本块运行抽象解释
    function analyzeBlock(Block $block, State $inputState): State {
        foreach ($block->instructions as $instr) {
            $inputState = $this->step($instr, $inputState);
        }
        return $inputState;
    }

    // 每条指令定义如何变换状态
    function step(Instruction $instr, State $state): State {
        // switch on instruction type
        // return new State with updated types
    }
}
```

---

### 2.4 类型感知死代码消除（Type-Aware DCE）

**文件**: `hphp/hhbbc/dce.cpp` (3,102 行)

HHBBC 的 DCE 不同于传统的 liveness-based DCE——它**结合类型分析**来发现更多死代码：

```
两种 DCE:
1. Local DCE — 单个基本块内
   - 向后遍历 block
   - 维护 "反向栈"：标记哪些 eval stack slot 未来会被使用
   - 未被使用的栈 slot → 产生该 slot 的指令可被删除
   - 未被使用的 local store → 消除

2. Global DCE — 跨基本块
   - 对 locals 做 liveness 分析
   - 允许跨 block 消除死 store
```

关键设计：**类型感知**—DCE 需要知道每条指令的类型才能正确判断。例如：
- 如果 `$x` 在某条路径上类型为 `Bottom`（不可达），使用 `$x` 的代码可能是 dead code
- 如果 `$x` 是 counted 类型，相关 inc/dec ref 操作不能被消除

**AOT 借鉴优先级: P2**

当前 AOT 的 DCE 基本依赖 GCC/Clang 的 `-O2`。可以增加编译期的 local DCE：
- 消除对未使用局部变量的赋值
- 消除无副作用的纯计算（如果结果未被使用）
- 利用类型信息判断操作是否可能有副作用

---

### 2.5 DataType 编码：3-of-7 纠错码

**文件**: `hphp/runtime/base/datatype.h`

HHVM 的运行时类型标签使用极巧妙的位编码：

```cpp
// DataType 是 uint8_t
// - bit 0 (LSB): countedness — 0 = 确定不可数
// - bits 1-7: 3-of-7 纠错码 — 恰好 3 个 bit 为 1

// 类型检测变为简单的位操作：
// 检查是否是 Vec 或 Dict: dt <= KindOfVec
// 检查是否有 persistent 版本: dt <= KindOfString
// 检查是否是 null/uninit: dt >= KindOfUninit
```

3-of-7 编码的特性：
- 恰好 3 个 bit 为 1 的 8-bit 值共 C(7,3) = 35 个（每对 persistent/counted 共用同一个 3-of-7 码）
- 任一类型的检测：`(dt & type_mask) == type_tag` 两指令完成
- unsigned LT/GT 比较实现高效类型分组检测

**AOT 借鉴优先级: P4**

这对 AOT 编译器的**生成代码质量**有启发——但主要在 phpx 层。可以考虑给 phpx 的 `Variant` 类型标签使用更高效的编码，优化 `is_int()`/`is_string()` 等运行时类型检查。

---

### 2.6 RepoAuthType：字节码空间高效类型存储

**文件**: `hphp/runtime/base/repo-auth-type.h`, `repo-auth-type-tags.h`

HHBBC 将分析得到的类型信息编码为 `RepoAuthType`，嵌入字节码流（`AssertRAT` 指令），供 JIT 使用。

设计要点：
- 紧凑编码（`CompactTaggedPtr`）：类型 tag + 可选指针（类名/数组形状）打包在一个指针宽度
- 覆盖从 `Uninit`（最精确）到 `Cell`（最宽泛）的完整格
- `SubObj` / `SubCls` 标签支持子类关系
- 数组形状特殊化（packed array 的精确类型）

**AOT 借鉴优先级: P3**

当前 AOT 的类型标注通过 C++ 类型系统（`int64_t`, `php::string`, `php::array`）表示。对于 typed property，可以利用类似 RAT 的思想生成更精确的 C++ 类型声明。

---

### 2.7 Index 依赖追踪

**文件**: `hphp/hhbbc/index.cpp` (30,132 行)

Index 不仅是类型信息的存储，更核心的是**依赖追踪机制**：

```
依赖类型（DependencyKind）:
- ReturnTy    — 函数返回类型
- ConstVal    — 常量值
- ClsConst    — 类常量
- PropType    — 属性类型
- PublicSProp — 公共静态属性类型（特别重要！）
```

当 Index 中某个函数的返回类型更新时，所有查询过该返回类型的函数都会被标记为需要重新分析。

**公共静态属性特殊处理**: 公共静态属性可以被任何函数修改，因此 Index 追踪所有的 mutation 操作。当分析发现某静态属性从未被修改时，可以做更激进的常量传播。

**AOT 借鉴优先级: P1**

与 2.1 的全程序分析配套——Index 依赖追踪是实现迭代分析的基础。在 AOT 中：

```php
class Index {
    // 返回类型：funcName => Type
    // 依赖：funcName => [depends_on_funcName, ...]
    // 脏标记：funcName => bool（需要重新分析）
}
```

Preprocessor 扫描所有文件后，构建初始 Index，然后迭代分析直到收敛。

---

### 2.8 Factored CFG（异常因子化的控制流图）

**文件**: `hphp/hhbbc/cfg.h`, `parse.cpp`

HHBBC 的控制流图将异常边**因子化**：不在每个可能抛异常的指令处终止基本块，而是让基本块尽可能大。

```
传统 CFG:
  instr1           ; block 1
  instr2(may_throw) ; block 1 在此终止（因为可能抛异常）
  ---
  instr3           ; block 2

Factored CFG:
  instr1           ; block 1
  instr2(may_throw)
  instr3
  ; block 1 包含多条指令
  ; 异常边从 factored exit edge 连接到异常 handler
```

好处：
- 更大的基本块 → 数据流分析更高效（更少的 block 边界）
- 类型信息可以排除异常可能性 → 在优化阶段可以删除异常边
- JIT 阶段更容易做指令调度

**AOT 借鉴优先级: P4**

对于 AOT 编译器（翻译到 C++ 而非直接生成机器码），CFG 主要由 GCC 处理。但在函数着色（function coloring）和 SSA 分析中，更精确的异常建模可以参考。

---

### 2.9 并行分析（Parallel Analysis）

**文件**: `hphp/hhbbc/parallel.cpp` (69 行), `README`

HHBBC 的不动点迭代中，每个 work unit 的分析可以**完全并行**：

```
线程安全模型:
- 分析阶段：只能读 Index（内部线程安全）+ 读 php 元数据（不可变）
- 合并阶段：单线程更新 Index（不需要锁）
```

这利用了 "Index 信息永远不会错误" 的特性——即使两个线程基于不同版本的 Index 进行分析，合并结果也不会产生错误信息。

**AOT 借鉴优先级: P3**

与 KPHP 的流水线并行类似。在 AOT 中，可以并行分析独立函数/类。需要注意的是 Index 合并必须串行（或使用 lock-free 结构）。

---

### 2.10 Public Static Property 优化

**文件**: `hphp/hhbbc/index.cpp`

HHBBC 追踪每个公共静态属性在整个程序中的 mutation：

```
- 初始状态：保守假设（可能被任何函数修改）
- 每轮分析中，记录哪些函数修改了哪些 static prop
- 如果一个 static prop 在分析中从未被修改 → 可以常量折叠
- 如果一个 static prop 只被赋值一种类型 → 类型可以收窄
```

这比简单的 "是否有写入" 分析更精确，因为它是**全程序**分析，能看到跨文件的 mutation。

**AOT 借鉴优先级: P2**

当前 AOT 中，公共静态属性总是使用 `Variant`（mixed 类型）。通过全程序分析，可以将只在内部赋值的静态属性优化为精确 C++ 类型。

---

## 三、工具链分析

### 3.1 测试基础设施

HHVM 有庞大的测试体系：

| 层级 | 目录 | 数量 | 用途 |
|------|------|------|------|
| Quick test | `hphp/test/quick/` | 865 个 .php | 快速回归测试 |
| Slow test | `hphp/test/slow/` | 7,927 个 .php | 全面功能/性能测试 |
| Zend test | `hphp/test/zend/` | ~4,500 个 | PHP 兼容性测试（来自 php-src） |
| Ext test | `hphp/test/ext/` | ~800 个 | 扩展功能测试 |
| Server test | `hphp/test/server/` | ~100 个 | HTTP/RPC 集成测试 |
| HHBBC unit test | `hphp/hhbbc/test/` | 3 个 C++ 文件 | 编译器内部测试 |
| **总计** | | **~14,675 个** | |

测试运行器特点：
- Quick vs Slow 分层：Quick（~1 秒运行）用于 pre-commit，Slow（~10 分钟）用于 CI
- 支持多种运行模式：interp / JIT / hhbbc + JIT / RepoAuthoritative
- Zend 测试：直接复用 php-src 官方测试，验证 PHP 兼容性

**AOT 借鉴:**
- Quick/Slow 分层测试策略——将现有 `tests/compiler/` 按运行时间分类
- 直接复用 php-src 官方 PHPT 测试——验证 AOT 编译器的 PHP 行为兼容性
- HHBBC 内部单元测试太少（仅 3 个），不应效仿——AOT 的 PHPUnit 测试覆盖更好

### 3.2 Hack 类型检查器

HHVM 的 Hack 语言有完整的**静态类型检查器**（`hphp/hack/`），与编译器独立运行：

- 编译期类型标注（`int`, `string`, `vec<T>`, `dict<TK,TV>`, `shape(...)`）
- 渐进类型（gradual typing）：可以从无标注逐步迁移
- IDE 集成（LSP 协议支持）
- 类型覆盖率追踪

**AOT 借鉴:**
- 当前 AOT 使用 `@phpstan-type` 等注解，可以集成 phpstan 做编译前的类型检查
- 类型覆盖率是一个有用的度量：X% 的函数/变量有精确的类型标注

### 3.3 Tracing / Debug 基础设施

HHVM 有丰富的调试和追踪：
- `TRACE_SET_MOD(hhbbc)` — 模块级条件日志（编译期开关）
- `hphp/tools/` — 多种分析工具（字节码查看器、profiler 等）
- `debug.cpp` — 类型/状态的可读化打印

**AOT 借鉴:**
- 当前 `-vv` 详细输出可以借鉴 TRACE_MODULE 方式，按模块过滤日志
- 类型分析结果的可视化可以辅助调试优化器

### 3.4 RepoAuthoritative 模式

HHVM 支持**编译一次，部署多次**的模式：

```
源码 → HHBBC 全程序分析 → 优化后字节码 Repo → 多进程直接加载 Repo
```

- Repo 中存储**预分析的类型信息**（RepoAuthType）
- 运行时不需要再次做类型推断
- 字节码级别的 interning (string/class/function id)
- 多进程共享同一个 Repo（mmap）

**AOT 借鉴: P4**

当前 AOT 直接生成 `.cc` 文件并编译为二进制，已经在 "编译一次，部署多次" 的路径上。但 Repo 的 mmap 共享思想可以用于多进程环境下的常量池共享（而非每个进程独立加载）。

---

## 四、类型系统兼容性分析

### 4.1 Hack vs PHP vs AOT Compiler

| 特性 | PHP 8.2 | Hack | AOT Compiler |
|------|---------|------|-------------|
| 基础类型 | mixed, int, string, float, bool, array, null, void, never | int, string, float, bool, null, void, noreturn, mixed, dynamic, nonnull, nothing | 同 PHP |
| 联合类型 | `int\|string` | `int\|string` (但实践中不鼓励) | 支持 |
| 交叉类型 | `X&Y` (8.1+) | 通过 `where` 约束 | 不支持 |
| 泛型 | 无 | `vec<T>`, `dict<TK,TV>`, `class Box<T>` | `std::vector<T>` (C++ 原生) |
| 数组类型 | `array` | `vec<T>`, `dict<TK,TV>`, `keyset<T>` | `php::array` |
| Shapes | 无 | `shape('x' => int, 'y' => string)` | 无 (建议用 object) |
| 枚举 | enum (8.1) | enum, enum class (带 label) | 支持 PHP 8.1 enum |
| 可空类型 | `?int` | `?int` (仅函数参数) | 支持 |
| nothing / bottom | 无 | 有 (空函数返回, unreachable) | 无 |
| dynamic | 无 | 有 (选择性放弃类型检查) | 无 |

### 4.2 类型格的关键差异

Hack/HHBBC 的类型系统比 PHP 丰富得多：

```
HHBBC 类型格:

          Cell (any value)
            |
    InitCell (not Uninit)
      |           |
   Prim        Boxed types (Obj, Res, ...)
     |
  InitPrim (not null)
  |    |    |
Num  Bool  Str/ArrKey
|  |
Int Dbl

底部: Bottom (无值 - unreachable)
顶部: Cell (任何值)
```

特性：
- **Bottom**: 表示不可达路径的类型，可以消除 dead code
- **Counted/Uncounted**: 静态字符串 vs 运行时分配字符串，不同生命周期
- **Array shapes**: `dict<'name' => string, 'age' => int>` 是独立类型
- **Wait handle**: `WaitH<T>` 表示异步结果的类型

### 4.3 Hack 的渐进类型设计

Hack 从 PHP 演进而来，支持渐进迁移：
- `mixed` — 任何类型（等同于无标注 PHP）
- `dynamic` — 任何类型，且不报类型错误（更宽松的 mixed）
- `<<__Soft>>` — 软类型提示（运行时不强制检查）

这种设计允许大型代码库逐步添加类型标注，避免 "全有或全无"。

### 4.4 与 AOT 编译器的语法差异

| 特性 | HHBBC 输入 (HHAS) | AOT Compiler |
|------|------|-------------|
| 基础 PHP 版本 | Hack (PHP 5.6 分支) | PHP 8.2+ |
| 类型标注 | 强制（Hack 语言要求） | 可选 (phpstan 注解) |
| 泛型 | `vec<T>`, `dict<K,V>`, 自定义泛型类 | 无原生泛型 |
| Lambda / 闭包 | `$x ==> $x + 1` (short lambda) | PHP 闭包 (`function($x) { return $x + 1; }`) |
| async / await | 原生支持 (WaitHandle) | 无 |
| Shapes | `shape('x' => int)` | 无 |
| Enum class | 支持（带 label 的 enum class） | 仅 PHP 8.1 enum |
| XHP | HTML 模板语法 | 无 |
| Case types | `case type T = int \| string` | 无 |

---

## 五、总结与优先级建议

| 优先级 | 技术 | 难度 | 收益 | 说明 |
|--------|------|------|------|------|
| **P0** | 全程序不动点分析 | 极高 | 极高 | 跨文件类型推导、返回类型收窄、消除伪动态调用 |
| **P0** | Trep 类型格 | 中 | 极高 | 精确联合类型、不可数标记、非空标记，提升所有优化的精度 |
| **P1** | 抽象解释器 | 高 | 高 | 替代/增强当前 SSA 分析，支持分支类型收窄、常量传播 |
| **P1** | Index 依赖追踪 | 高 | 高 | 全程序分析的基础，增量编译 |
| **P2** | 类型感知 DCE | 中 | 中 | 消除更多死代码，减小生成 C++ 代码体积 |
| **P2** | Public Static Prop 优化 | 中 | 中 | 全局静态属性类型精确化 |
| **P3** | RepoAuthType 存储 | 低 | 中 | 产物中嵌入精确类型信息（对 C++ 生成阶段意义有限） |
| **P3** | 并行分析 | 高 | 中 | 大型项目编译提速 |
| **P4** | DataType 编码优化 | 低 | 低 | 对 phpx Variant 类型检查微优化 |
| **P4** | Factored CFG | 高 | 低 | C++ 编译器已处理 CFG 优化 |
| **P5** | Repo mmap 共享 | 高 | 低 | 特定部署场景优化 |

### 关键认识

1. **HHBBC 的全程序分析是其最大的差异化优势**——AOT 编译器最大的架构差距就在于此。单文件分析无法看到跨文件的类型信息，导致很多调用被迫使用动态分发。

2. **类型系统是优化的核心引擎**——HHBBC 的 trep + specialization + monotonic lattice 投入巨大（约 12k 行），但这正是所有优化 pass 的质量基础。

3. **依赖追踪是实现增量分析的关键**——Index 自动记录的 "谁查询了什么" 信息，使得只需重新分析受影响的函数，避免全程序每次重新分析。

4. **factored CFG 和 DataType 编码更偏 JIT 场景**——这些设计为运行时 JIT 优化，AOT 编译器生成 C++ 源码，由 GCC/Clang 处理这些底层优化，不应重复投资。

5. **测试基础设施值得学习**——Quick/Slow 分层、直接复用 php-src 测试、多运行模式，对 AOT 编译器的测试 CI 建设有直接参考价值。

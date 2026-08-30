# PHP Zend Optimizer / OPcache / JIT 设计分析：可引入 AOT 编译器的机制

本文档分析了 php-src (v8.4.14) 中 Zend Optimizer、OPcache 和 JIT 的实现，识别出可引入 AOT 编译器的设计模式、算法和数据流框架。

源码位置:
- 优化器: `~/soft/php/php-8.4.14/Zend/Optimizer/`
- OPcache: `~/soft/php/php-8.4.14/ext/opcache/`
- JIT: `~/soft/php/php-8.4.14/ext/opcache/jit/`

---

## 概览

PHP 的优化器是本领域最成熟的动态语言高端优化器之一。它包含完整的 SSA (Static Single Assignment) 构建、e-SSA (Extended SSA) 类型/范围推断、SCCP (Sparse Conditional Constant Propagation)、逃逸分析、死代码消除、调用图分析、profile-guided tracing JIT 等完整编译器优化基础设施。

AOT 编译器可以从以下方面借鉴：

| 优先级 | 设计/模块 | 实施量级 | 收益 |
|--------|----------|---------|------|
| P1 | Pass Pipeline 架构 | ~100 行框架 | 架构清晰、可插拔、分 O0/O1/O2 |
| P1 | SSA + e-SSA 构建 | 中 | 所有高级优化的基础 |
| P1 | 类型推断 (Type & Range Inference) | 中 | 精确的类型推导能力 |
| P2 | SCDF 通用数据流框架 | ~300 行 | 可复用于 SCCP/类型推断/优化 |
| P2 | SCCP 常量传播 | 中 | 条件常量折叠 + 不可达代码消除 |
| P2 | DCE 死代码消除 | ~400 行 | worklist 驱动的精确 DCE |
| P3 | 逃逸分析 (Escape Analysis) | ~500 行 | 栈分配、消除引用计数 |
| P3 | 调用图 (Call Graph) | ~400 行 | 跨函数分析、内联决策、死代码 |
| P4 | JIT IR 框架 | 极重 | 参考其 IR 设计的抽象层次 |
| P4 | OPcache 持久化/File Cache | 轻 | 序列化优化后的 IR 缓存 |

---

## 1. Pass Pipeline 架构

### 设计

PHP 定义了 16 个优化 Pass，每个 Pass 对应一个 bitmask 位（`zend_optimizer.h:28-46`）：

```c
#define ZEND_OPTIMIZER_PASS_1      (1<<0)   // 简单局部优化 (常量替换/折叠)
#define ZEND_OPTIMIZER_PASS_2      (1<<1)   //
#define ZEND_OPTIMIZER_PASS_3      (1<<2)   // Jump 优化
#define ZEND_OPTIMIZER_PASS_4      (1<<3)   // INIT_FCALL_BY_NAME -> DO_FCALL
#define ZEND_OPTIMIZER_PASS_5      (1<<4)   // CFG 优化 (block pass)
#define ZEND_OPTIMIZER_PASS_6      (1<<5)   // DFA 优化 (type/range inference → 单函数)
#define ZEND_OPTIMIZER_PASS_7      (1<<6)   // CALL GRAPH 优化 (跨函数分析)
#define ZEND_OPTIMIZER_PASS_8      (1<<7)   // SCCP (常量传播)
#define ZEND_OPTIMIZER_PASS_9      (1<<8)   // 临时变量优化
#define ZEND_OPTIMIZER_PASS_10     (1<<9)   // NOP 移除
#define ZEND_OPTIMIZER_PASS_11     (1<<10)  // 合并相同常量
#define ZEND_OPTIMIZER_PASS_12     (1<<11)  // 调整栈使用
#define ZEND_OPTIMIZER_PASS_13     (1<<12)  // 移除未使用变量
#define ZEND_OPTIMIZER_PASS_14     (1<<13)  // DCE (死代码消除)
#define ZEND_OPTIMIZER_PASS_15     (1<<14)  // 收集常量 (unsafe)
#define ZEND_OPTIMIZER_PASS_16     (1<<15)  // 函数内联
```

### 流水线调度

`zend_optimize()` 函数 (`zend_optimizer.c:1067-1183`) 按顺序执行各个 pass，每个 pass 只处理已执行 pass 产生的结果：

```
pass1 (常量折叠) → pass3 (跳转优化) → pass4 (函数调用优化)
  → pass5 (CFG) → pass6 (DFA + 类型推断) → pass9 (临时变量)
  → pass10 (NOP移除) → pass11 (常量合并) → pass13 (变量清理) → ...
```

当 `PASS_6 + PASS_7` 同时启用时，`zend_optimize_script()` 走更复杂的调用图路径：
```
build_call_graph → zend_optimize (per-func) → analyze_call_graph
  → 构建 call_map → dfa_analyze_op_array (per-func)
  → dfa_optimize_op_array (per-func, with call context)
  → pass9 → pass11 → pass13 → pass12 (stack adjust) → redo_pass_two
```

### 关键设计点

**1. Bitmask 开关 + 注册式 Pass**

用户可以按 bitmask 组合任意 pass。同时支持 `zend_optimizer_register_pass()` 注册外部 pass（如 JIT 自己的优化 pass）：

```c
static struct {
    zend_optimizer_pass_t pass[ZEND_OPTIMIZER_MAX_REGISTERED_PASSES];
    int last;
} zend_optimizer_registered_passes;
```

注册的 pass 在所有内置 pass 之后执行（`zend_optimizer_call_registered_passes`）。

**2. 每个 pass 可选 dump 输出**

通过 `debug_level` 控制，支持在任意 pass 前后输出中间表示，用于调试和性能分析。

**3. Per-function 和 script-level 双层优化**

- `zend_optimize(op_array, ctx)` — 单函数优化，保守（不利用跨函数信息）
- `zend_optimize_script(script, ...)` — 全脚本优化 with call graph，可做跨函数优化

### AOT 借鉴

AOT 编译器可以定义类似的 pass pipeline：

```php
enum AotPass: int {
    case CONSTANT_FOLD       = 1 << 0;
    case TYPE_CHECK_INSERT   = 1 << 1;
    case ESCAPE_ANALYSIS     = 1 << 2;
    case DEVIRTUALIZE        = 1 << 3;
    case DEAD_CODE_ELIM      = 1 << 4;
    case FUNCTION_INLINE     = 1 << 5;
    case LOOP_OPTIMIZE       = 1 << 6;
    case BOX_ALLOC_ELIM      = 1 << 7;
}
```

按优化级别组合：

```php
const O0 = AotPass::TYPE_CHECK_INSERT->value;  // 必需的基础代码生成
const O1 = O0 | AotPass::CONSTANT_FOLD->value;  // 基础优化
const O2 = O1 | AotPass::DEVIRTUALIZE->value | AotPass::FUNCTION_INLINE->value;
```

---

## 2. SSA (Static Single Assignment) + e-SSA

### 数据结构

SSA 构建在控制流图 (CFG) 之上：

**CFG (`zend_cfg.h:84-92`):**
```c
typedef struct _zend_cfg {
    int               blocks_count;       // 基本块数量
    int               edges_count;        // 边数量
    zend_basic_block *blocks;             // 基本块数组
    int              *predecessors;       // 前驱列表
    uint32_t         *map;                // opnum → block 映射
    uint32_t          flags;
} zend_cfg;

typedef struct _zend_basic_block {
    int              *successors;         // 后继块索引
    uint32_t          flags;
    uint32_t          start;              // 起始 opcode
    uint32_t          len;                // opcode 数
    int               successors_count;
    int               predecessors_count;
    int               idom;               // 立即支配者
    int               loop_header;        // 最近循环头
    int               level;              // 支配树深度
    int               children;           // 支配的子块链表
} zend_basic_block;
```

**SSA (`zend_ssa.h:135-143`):**
```c
typedef struct _zend_ssa {
    zend_cfg               cfg;           // 控制流图
    int                    vars_count;    // SSA 变量数
    int                    sccs;          // 强连通分量数
    zend_ssa_block        *blocks;       // 每基本块的 φ 函数
    zend_ssa_op           *ops;          // 每指令的 use-def 信息
    zend_ssa_var          *vars;         // 每个 SSA 变量的 def-use 链
    zend_ssa_var_info     *var_info;     // 类型推断结果 (类型位掩码 + 范围)
} zend_ssa;
```

**SSA Op (`zend_ssa.h:82-92`):**
```c
typedef struct _zend_ssa_op {
    int op1_use;
    int op2_use;
    int result_use;
    int op1_def;        // 这个指令定义的 SSA 变量
    int op2_def;
    int result_def;
    int op1_use_chain;  // use-def 链
    int op2_use_chain;
    int res_use_chain;
} zend_ssa_op;
```

### e-SSA: Extended SSA with Pi Nodes

这是 PHP 优化器最精妙的设计之一。Pi node 是一种特殊的 φ 函数，用于表示从条件分支推断出的类型/范围约束。

**Pi 约束 (`zend_ssa.h:42-59`):**
```c
typedef struct _zend_ssa_range_constraint {
    zend_ssa_range         range;       // 范围约束 [min, max]
    int                    min_var;     // 符号范围下界变量
    int                    max_var;     // 符号范围上界变量
    zend_ssa_negative_lat  negative;    // 否定潜力
} zend_ssa_range_constraint;

typedef struct _zend_ssa_type_constraint {
    uint32_t               type_mask;   // 类型掩码 (与操作后得到的窄化类型)
    zend_class_entry      *ce;          // 类条目 (for instanceof)
} zend_ssa_type_constraint;

typedef union _zend_ssa_pi_constraint {
    zend_ssa_range_constraint range;
    zend_ssa_type_constraint type;
} zend_ssa_pi_constraint;
```

**工作原理：** 对于 `if ($x > 0)` 这样的条件：
- 在 truthy 分支，插入 `Pi($x, range[1, LONG_MAX])` — 将 `$x` 的 SSA 变量限定在 > 0 的范围
- 在 falsy 分支，插入 `Pi($x, range[LONG_MIN, 0])` — 将 `$x` 限定在 ≤ 0

这允许分支内使用精化的类型/范围信息进行后续优化，而不改变原变量的显式赋值链。

### SSA 构建流程

```
1. zend_build_cfg()     → 构建控制流图 (含支配树、循环检测)
2. zend_build_dfg()     → 构建数据流图 (计算 use/def sets)
3. zend_build_ssa()     → 放置 φ 函数 → 重命名变量 → 构建 SSA 形式
4. zend_ssa_compute_use_def_chains() → 连接 use-def 链
5. zend_ssa_find_sccs() → 找强连通分量 (用于类型推断)
6. zend_ssa_inference() → 类型推断 + 范围推断 (填充 var_info)
```

### AOT 借鉴

AOT 编译器不需要 SSA 形式（因为生成的是 C++ 代码，不是直接操作寄存器），但 e-SSA 的以下概念可以直接用：

1. **Pi 约束的概念：** 在条件分支中插入类型窄化标记，使分支内变量具有更精确的类型。这直接对应前文 TypeSpecifier / Type Narrowing (#7) 的实现基础。

2. **Type & Range 信息关联到每个表达式：** 类似 SSA `var_info` 的设计，可以在 AOT 的 FunctionContext 中为每个变量/表达式维护 `{type_mask, range, ce}` 三元组。

3. **Use-def 链用于优化判断：** 当需要判断一个变量是否有且仅有一个 `use` 时，SSA 的 use_chain 提供了 O(1) 查询。

---

## 3. 类型推断 (Type & Range Inference)

### 类型系统

PHP 使用位掩码表示类型信息（`zend_type_info.h` 定义），这是最具特色的设计：

```c
#define MAY_BE_UNDEF          (1<< 0)
#define MAY_BE_NULL           (1<< 1)
#define MAY_BE_FALSE          (1<< 2)
#define MAY_BE_TRUE           (1<< 3)
#define MAY_BE_LONG           (1<< 4)
#define MAY_BE_DOUBLE         (1<< 5)
#define MAY_BE_STRING         (1<< 6)
#define MAY_BE_ARRAY          (1<< 7)
#define MAY_BE_OBJECT         (1<< 8)
#define MAY_BE_RESOURCE       (1<< 9)
#define MAY_BE_REFERENCE      (1<<10)
#define MAY_BE_CALLABLE       (1<<11)
#define MAY_BE_ITERABLE       (1<<12)
#define MAY_BE_VOID           (1<<13)
#define MAY_BE_INDIRECT       (1<<14)

// 方便组合
#define MAY_BE_ANY            (MAY_BE_NULL|MAY_BE_FALSE|MAY_BE_TRUE|...)
#define MAY_BE_TRUTHY         (MAY_BE_TRUE|MAY_BE_LONG|...  /* 非 0/''/[]/null */)
#define MAY_BE_FALSEY         (MAY_BE_UNDEF|MAY_BE_NULL|MAY_BE_FALSE|...)
```

**核心优势：** 位运算极快。类型运算（合并/交集/差集）仅需一条 AND/OR/NOT 指令：

```c
// 合并两个变量的类型
uint32_t result_type = info1 | info2;

// 检查是否可能为 string
if (info & MAY_BE_STRING) { ... }

// 交集
uint32_t common = info1 & info2;
```

### 范围推断

每个 SSA 变量附带 `zend_ssa_range { min, max, underflow, overflow }`：

```c
typedef struct _zend_ssa_range {
    zend_long  min;
    zend_long  max;
    bool  underflow;  // 是否有下溢风险
    bool  overflow;   // 是否有上溢风险
} zend_ssa_range;
```

核心算法（`zend_inference.c:1071`）基于 V. Campos 的 "Speed and Precision in Range Analysis, SBLP'12" 论文：

1. **Warmup 阶段 (16 pass):** 在 SCC（强连通分量）上传播范围，使用 widening 加速收敛
2. **Narrowing 阶段:** 将范围逐渐收窄，消除 widening 造成的过度近似
3. **Zend Engine 专门的算术语义:** `zend_add_will_overflow()`, `zend_sub_will_overflow()` 等精确检测整数溢出

**运算符范围推断示例：**

```c
// ADD: 结果范围
min = OP1_MIN() + OP2_MIN()
max = OP1_MAX() + OP2_MAX()
overflow = OP1_RANGE_OVERFLOW() || OP2_RANGE_OVERFLOW()
         || zend_add_will_overflow(OP1_MAX(), OP2_MAX())

// 结果类型: 如果 overflow 为真，类型增加 MAY_BE_DOUBLE
// (PHP int 溢出自定转为 float)
```

### 每个 Opcode 的 `update_type_info`

`_zend_update_type_info()` 是一个巨大的 switch，针对每个 Zend opcode 精确计算结果类型和范围。例如 `ZEND_ASSIGN_DIM`（数组赋值），不仅更新被赋值元素的类型，还更新数组整体的类型，考虑 MAY_BE_PACKED_GUARD（打包数组守卫）和引用计数推断。

### AOT 借鉴

1. **位掩码类型系统：** 是最适合 AOT 编译器的轻量类型表示方案。AOT 当前用字符串类型（`TYPE_INT = 'int'`），无法高效地进行"可能是 int 或 string"这种复合表示。位掩码提供了 O(1) 的 union/intersect/test 操作。

2. **范围推断：** 可为 C++ 代码生成选择最优的整数类型（`int32_t` vs `int64_t` vs `BigInt`），避免不必要的 BigInt 分配。

3. **Overflow 追踪：** 精确判定何时需要从 int64 转为 float/BigInt，只在真正可能溢出时才插入转换代码。

4. **每个 opcode 的类型更新表：** `_zend_update_type_info()` 的设计可以直接映射到 AOT 的 Rule 系统中——每个 opcode 对应一个 Rule，负责输出该操作的结果类型。

---

## 4. SCCP (Sparse Conditional Constant Propagation)

### 核心设计

SCCP 同时做**常量传播**和**条件常量折叠**，还可以消除不可达代码（不需要额外的死代码消除 pass）。

实现在 `sccp.c`，基于 `scdf.h`（SCDF 框架）。

### 值格 (Value Lattice)

```
    TOP (未定义)
   / | \
  C1 C2 C3  (常量值)
   \ | /
    BOT (过定义 = 非常量)
```

- TOP: 还不知道这个变量的值（乐观假设）
- BOT: 已知这个变量不是常量
- 常量值: 已知精确值

### 算法关键点 (来自 sccp.c:30-74 的注释)

**`meet` 操作 (φ 函数的合并):**
- BOT + any = BOT
- TOP + any = any
- C_i + C_i = C_i (两个相同常量)
- C_i + C_j = BOT (两个不同常量)

**指令求值:**
- 任何操作数是 BOT → 结果是 BOT (例外: ASSIGN 的 op1)
- 永远不能求值的指令 → BOT
- 任何操作数是 TOP → 结果是 TOP
- 所有操作数都是已知常量 → 尝试编译时求值 → 成功返回常量值，失败返回 BOT

**分支可行性判断:**
- 分支在 BOT 上 → 所有后继可行
- 分支在 TOP 上 → 都没有后继不可行（等待更多信息）
- 分支在已知常量上 → 只有满足条件的分支可行

### SCDF 框架 (`scdf.h`)

SCCP 被构建在 SCDF (Sparse Conditional Data Flow) 框架之上，这是一个通用的稀疏条件数据流分析引擎：

```c
typedef struct _scdf_ctx {
    zend_op_array *op_array;
    zend_ssa *ssa;
    zend_bitset instr_worklist;       // 待处理的指令
    zend_bitset phi_var_worklist;    // 待处理的 Phi/SSA 变量
    zend_bitset block_worklist;      // 待处理的块
    zend_bitset executable_blocks;   // 可执行块
    zend_bitset feasible_edges;      // 可行边

    struct {
        void (*visit_instr)(...);       // 处理一条指令
        void (*visit_phi)(...);         // 处理一个 φ 函数
        void (*mark_feasible_successors)(...);  // 标记可行后继
    } handlers;
} scdf_ctx;
```

**使用模式：** SCCP 实现了 `visit_instr` (常量求值)、`visit_phi` (meet 操作)、`mark_feasible_successors` (分支可行性)。类型推断也用类似的 worklist 传播算法。

**通用 worklist 机制:**
```c
// 当一个变量的值改变时，将它的所有 use 加入 worklist
static inline void scdf_add_to_worklist(scdf_ctx *scdf, int var_num) {
    const zend_ssa_var *var = &ssa->vars[var_num];
    int use;
    FOREACH_USE(var, use) {
        zend_bitset_incl(scdf->instr_worklist, use);  // 标记使用该变量的指令
    }
    FOREACH_PHI_USE(var, phi) {
        zend_bitset_incl(scdf->phi_var_worklist, phi->ssa_var);
    }
}
```

### AOT 借鉴

1. **SCDF 框架是最直接可借鉴的**：约 300 行 C 代码，提供了一个通用的 worklist 驱动的条件数据流引擎。AOT 可以移植为 PHP 类，复用于 SCCP、类型推断、逃逸分析等多项优化 pass。

2. **TOP/BOT 格模型：** AOT 编译器在分析类型时可以使用相同的格结构：
   - TOP = 未知类型 (分析初期)
   - BOT = 矛盾类型 (发现了不一致)
   - 具体值 (常量或确切类型)

3. **条件分支可行性：** SCCP 的分支可行性判断可以直接帮助 AOT 在编译时消除不可达分支，生成更简化的 C++ 代码。

---

## 5. DCE (Dead Code Elimination)

### 算法 (`dce.c`)

PHP 的 DCE 采用乐观策略：

```
1. 假设所有指令和 φ 函数都是 dead
2. 标记所有有明显副作用的指令为 live (side-effect instruction)
3. 从 live 指令出发，标记其操作数的定义指令为 live (use-def 链反向传播)
4. 重复直到 worklist 为空
5. 删除所有仍标记为 dead 的指令
```

**关键判断 `may_have_side_effects()` (`dce.c:74-100`):**

将 Zend opcode 分为三种：
- 永远无副作用（如 ADD、CONCAT、BOOL_NOT）：可以被 DCE 消除
- 可能产生 notice 但无本质副作用（如 DIV_BY_ZERO 触发 warning）：可配置是否消除
- 永远有副作用（如 ECHO、THROW、ASSIGN_OBJ）：必须保留

**特别能力：** 可以消除"非逃逸数组/对象的冗余修改"以及"无用的数组/对象分配"。如果一个数组只在局部被构建、修改和使用，中间步骤的 ASSIGN_DIM 可能被消除。

### AOT 借鉴

AOT 编译器的 DCE 可以更激进（因为编译时已知类型）：

1. **副作用分类矩阵：** 为 AOT 的表达式/语句类型建立副作用表格，精确标记哪些操作必须保留
2. **逃逸感知 DCE：** 结合逃逸分析，消除未逃逸对象的操作——这是 AOT 最大的优化机会之一
3. **Control-dependence based DCE：** PHP 明确表示当前的 DCE 没有考虑控制依赖（`dce.c:35-39` 的注释），AOT 可以做更精确的控制依赖 DCE

---

## 6. 逃逸分析 (Escape Analysis)

### 算法 (`escape_analysis.c`)

基于 Kotzmann & Mossenbock (PPPJ'05) 的经典逃逸分析算法。

**核心步骤：**

1. **构建等价逃逸集 (`zend_build_equi_escape_sets`):** 使用 Union-Find 算法。如果两个 SSA 变量通过 φ 函数或 ASSIGN 关联（值相同），它们属于同一个等价类。

2. **逃逸状态传播:** 每个等价类有四种状态：
   ```
   ESCAPE_STATE_UNKNOWN      → 初始状态（零初始化的 C 内存）
   ESCAPE_STATE_NO_ESCAPE    → 确定不逃逸（最终目标）
   ESCAPE_STATE_FUNCTION_ESCAPE → 逃逸到被调用函数（传入参数）
   ESCAPE_STATE_GLOBAL_ESCAPE → 全局逃逸（返回、赋值到全局变量、抛异常等）
   ```

3. **状态单调收敛:** 状态只能从 UNKNOWN → NO_ESCAPE/FUNCTION_ESCAPE/GLOBAL_ESCAPE，从不反转。

4. **应用逃逸信息:**
   - 不逃逸的数组可以在栈上分配（不需要堆分配）
   - 不逃逸的对象可以避免引用计数操作
   - 不逃逸的变量不需要分离（ZEND_SEPARATE）

**前驱/后继边:** 支持符号类型别名（SYMTABLE_ALIAS）和 HTTP 响应头别名（HTTP_RESPONSE_HEADER_ALIAS）。

### AOT 借鉴

逃逸分析对 AOT 编译器可能价值最大：

1. **Box allocation 消除：** AOT 使用 `Box<T>` 表示对象引用。逃逸分析可以确认哪些 Box 不需要堆分配，直接在栈上创建。

2. **引用计数消除：** 不逃逸的对象可以跳过 `php::Object::Ref()` / `php::Object::Unref()` 操作。

3. **Array 栈分配：** 局部数组逃逸分析后可以改用栈上的 `zend_array`。

4. **4 状态模型非常简单有效**，AOT 可以直接映射：
   - ESCAPE_STATE_NO_ESCAPE → 栈分配
   - ESCAPE_STATE_FUNCTION_ESCAPE → 调用者决定
   - ESCAPE_STATE_GLOBAL_ESCAPE → 堆分配

---

## 7. 调用图 (Call Graph)

### 设计 (`zend_call_graph.h`)

PHP 的调用图同时追踪 caller → callee 和 callee → caller 的双向关系：

```c
struct _zend_call_info {
    zend_op_array    *caller_op_array;     // 调用者
    zend_op          *caller_init_opline;  // INIT_FCALL 指令
    zend_op          *caller_call_opline;  // DO_FCALL 指令
    zend_function    *callee_func;         // 被调用函数
    zend_call_info   *next_caller;         // 链表: callee 的下一个 caller
    zend_call_info   *next_callee;         // 链表: caller 的下一个 callee
    bool              recursive;           // 递归调用
    bool              send_unpack;         // 使用 SEND_UNPACK
    bool              named_args;          // 命名参数
    bool              is_prototype;        // 可能是子类重写的方法
    bool              is_frameless;        // frameless 函数
    int               num_args;
    zend_send_arg_info arg_info[1];
};

struct _zend_func_info {
    zend_ssa           ssa;               // 函数自身的 SSA
    zend_call_info    *caller_info;       // 谁调用了这个函数
    zend_call_info    *callee_info;       // 这个函数调用了谁
    zend_call_info    **call_map;         // 从 opnum 快速索引 call_info
    zend_ssa_var_info  return_info;       // 推断出的返回类型
};
```

**关键功能:**

1. **双向图:** `caller_info` 和 `callee_info` 分别是不同的链表，支持向上（从 callee 找 caller）和向下（从 caller 找 callee）遍历
2. **call_map:** 数组索引 opnum → call_info，O(1) 查找某个 opcode 位置对应的调用信息
3. **返回类型传播:** 被调用函数的 return_info 可以向上传播到调用者的 return_info
4. **参数类型传播:** 调用者的实参类型可以向下传播到被调用者的参数类型（用于更精确的函数体优化）

### 高级跨函数优化 (`zend_optimize_script:1626-1728`)

```
1. build_call_graph        → 构建双向调用图
2. zend_optimize (per-func) → 每个函数做独立的 local optimization
3. analyze_call_graph      → 推断函数信息 (递归标记、间接变量访问、func_get_args 等)
4. build_call_map          → 为每个函数构建 opnum→call 的索引
5. dfa_analyze_op_array    → 构建 SSA + 类型推断 (per-func)
6. dfa_optimize_op_array   → 基于 SSA 做 SCCP + DCE + block pass
```

### AOT 借鉴

AOT 编译器的前两步（prepare + convert）天然构建了完整的符号依赖图。可以在此之上增加：

1. **call_map 索引：** 从每个 call site 快速查找 callee 的元信息（参数类型、返回类型、是否内联候选）
2. **返回类型双向传播：** 目前 AOT 的返回类型推导是自顶向下的；调用图允许从 callee 的已知返回类型回传给 caller
3. **递归标记：** `ZEND_FUNC_RECURSIVE_DIRECTLY` / `ZEND_FUNC_RECURSIVE_INDIRECTLY` 对内联策略决策至关重要

---

## 8. JIT IR 框架

### 设计

PHP JIT 使用的 IR（Intermediate Representation）是一个通用的 SSA 派生的低级中间表示，位于 `ext/opcache/jit/ir/`。

**IR 的三个阶段:**

| 阶段 | 文件 | 作用 |
|------|------|------|
| IR builder | `ir_builder.h`, `zend_jit_ir.c` | 从 Zend 字节码构建 IR 指令 |
| IR optimizer | `ir_cfg.c`, `ir_fold.h`, `ir_gcm.c` | CFG 优化、常量折叠、全局代码移动 (GCM) |
| IR emitter | `ir_emit.c`, `ir_emit_x86.h` | 从 IR 发射 x86/ARM64 机器码 |

**IR 指令示例: IR_ADD, IR_MUL, IR_LOAD, IR_STORE, IR_CALL, IR_GUARD, etc.**

**JIT 优化级别 (`zend_jit.h:32-37`):**
```c
#define ZEND_JIT_LEVEL_NONE        0     // 不启用 JIT
#define ZEND_JIT_LEVEL_MINIMAL     1     // 最小 JIT (子程序线程化)
#define ZEND_JIT_LEVEL_INLINE      2     // 选择性内联线程化
#define ZEND_JIT_LEVEL_OPT_FUNC    3     // 基于类型推断优化单个函数
#define ZEND_JIT_LEVEL_OPT_FUNCS   4     // 基于调用树优化
#define ZEND_JIT_LEVEL_OPT_SCRIPT  5     // 过程间分析
```

**JIT 触发模式 (`zend_jit.h:39-44`):**
```c
#define ZEND_JIT_ON_SCRIPT_LOAD    0  // 所有函数加载时立即编译
#define ZEND_JIT_ON_FIRST_EXEC     1  // 首次执行时编译
#define ZEND_JIT_ON_PROF_REQUEST   2  // 根据 profile 数据编译最热的函数
#define ZEND_JIT_ON_HOT_COUNTERS   3  // N 次调用/循环迭代后编译
#define ZEND_JIT_ON_HOT_TRACE      5  // N 次调用后走 tracing JIT
```

### AOT 借鉴

1. **IR 作为 AST→C++ 的中间载体：** AOT 当前直接从 AST 生成 C++ 代码。引入 IR 层可以：
   - 在 IR 层面做优化（fold、GCM、寄存器分配模拟）
   - 解耦前端（PHP AST）和后端（C++ codegen）

2. **`ir_fold.h` 的常量折叠表：** IR 包含一个自动生成的折叠规则表 (`gen_ir_fold_hash`)，定义了数百条代数简化规则。AOT 可以采用类似的"规则表" 驱动的常量折叠。

3. **JIT 的 profiling 机制：** `hot_loop` / `hot_func` 计数器 —— AOT 可以将 profile 数据嵌入生成的二进制，用于 PGO (Profile-Guided Optimization)。

---

## 9. OPcache 持久化 & File Cache

### 设计

OPcache 不只是一个缓存——它在缓存中存的是**优化后**的字节码。

```
原始 PHP 源码
  → 编译为 zend_op_array (原始字节码)
  → 经过 Zend Optimizer 所有 pass (SSA + 类型推断 + SCCP + DCE + ...)
  → 只保留优化后的 zend_op_array (丢弃 SSA 等临时 IR)
  → zend_persist() 序列化到共享内存 / 文件缓存
```

**`zend_persist_calc` + `zend_persist`**：两阶段序列化——
1. `_calc` 计算所需的共享内存大小
2. `_persist` 进行实际序列化（所有指针调整为绝对偏移量）

**文件缓存 (`zend_file_cache.c`)：** 将持久化后的脚本写入文件，允许跨进程重启复用。

### AOT 借鉴

当前 AOT 编译器每次从 PHP 源码开始编译。可以借鉴 OPcache 的理念：

1. **缓存优化后的 AST/类型信息：** 在 `convert()` 阶段之后，将带类型的 AST 序列化，下次编译时直接加载
2. **增量编译：** 只重新编译改动的文件及其依赖
3. **`_calc` + `_persist` 两阶段序列化：** 先计算大小再分配内存/写入，避免 realloc 碎片

---

## 10. 其他值得注意的设计

### zend_bitset

PHP 使用自己的 bitset 实现进行高效的集合操作。优化器频繁使用 bitset 来表示 worklist、live sets、def/use sets。

### zend_worklist.h

通用的 worklist 迭代宏，SCCP 和类型推断都使用相同的 worklist 机制。设计为宏以便内联性能。

### zend_arena

Arena 内存分配器用于所有优化器数据结构的快速分配和整体释放。一个 arena 绑定一个 `zend_optimizer_ctx`，所有 pass 共享同一个 arena。

### Pass 间数据管理

SSA 等临时 IR 在 pass 完成后立即销毁（通过 arena free），只在最终的 `zend_op_array` 中保留优化后的结果。这保证了内存效率。

---

## 推荐采用顺序 (针对 AOT 编译器)

```
Phase 1: Pass Pipeline
  └── 定义 AotPass 枚举 + Pipeline runner，可插拔 pass 架构

Phase 2: 位掩码类型系统
  └── 借鉴 Zend 的 type mask 设计，替代当前的字符串类型常量
  └── 直接映射为 C++ 的 uint32_t 常量

Phase 3: 类型推断规则
  └── 每个 AST 节点/opcode 对应一个 update_type_info
  └── 与 Rule 系统 (#4 设计) 结合实现

Phase 4: SCCP + DCE
  └── 基于 SCDF 框架的常量传播 + 死代码消除
  └── 可在 C++ 代码生成之前消去冗余表达式

Phase 5: 逃逸分析
  └── Union-Find 等价逃逸集 + 4 状态传播
  └── 用于 Box 分配消除、引用计数消除

Phase 6: 调用图跨函数优化
  └── 在已有的符号依赖图上增加 call_map + 类型传播
```

每一层都可以独立实施并立即给现有代码生成带来收益。

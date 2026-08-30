# GMP 函数对照表（未实现）

本文档记录 PHP GMP 扩展中尚未在 BigInt 类型中实现的函数，作为后续开发的参考。

## 统计

GMP 扩展共 44 个函数（不含 `gmp_init` 和别名 `gmp_div`）。已覆盖 17 个，未覆盖 27 个。

## 已实现

| GMP 函数 | BigInt 方法 / 运算符 | 说明 |
|----------|---------------------|------|
| `gmp_init` | `std::bigInt()` | 构造 |
| `gmp_add` | `add()` / `+` | 加法 |
| `gmp_sub` | `sub()` / `-` | 减法 |
| `gmp_mul` | `mul()` / `*` | 乘法 |
| `gmp_div_q` | `div()` / `/` | 除法（商） |
| `gmp_div_r` | `mod()` / `%` | 除法（余数） |
| `gmp_div_qr` | `divmod()` | 商和余数 |
| `gmp_mod` | `mod()` / `%` | 取模 |
| `gmp_pow` | `pow()` | 幂运算 |
| `gmp_powm` | `powmod()` | 模幂 |
| `gmp_neg` | `neg()` / `-`（一元） | 取负 |
| `gmp_abs` | `abs()` | 绝对值 |
| `gmp_sqrt` | `sqrt()` | 平方根 |
| `gmp_gcd` | `gcd()` | 最大公约数 |
| `gmp_cmp` | `cmp()` / `<=>` | 比较 |
| `gmp_and` | `bitAnd()` / `&` | 按位与 |
| `gmp_or` | `bitOr()` / `\|` | 按位或 |
| `gmp_xor` | `bitXor()` / `^` | 按位异或 |
| `gmp_com` | `bitNot()` / `~` | 按位取反 |
| `gmp_testbit` | `testBit()` | 位测试 |
| `gmp_popcount` | `popCount()` | 人口统计 |
| `gmp_intval` | `toInt()` | 转 int |
| `gmp_strval` | `toString()` | 转字符串 |

## 未实现（按优先级排序）

### 高优先级 — 常用数论函数

| GMP 函数 | 建议方法名 | 签名 | 说明 |
|----------|-----------|------|------|
| `gmp_sign` | `sign()` | `(): int` | 符号，返回 -1/0/1 |
| `gmp_lcm` | `lcm($x)` | `(BigInt): BigInt` | 最小公倍数 |
| `gmp_perfect_square` | `perfectSquare()` | `(): bool` | 是否完全平方数 |
| `gmp_perfect_power` | `perfectPower()` | `(): bool` | 是否完全幂 |
| `gmp_prob_prime` | `probPrime($reps = 10)` | `(int): int` | 概率素性检测（Miller-Rabin） |
| `gmp_nextprime` | `nextPrime()` | `(): BigInt` | 下一个素数 |
| `gmp_binomial` | `binomial($k)` | `(int): BigInt` | 二项式系数 C(n, k) |
| `gmp_fact` | `fact()` | `(): BigInt` | 阶乘 n! |

### 中优先级 — 高级数论函数

| GMP 函数 | 建议方法名 | 签名 | 说明 |
|----------|-----------|------|------|
| `gmp_gcdext` | `gcdext($x)` | `(BigInt): array` | 扩展 GCD，返回 [g, s, t] 使得 g = s·a + t·b |
| `gmp_invert` | `invert($mod)` | `(BigInt): BigInt\|false` | 模逆元，不存在时返回 false |
| `gmp_sqrtrem` | `sqrtrem()` | `(): array` | 平方根+余数，返回 [root, rem] |
| `gmp_jacobi` | `jacobi($x)` | `(BigInt): int` | Jacobi 符号 |
| `gmp_legendre` | `legendre($x)` | `(BigInt): int` | Legendre 符号 |
| `gmp_kronecker` | `kronecker($x)` | `(BigInt): int` | Kronecker 符号 |

### 低优先级 — 较少使用

| GMP 函数 | 建议方法名 | 签名 | 说明 |
|----------|-----------|------|------|
| `gmp_divexact` | `divExact($x)` | `(BigInt): BigInt` | 精确除法（已知整除时使用，比普通除法更快） |
| `gmp_root` | `root($n)` | `(int): BigInt` | n 次方根（截断） |
| `gmp_rootrem` | `rootrem($n)` | `(int): array` | n 次方根+余数 |
| `gmp_hamdist` | `hamDist($x)` | `(BigInt): int` | 汉明距离 |

### 不适用 — 与不可变设计冲突

| GMP 函数 | 原因 |
|----------|------|
| `gmp_setbit` | 直接修改 GMP 对象，BigInt 不可变 |
| `gmp_clrbit` | 直接修改 GMP 对象，BigInt 不可变 |

### 待评估

| GMP 函数 | 说明 |
|----------|------|
| `gmp_scan0` | 从指定位置找第一个 0 位 |
| `gmp_scan1` | 从指定位置找第一个 1 位 |
| `gmp_random_bits` | 生成随机位数的 BigInt（需全局种子，不适合实例方法） |
| `gmp_random_range` | 区间随机 BigInt（需全局种子，不适合实例方法） |
| `gmp_random_seed` | 设置随机种子（全局状态，不适合实例方法） |
| `gmp_import` | 从二进制字符串导入 |
| `gmp_export` | 导出为二进制字符串 |

## toString 增强

| 缺失功能 | 说明 |
|---------|------|
| `toString($base)` | 当前 `toString()` 仅支持十进制。GMP 的 `gmp_strval` 支持 2-62 进制输出 |

## 更新记录

- 2026-05-27：初始版本，对比 PHP 8.4.14 GMP 扩展

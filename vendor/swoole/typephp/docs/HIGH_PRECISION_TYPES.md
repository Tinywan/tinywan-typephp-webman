# AOT 编译器高精度类型使用教程

本教程介绍 AOT 编译器中的三种高精度数值类型——**BigInt**（任意精度整数）、**Decimal**（50 位十进制数）和 **BigFloat**（256 bit 浮点数）。

## 目录

1. [为什么需要高精度类型](#1-为什么需要高精度类型)
2. [快速开始](#2-快速开始)
3. [三种类型概览](#3-三种类型概览)
4. [构造与声明](#4-构造与声明)
5. [算术运算](#5-算术运算)
6. [比较运算](#6-比较运算)
7. [复合赋值](#7-复合赋值)
8. [通用方法调用](#8-通用方法调用)
9. [类型转换](#9-类型转换)
10. [混合运算与类型提升](#10-混合运算与类型提升)
11. [超长字面量自动识别](#11-超长字面量自动识别)
12. [限制与注意事项](#12-限制与注意事项)
13. [完整示例](#13-完整示例)

---

## 1. 为什么需要高精度类型

PHP 的原生 `int` 是 64 位有符号整数，最大值为 `9223372036854775807`（约 9.22×10¹⁸）。超过这个范围的整数字面量会被 PHP 解析器静默转换为 `float`（double），丢失有效位数。

PHP 的原生 `float`（IEEE 754 double）最多只能保证约 15–16 位有效数字。对于金融计算、科学计算、密码学等场景，这远远不够。

```php
// PHP 原生行为的精度问题
$a = 123456789012345678901234567890;  // 30 位整数 → 被转为 float，精度丢失
// 实际存储：1.2345678901234568E+29，末尾数字已经不可靠

$b = 0.1 + 0.2;  // 0.30000000000000004 — 经典的浮点误差
```

AOT 编译器提供了三种高精度类型，底层基于成熟的 C/C++ 数学库，并直接生成本地调用。这里的“零成本抽象”是指没有 PHP 方法查找和解释器分派开销；高精度运算本身仍需要数学库计算、内存分配和装箱：

| 类型 | 底层库 | 特点 |
|------|--------|------|
| BigInt | GMP (`libgmp`) | 任意精度整数，不会溢出 |
| Decimal | libmpdec | 十进制小数，约 50 位有效数字，无二进制浮点误差 |
| BigFloat | MPFR (`libmpfr`) | 默认 256 bit，字符串输出 64 位有效数字 |

---

## 2. 快速开始

使用高精度类型的前提条件：

1. 文件头部声明 `declare(strict_types=1)`
2. 导入原生类型声明 `use native_types`
3. 系统已安装对应的 C++ 库（`libgmp-dev`、`libmpdec-dev`、`libmpfr-dev`）

```php
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // 你的高精度计算代码
    $a = std::bigInt("123456789012345678901234567890");
    $b = std::bigInt("987654321098765432109876543210");
    $sum = $a + $b;
    echo $sum->toString();
}
?>
```

编译运行：

```bash
php bin/tpc.php my_program.php -o my_program
./my_program
```

> **提示**：和所有 native_types 一样，Big* 类型只能在 AOT 编译模式下使用，不能在普通 PHP 解释器中运行。AOT 编译器会对 `std::bigInt()` 等函数进行编译期求值，直接生成 C++ 代码。

---

## 3. 三种类型概览

### BigInt — 任意精度整数

适用于大整数计算，不会溢出，不会丢失精度。整数除法结果为整数（截断）。

```php
$a = std::bigInt("1234567890123456789012345678901234567890");  // 40 位
$b = $a * 2;  // 80 位，不会溢出
```

### Decimal — 50 位十进制数

适用于金融计算等需要精确十进制表示的场景。`0.1 + 0.2` 精确等于 `0.3`，不存在二进制浮点误差。

```php
$price = std::decimal("19.99");
$quantity = 3;
$total = $price * $quantity;  // 59.97，精确
```

### BigFloat — 256 bit 高精度浮点数

适用于科学计算等需要高精度浮点运算的场景。基于 MPFR，当前默认精度固定为 256 bit，远高于 IEEE 754 double 的 53 bit。

```php
$pi = std::bigFloat("3.141592653589793238462643383279502884197");
$area = $pi * 100 * 100;  // 高精度 π × r²
```

---

## 4. 构造与声明

### 4.1 从字面量构造

`std::bigInt()`、`std::decimal()`、`std::bigFloat()` 是**编译期函数**，在生成的 C++ 代码中直接构造对应的 C++ 对象，不产生运行时函数调用。

```php
// BigInt — 从 int 或字符串构造
$a = std::bigInt(100);                                    // 普通整数
$b = std::bigInt("123456789012345678901234567890");       // 超长整数，必须用字符串

// Decimal — 建议从字符串构造以避免浮点精度丢失
$c = std::decimal("123.456");                             // ✅ 推荐：精确字符串
$d = std::decimal(42);                                    // ✅ 可行：从 int

// BigFloat — 从 int、float 或字符串构造
$e = std::bigFloat(100.5);                                // 从 float
$f = std::bigFloat(42);                                   // 从 int
$g = std::bigFloat("3.14159265358979323846");             // 从字符串（精确）
```

### 4.2 类型标注

在 `use native_types` 下，Big* 类型变量自动获得原生 C++ 存储类型：

```php
use native_types;

// 编译器自动推断类型为 php::BigInt / php::Decimal / php::BigFloat
$a = std::bigInt(100);         // → C++: php::Variant(new BigInt(100))
$b = std::decimal("100.50");   // → C++: php::Variant(new Decimal("100.50"))
$c = std::bigFloat(3.14);      // → C++: php::Variant(new BigFloat(3.14))
```

> **关键细节**：Big* 类型是**不可变（immutable）**的。每次运算都返回新值，不会修改原变量。详见 [第 7 节：复合赋值](#7-复合赋值)。

---

## 5. 算术运算

### 5.1 标准运算符

支持的运算符取决于具体类型：BigInt 支持 `+ - * / % **`，Decimal 支持 `+ - * / %`，BigFloat 支持 `+ - * /`：

```php
$a = std::bigInt(100);
$b = std::bigInt(200);

$sum  = $a + $b;    // 加法
$diff = $a - $b;    // 减法
$prod = $a * $b;    // 乘法
$quot = $a / $b;    // 除法（BigInt 为整数除法）
$mod  = $a % $b;    // 取模
$pow  = $a ** 10;   // 幂运算（BigInt 支持）

// 一元取负
$neg  = -$a;        // 取负
```

生成的 C++ 代码示例（`$a + $b`）：

```cpp
php::BigInt::add(a, b)      // BigInt 加法
php::BigInt::sub(a, b)      // BigInt 减法
php::BigInt::mul(a, b)      // BigInt 乘法
php::BigInt::div(a, b)      // BigInt 除法
php::BigInt::mod(a, b)      // BigInt 取模
php::BigInt::pow(a, b)      // BigInt 幂运算
```

### 5.2 与 int / float 混合运算

Big* 类型可以在安全范围内与普通 int/float 混合运算，编译器自动进行类型提升：

```php
$a = std::bigInt(100);

$b = $a + 50;       // BigInt + Int → BigInt
$c = 200 + $a;      // Int + BigInt → BigInt
$d = $a * 3.5;      // BigInt * Float → 编译错误！
                    // 浮点数无法精确提升为 BigInt，
                    // 应使用 Decimal 或 BigFloat
```

### 5.3 BigInt 除法注意事项

`BigInt / BigInt` 是整数除法（截断），类似于 PHP 的 `intdiv()`：

```php
$a = std::bigInt(100);
$b = $a / 3;  // 33（不是 33.333...）
```

如果需要精确的小数结果，先将操作数转换为 Decimal：

```php
$a = std::bigInt(100);
$result = std::decimal($a->toString()) / std::decimal("3");
// 33.333333333...
```

### 5.4 各类型支持的运算符汇总

| 运算符 | BigInt | Decimal | BigFloat |
|--------|--------|---------|----------|
| `+` `-` `*` | ✅ | ✅ | ✅ |
| `/` | ✅ 整数除法 | ✅ | ✅ |
| `%` | ✅ | ✅ | ❌ |
| `**` | ✅ | ❌ | ❌ |
| `-` (一元取负) | ✅ | ✅ | ✅ |

---

## 6. 比较运算

所有六种比较运算符均可用于 Big* 类型：

```php
$a = std::bigInt(100);
$b = 200;

// 比较运算返回 bool（需要 (int) 转换输出）
echo (int)($a < $b);     // 1 (true)   → cmp(a,b) < 0
echo (int)($a > $b);     // 0 (false)  → cmp(a,b) > 0
echo (int)($a <= 100);   // 1 (true)   → cmp(a,b) <= 0
echo (int)($a >= 100);   // 1 (true)   → cmp(a,b) >= 0
echo (int)($a == 100);   // 1 (true)   → cmp(a,b) == 0
echo (int)($a != 50);    // 1 (true)   → cmp(a,b) != 0

// 太空船运算符
$cmp = $a <=> $b;        // -1 ($a < $b)
echo (int)$cmp;          // -1
```

生成的 C++ 代码示例：

```cpp
php::BigInt::cmp(a, b) < 0    // a < b
php::BigInt::cmp(a, b) == 0   // a == b
php::BigInt::cmp(a, b) != 0   // a != b
php::BigInt::cmp(a, b)        // a <=> b（直接返回 -1/0/1）
```

---

## 7. 复合赋值

Big* 类型**支持** `+=`、`-=`、`*=`、`/=`、`%=` 等复合赋值运算符。

### 7.1 工作方式

Big* 类型是**不可变的**（immutable）。`$a += 50` 在编译时被展开为 `$a = BigInt::add($a, 50)`——创建一个新值然后赋值给原变量。

```php
$a = std::bigInt(100);
$a += 50;          // → a = php::BigInt::add(a, php::newBigInt(50))
echo $a->toString();  // "150"

$a -= 30;          // → a = php::BigInt::sub(a, php::newBigInt(30))
$a *= 5;           // → a = php::BigInt::mul(a, php::newBigInt(5))
$a /= 3;           // → a = php::BigInt::div(a, php::newBigInt(3))
$a %= 7;           // → a = php::BigInt::mod(a, php::newBigInt(7))
```

Decimal 和 BigFloat 同样支持：

```php
// Decimal 复合赋值
$d = std::decimal("100.50");
$d += 25.25;       // → d = php::Decimal::add(d, php::newDecimal("25.25"))
$d -= 123.45;      // → d = php::Decimal::sub(d, php::newDecimal("123.45"))
$d *= 2;           // → d = php::Decimal::mul(d, php::newDecimal(2))
$d /= 4;           // → d = php::Decimal::div(d, php::newDecimal(4))
$d %= 5.0;         // → d = php::Decimal::mod(d, php::newDecimal("5.0"))

// BigFloat 复合赋值（不支持 %=）
$bf = std::bigFloat(100.0);
$bf += 50.0;
$bf -= 30.0;
$bf *= 2.0;
$bf /= 3.0;
```

### 7.2 `++` / `--` 不可用

由于 Big* 类型是不可变的，`++` / `--` 运算符在语义上不匹配。编译器会给出清晰的错误提示：

```php
$a = std::bigInt(100);
$a++;  // ❌ 编译错误：Cannot use ++ on php::BigInt. Use += 1 instead.
++$a;  // ❌ 编译错误：Cannot use ++ on php::BigInt. Use += 1 instead.
--$a;  // ❌ 编译错误：Cannot use -- on php::BigInt. Use -= 1 instead.
```

正确的替代写法：

```php
$a += 1;   // ✅ 代替 $a++
$a -= 1;   // ✅ 代替 $a--
```

---

## 8. 通用方法调用

Big* 类型支持通过 `$value->method()` 语法（通用方法/Universal Methods）调用方法。这些调用在编译时直接翻译为对应的 C++ 静态函数，没有动态方法分派开销；数学库运算、结果分配和装箱成本仍然存在。

### 8.1 BigInt 方法

```php
$a = std::bigInt("12345678901234567890");

// 算术方法（均返回新的 BigInt）
$b = $a->add(1);        // 加法：$a + 1
$c = $a->sub(1);        // 减法：$a - 1
$d = $a->mul(2);        // 乘法：$a * 2
$e = $a->div(10);       // 除法：$a / 10
$f = $a->mod(1000000);  // 取模：$a % 1000000
$g = $a->pow(3);        // 幂运算：$a ** 3

// 一元方法
$h = $a->neg();         // 取负：-$a
$i = $a->abs();         // 绝对值

// 特殊方法
$j = $a->gcd(15);       // 最大公约数：gcd($a, 15)

// 比较方法
$cmp = $a->cmp(100);    // 比较：返回 -1/0/1
if ($a->cmp(100) > 0) { /* $a > 100 */ }

// 类型转换方法
echo $a->toString();    // 转字符串："12345678901234567890"
echo $a->toInt();       // 转 int；超出 PHP int 范围时抛出 ArithmeticError
echo $a->toFloat();     // 转 float（可能丢精度）
```

### 8.2 Decimal 方法

```php
$d = std::decimal("123.456");

// 算术方法
echo $d->add(std::decimal("50.25"))->toString();  // "173.706"
echo $d->sub(std::decimal("50.25"))->toString();  // "73.206"
echo $d->mul(2)->toString();                      // "246.912"
echo $d->div(3)->toString();                      // "41.152"
echo $d->mod(std::decimal("5.0"))->toString();    // "3.456"

// 一元方法
echo $d->neg()->toString();   // "-123.456"
echo $d->abs()->toString();   // "123.456"

// 比较与转换
echo $d->cmp(std::decimal("100")) > 0 ? "greater" : "less";  // "greater"
echo $d->toInt();             // 123
echo $d->toString();          // "123.456"
```

### 8.3 BigFloat 方法

```php
$bf = std::bigFloat(3.14159265);

echo $bf->add(1.0)->toString();   // "4.14159265..."
echo $bf->mul(2.0)->toString();   // "6.2831853..."
echo $bf->div(2.0)->toFloat();    // 1.570796325
echo $bf->neg()->toString();      // "-3.14159265..."
echo $bf->abs()->toString();      // "3.14159265..."

// 比较
echo $bf->cmp(3.0);               // > 0（$bf > 3.0）
```

### 8.4 通用方法 vs 运算符

运算符和方法调用在功能上等价，选择哪种取决于代码风格：

```php
$a = std::bigInt(100);
$b = std::bigInt(50);

// 两种等价写法
$result1 = $a + $b;             // 运算符风格
$result2 = $a->add($b);         // 方法调用风格

// 方法调用支持链式调用
$result3 = $a->add(10)->mul(2)->sub(5)->toString();  // "215"
```

---

## 9. 类型转换

### 9.1 Big* 之间的转换

```php
// BigInt → Decimal（精确，推荐方式）
$big = std::bigInt("12345678901234567890");
$dec = std::decimal($big->toString());

// Decimal → BigInt（截断小数部分）
$d = std::decimal("123.456");
$i = std::bigInt($d->toInt());  // 123

// Int → BigInt / Decimal / BigFloat
$bi = std::bigInt(42);
$dc = std::decimal(42);
$bf = std::bigFloat(42);

// Float → BigFloat（Float → Decimal 不推荐直接使用 float 字面量）
$bf2 = std::bigFloat(3.14);

// 任意类型 → BigFloat
$bf3 = std::bigFloat($big->toString());
```

### 9.2 Big* 与普通类型的转换

```php
// BigInt → 普通类型
$a = std::bigInt("99999999999999999999");
$s = $a->toString();  // "99999999999999999999"
$i = $a->toInt();     // 超出 PHP int 范围时抛出 ArithmeticError
$f = $a->toFloat();   // 1.0E+20（可能丢失精度）

// 普通类型 → BigInt（通过编译期函数）
$b = std::bigInt(42);           // int → BigInt
$c = std::bigInt("123456...");  // string → BigInt

// 强制转换和 PHP 转换函数会按数值转换，不会读取 Box resource id
$n = (int) std::decimal("12.75");       // 12
$x = floatval(std::bigInt("42"));       // 42.0
$ok = boolval(std::bigFloat("0"));      // false
```

### 9.3 跨类型隐式混合的限制

编译器会阻止可能导致精度损失的跨类型隐式混合运算：

```php
$a = std::bigFloat(100.5);
$b = std::bigInt(200);

$c = $a + $b;  // ❌ 编译错误：Cannot mix BigFloat and BigInt implicitly.
               //    Use std::bigFloat() to convert explicitly.

// 正确的做法：显式转换
$c = $a + std::bigFloat($b->toString());  // ✅
```

| 组合 | 是否允许 | 说明 |
|------|---------|------|
| BigInt + BigFloat | ❌ 编译错误 | 精密度量不同，需显式转换 |
| BigInt + Decimal | ❌ 编译错误 | 精密度量不同，需显式转换 |
| BigFloat + Decimal | ❌ 编译错误 | 精密度量不同，需显式转换 |
| BigInt + Int | ✅ 自动提升 Int → BigInt | 无精度损失 |
| BigInt + Float | ❌ 编译错误 | Float 无法精确提升为 BigInt |
| Decimal + Int | ✅ 自动提升 Int → Decimal | 无精度损失 |
| Decimal + Float | ✅ 自动提升 Float → Decimal | 可能有微小误差 |
| BigFloat + Int | ✅ 自动提升 Int → BigFloat | 无精度损失 |
| BigFloat + Float | ✅ 自动提升 Float → BigFloat | 无精度损失 |

---

## 10. 混合运算与类型提升

当 Big* 类型与普通 Int/Float 混合运算时，编译器只执行不会改变数值模型的安全提升。

**规则**：

1. 若任一操作数是 Var（非原生类型），则全部转为 Var，使用 ZendVM 运行时运算
2. 若两操作数均为 Int/Float，则 Float 优先（Int → Float）
3. BigInt 可安全提升 Int；Decimal 可提升 Int 和保留源码文本的 Float 字面量；BigFloat 可提升 Int/Float
4. 不同 Big* 类型之间，以及 BigInt 与 Float 之间，不进行隐式转换

```php
// 类型提升示例
$a = std::bigInt(100);
$b = 50;                // Int

$c = $a + $b;           // BigInt + Int → BigInt
                        // $b 自动提升为 BigInt

$d = std::decimal("10.5");
$e = $d + 3;            // Decimal + Int → Decimal
                        // 3 自动提升为 Decimal

$f = std::bigFloat(1.5);
$g = $f + 2.0;          // BigFloat + Float → BigFloat
                        // 2.0 自动提升为 BigFloat
```

---

## 11. 超长字面量自动识别

AOT 编译器会自动检测超出原生类型精度的数值字面量，并自动转为对应的 Big* 类型。你**不需要手动包装**。

```php
// 19 位以上整数 → 自动转为 BigInt
$a = 12345678901234567890;
echo $a->toString();  // "12345678901234567890"
// 编译器自动处理：等同于 std::bigInt("12345678901234567890")

// 16 位有效数字以上的小数 → 自动转为 Decimal
$b = 3.14159265358979323846;
// 编译器自动处理：等同于 std::decimal("3.14159265358979323846")
```

**识别规则**：

- 纯数字，19 位及以上 → BigInt
- 含小数点或指数，16 位有效数字以上 → Decimal
- 禁用下划线 `_`（如 `1_234_567_890_123_456_789_0`）

> **推荐做法**：对于关键精度，仍然建议显式使用 `std::bigInt("...")` 或 `std::decimal("...")`，确保意图明确。自动识别是一个便利特性，适用于快速原型开发。

---

## 12. 限制与注意事项

### 12.1 不可变性

所有 Big* 类型是**不可变的**。每次运算创建新值：

```php
$a = std::bigInt(100);
$b = $a->add(50);    // $a 依然是 100，$b 是 150
$c = $a + 50;        // $a 依然是 100，$c 是 150
```

### 12.2 `++` / `--` 不支持

参见 [第 7.2 节](#72---不可用)。使用 `+= 1` / `-= 1` 代替。

### 12.3 BigFloat 不支持 `%` 和 `**`

```php
$bf = std::bigFloat(10.0);
$bf %= 3;   // ❌ 编译错误
$bf ** 2;   // ❌ 编译错误
```

### 12.4 Decimal 不支持 `**`

```php
$d = std::decimal("10.5");
$d ** 2;    // ❌ 编译错误
```

### 12.5 跨 Big* 类型不能隐式混合

BigFloat、Decimal、BigInt 之间必须显式转换：

```php
$a = std::bigFloat(100.5);
$b = std::bigInt(200);
$c = $a + $b;  // ❌ 编译错误
// 改为
$c = $a + std::bigFloat($b->toString());  // ✅
```

该限制同样适用于比较运算。比较前必须把两边显式转换为同一种 Big* 类型，避免编译为错误的底层资源类型。

### 12.6 边界和异常

- BigInt 负数右移采用算术右移，例如 `std::bigInt("-3") >> 1` 得到 `-2`。
- 负数 bit index、负数 `popCount()`、过大的幂指数会抛出 `ValueError`。
- 除零抛出 `DivisionByZeroError`；转为 PHP int 时超出范围抛出 `ArithmeticError`。
- BigFloat 的绝对值指数超过 10000 时，`toString()` 自动使用科学计数法，避免构造超大字符串。

### 12.7 不能在普通 PHP 解释器中运行

Big* 类型是 AOT 编译器的专有特性，依赖编译期代码生成和 C++ 底层库。源码不能被 `php` 命令直接解释执行。

### 12.8 启用 `use native_types`

忘记添加 `use native_types` 会导致 Big* 变量被当作 Var（通用类型），失去原生类型的大部分性能优势。

---

## 13. 完整示例

### 13.1 大整数阶乘

```php
<?php
declare(strict_types=1);
use native_types;

/**
 * 计算 n 的阶乘，支持任意大的结果
 */
function factorial(int $n): void {
    $result = std::bigInt(1);
    for ($i = 2; $i <= $n; $i++) {
        $result *= $i;
    }
    echo "{$n}! = " . $result->toString() . "\n";
    echo "位数: " . strlen($result->toString()) . "\n";
}

function main(): void {
    factorial(10);   // 10! = 3628800
    factorial(50);   // 3041409320171337804361260816606476884...
    factorial(100);  // 933262154439441526816992388562667004...
}
?>
```

### 13.2 金融计算：订单明细

```php
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // 使用 Decimal 精确表示金额
    $price = std::decimal("19.99");
    $quantity = 3;
    $taxRate = std::decimal("0.08");

    $subtotal = $price * $quantity;
    $tax = $subtotal * $taxRate;
    $total = $subtotal + $tax;

    echo "单价: " . $price->toString() . "\n";
    echo "数量: {$quantity}\n";
    echo "小计: " . $subtotal->toString() . "\n";
    echo "税额: " . $tax->toString() . "\n";
    echo "总计: " . $total->toString() . "\n";
}
?>
```

输出：

```
单价: 19.99
数量: 3
小计: 59.97
税额: 4.7976
总计: 64.7676
```

### 13.3 高精度圆周率计算

```php
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // 使用 BigFloat 进行高精度数学运算
    $pi = std::bigFloat("3.141592653589793238462643383279502884197");
    $radius = 100;

    // 圆面积
    $area = $pi * std::bigFloat($radius * $radius);
    echo "圆面积: " . $area->toString() . "\n";

    // 圆周长
    $circumference = $pi * std::bigFloat(2 * $radius);
    echo "圆周长: " . $circumference->toString() . "\n";

    // 比较
    $earthRadius = 6371;
    $earthArea = $pi * std::bigFloat($earthRadius * $earthRadius);
    echo "如果半径是 {$earthRadius}km...\n";
    echo "面积约: " . $earthArea->toInt() . " km²\n";
}
?>
```

### 13.4 综合示例：多种类型混合

```php
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // BigInt — 大整数运算
    $big = std::bigInt("100000000000000000000");
    $big += std::bigInt("99999999999999999999");
    echo "BigInt: " . $big->toString() . "\n";

    // 运算符 + 比较
    $a = std::bigInt(100);
    echo "BigInt + Int: " . ($a + 50)->toString() . "\n";
    echo "BigInt * 5: " . ($a * 5)->toString() . "\n";
    echo "BigInt > 50: " . (int)($a > 50) . "\n";
    echo "a == 100: " . (int)($a == 100) . "\n";

    // Unary minus
    $neg = -$a;
    echo "-a: " . $neg->toString() . "\n";

    // Decimal — 精确十进制运算
    $price = std::decimal("99.99");
    $price *= 3;   // 复合赋值
    echo "价格 × 3: " . $price->toString() . "\n";

    // 比较
    $d = std::decimal("100.25");
    echo "d > 50: " . (int)($d > 50) . "\n";
    echo "d != 100: " . (int)($d != 100) . "\n";

    // BigFloat — 高精度浮点
    $bf = std::bigFloat(3.14159);
    $bf *= 2.0;
    echo "pi × 2: " . $bf->toString() . "\n";

    // 方法链式调用
    $result = std::bigInt(100)
        ->add(50)
        ->mul(3)
        ->sub(100)
        ->toString();
    echo "100 + 50 × 3 - 100 = " . $result . "\n";
}
?>
```

输出：

```
BigInt: 200000000000000000099
BigInt + Int: 150
BigInt * 5: 500
BigInt > 50: 1
a == 100: 1
-a: -100
价格 × 3: 299.97
d > 50: 1
d != 100: 1
pi × 2: 6.2831800000000000
100 + 50 × 3 - 100 = 350
```

---

## 进一步阅读

- **类型系统规范**：[`docs/NATIVE_TYPES.md`](NATIVE_TYPES.md) — 完整的类型提升规则、声明语法、C++ API 参考
- **BigInt PHPT 测试**：[`tests/compiler/bigint/`](../tests/compiler/bigint/) — BigInt 各项功能的集成测试
- **Decimal PHPT 测试**：[`tests/compiler/decimal/`](../tests/compiler/decimal/) — Decimal 各项功能的集成测试
- **BigFloat 集成测试**：[`tests/compiler/bignumber/bigfloat_operators.phpt`](../tests/compiler/bignumber/bigfloat_operators.phpt) — BigFloat 运算符测试
- **C++ 运行时头文件**：
  - [`phpx/include/phpx_big_int.h`](../../phpx/include/phpx_big_int.h) — BigInt C++ API
  - [`phpx/include/phpx_decimal.h`](../../phpx/include/phpx_decimal.h) — Decimal C++ API
  - [`phpx/include/phpx_big_float.h`](../../phpx/include/phpx_big_float.h) — BigFloat C++ API

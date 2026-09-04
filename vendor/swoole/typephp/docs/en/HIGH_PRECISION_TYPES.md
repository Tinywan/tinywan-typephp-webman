# AOT Compiler High-Precision Types Tutorial

This tutorial introduces the three high-precision numeric types in the AOT compiler — **BigInt** (arbitrary-precision integer), **Decimal** (50-digit decimal number), and **BigFloat** (256-bit floating-point number).

## Table of Contents

1. [Why High-Precision Types Are Needed](#1-why-high-precision-types-are-needed)
2. [Quick Start](#2-quick-start)
3. [Overview of the Three Types](#3-overview-of-the-three-types)
4. [Construction and Declaration](#4-construction-and-declaration)
5. [Arithmetic Operations](#5-arithmetic-operations)
6. [Comparison Operations](#6-comparison-operations)
7. [Compound Assignment](#7-compound-assignment)
8. [Universal Method Calls](#8-universal-method-calls)
9. [Type Conversion](#9-type-conversion)
10. [Mixed Operations and Type Promotion](#10-mixed-operations-and-type-promotion)
11. [Automatic Detection of Extra-Long Literals](#11-automatic-detection-of-extra-long-literals)
12. [Limitations and Notes](#12-limitations-and-notes)
13. [Complete Examples](#13-complete-examples)

---

## 1. Why High-Precision Types Are Needed

PHP's native `int` is a 64-bit signed integer with a maximum value of `9223372036854775807` (about 9.22×10¹⁸). Integer literals exceeding this range are silently converted by the PHP parser to `float` (double), losing significant digits.

PHP's native `float` (IEEE 754 double) can only guarantee about 15–16 significant digits at most. This is far from sufficient for financial computation, scientific computing, cryptography, and other scenarios.

```php
// Precision problems with native PHP behavior
$a = 123456789012345678901234567890;  // 30-digit integer → converted to float, precision lost
// Actually stored: 1.2345678901234568E+29, the trailing digits are already unreliable

$b = 0.1 + 0.2;  // 0.30000000000000004 — the classic floating-point error
```

The AOT compiler provides three high-precision types, built on mature C/C++ math libraries, and directly generates native calls. Here "zero-cost abstraction" refers to the absence of PHP method lookup and interpreter dispatch overhead; the high-precision operations themselves still require math library computation, memory allocation, and boxing:

| Type | Underlying library | Characteristics |
|------|--------|------|
| BigInt | GMP (`libgmp`) | Arbitrary-precision integer, never overflows |
| Decimal | libmpdec | Decimal fraction, about 50 significant digits, no binary floating-point error |
| BigFloat | MPFR (`libmpfr`) | 256 bit by default, 64 significant digits in string output |

---

## 2. Quick Start

Prerequisites for using high-precision types:

1. Declare `declare(strict_types=1)` at the top of the file
2. Import the native type declaration `use native_types`
3. The system must have the corresponding C++ libraries installed (`libgmp-dev`, `libmpdec-dev`, `libmpfr-dev`)

```php
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // Your high-precision computation code
    $a = std::bigInt("123456789012345678901234567890");
    $b = std::bigInt("987654321098765432109876543210");
    $sum = $a + $b;
    echo $sum->toString();
}
?>
```

Compile and run:

```bash
php bin/tpc.php my_program.php -o my_program
./my_program
```

> **Tip**: Like all native_types, the Big* types can only be used in AOT compile mode and cannot run in the normal PHP interpreter. The AOT compiler performs compile-time evaluation of functions such as `std::bigInt()` and directly generates C++ code.

---

## 3. Overview of the Three Types

### BigInt — Arbitrary-Precision Integer

Suitable for large integer computation; it never overflows and never loses precision. Integer division produces an integer result (truncated).

```php
$a = std::bigInt("1234567890123456789012345678901234567890");  // 40 digits
$b = $a * 2;  // 80 digits, never overflows
```

### Decimal — 50-Digit Decimal Number

Suitable for scenarios requiring precise decimal representation, such as financial computation. `0.1 + 0.2` exactly equals `0.3` with no binary floating-point error.

```php
$price = std::decimal("19.99");
$quantity = 3;
$total = $price * $quantity;  // 59.97, exact
```

### BigFloat — 256-Bit High-Precision Floating-Point Number

Suitable for scenarios requiring high-precision floating-point computation, such as scientific computing. Based on MPFR, the default precision is currently fixed at 256 bit, far higher than the 53 bit of IEEE 754 double.

```php
$pi = std::bigFloat("3.141592653589793238462643383279502884197");
$area = $pi * 100 * 100;  // high-precision π × r²
```

---

## 4. Construction and Declaration

### 4.1 Constructing from Literals

`std::bigInt()`, `std::decimal()`, and `std::bigFloat()` are **compile-time functions** that directly construct the corresponding C++ objects in the generated C++ code, without producing runtime function calls.

```php
// BigInt — construct from an int or a string
$a = std::bigInt(100);                                    // ordinary integer
$b = std::bigInt("123456789012345678901234567890");       // extra-long integer, must use a string

// Decimal — construct from a string is recommended to avoid floating-point precision loss
$c = std::decimal("123.456");                             // ✅ recommended: exact string
$d = std::decimal(42);                                    // ✅ acceptable: from int

// BigFloat — construct from an int, float, or string
$e = std::bigFloat(100.5);                                // from float
$f = std::bigFloat(42);                                   // from int
$g = std::bigFloat("3.14159265358979323846");             // from string (exact)
```

### 4.2 Type Annotation

Under `use native_types`, Big* type variables automatically get native C++ storage types:

```php
use native_types;

// The compiler automatically infers the type as php::BigInt / php::Decimal / php::BigFloat
$a = std::bigInt(100);         // → C++: php::Variant(new BigInt(100))
$b = std::decimal("100.50");   // → C++: php::Variant(new Decimal("100.50"))
$c = std::bigFloat(3.14);      // → C++: php::Variant(new BigFloat(3.14))
```

> **Key detail**: Big* types are **immutable**. Every operation returns a new value and never modifies the original variable. See [Section 7: Compound Assignment](#7-compound-assignment) for details.

---

## 5. Arithmetic Operations

### 5.1 Standard Operators

The supported operators depend on the concrete type: BigInt supports `+ - * / % **`, Decimal supports `+ - * / %`, and BigFloat supports `+ - * /`:

```php
$a = std::bigInt(100);
$b = std::bigInt(200);

$sum  = $a + $b;    // addition
$diff = $a - $b;    // subtraction
$prod = $a * $b;    // multiplication
$quot = $a / $b;    // division (integer division for BigInt)
$mod  = $a % $b;    // modulo
$pow  = $a ** 10;   // exponentiation (supported by BigInt)

// unary negation
$neg  = -$a;        // negation
```

Example of the generated C++ code (`$a + $b`):

```cpp
php::BigInt::add(a, b)      // BigInt addition
php::BigInt::sub(a, b)      // BigInt subtraction
php::BigInt::mul(a, b)      // BigInt multiplication
php::BigInt::div(a, b)      // BigInt division
php::BigInt::mod(a, b)      // BigInt modulo
php::BigInt::pow(a, b)      // BigInt exponentiation
```

### 5.2 Mixed Operations with int / float

Big* types can be mixed with ordinary int/float within a safe range, and the compiler automatically performs type promotion:

```php
$a = std::bigInt(100);

$b = $a + 50;       // BigInt + Int → BigInt
$c = 200 + $a;      // Int + BigInt → BigInt
$d = $a * 3.5;      // BigInt * Float → compile error!
                    // a float cannot be promoted to BigInt exactly,
                    // use Decimal or BigFloat instead
```

### 5.3 BigInt Division Notes

`BigInt / BigInt` is integer division (truncation), similar to PHP's `intdiv()`:

```php
$a = std::bigInt(100);
$b = $a / 3;  // 33 (not 33.333...)
```

If you need an exact decimal result, convert the operands to Decimal first:

```php
$a = std::bigInt(100);
$result = std::decimal($a->toString()) / std::decimal("3");
// 33.333333333...
```

### 5.4 Summary of Operators Supported by Each Type

| Operator | BigInt | Decimal | BigFloat |
|--------|--------|---------|----------|
| `+` `-` `*` | ✅ | ✅ | ✅ |
| `/` | ✅ integer division | ✅ | ✅ |
| `%` | ✅ | ✅ | ❌ |
| `**` | ✅ | ❌ | ❌ |
| `-` (unary negation) | ✅ | ✅ | ✅ |

---

## 6. Comparison Operations

All six comparison operators can be used with Big* types:

```php
$a = std::bigInt(100);
$b = 200;

// comparison operations return bool (an (int) cast is needed for output)
echo (int)($a < $b);     // 1 (true)   → cmp(a,b) < 0
echo (int)($a > $b);     // 0 (false)  → cmp(a,b) > 0
echo (int)($a <= 100);   // 1 (true)   → cmp(a,b) <= 0
echo (int)($a >= 100);   // 1 (true)   → cmp(a,b) >= 0
echo (int)($a == 100);   // 1 (true)   → cmp(a,b) == 0
echo (int)($a != 50);    // 1 (true)   → cmp(a,b) != 0

// spaceship operator
$cmp = $a <=> $b;        // -1 ($a < $b)
echo (int)$cmp;          // -1
```

Example of the generated C++ code:

```cpp
php::BigInt::cmp(a, b) < 0    // a < b
php::BigInt::cmp(a, b) == 0   // a == b
php::BigInt::cmp(a, b) != 0   // a != b
php::BigInt::cmp(a, b)        // a <=> b (directly returns -1/0/1)
```

---

## 7. Compound Assignment

Big* types **support** compound assignment operators such as `+=`, `-=`, `*=`, `/=`, and `%=`.

### 7.1 How It Works

Big* types are **immutable**. `$a += 50` is expanded at compile time to `$a = BigInt::add($a, 50)` — a new value is created and then assigned to the original variable.

```php
$a = std::bigInt(100);
$a += 50;          // → a = php::BigInt::add(a, php::newBigInt(50))
echo $a->toString();  // "150"

$a -= 30;          // → a = php::BigInt::sub(a, php::newBigInt(30))
$a *= 5;           // → a = php::BigInt::mul(a, php::newBigInt(5))
$a /= 3;           // → a = php::BigInt::div(a, php::newBigInt(3))
$a %= 7;           // → a = php::BigInt::mod(a, php::newBigInt(7))
```

Decimal and BigFloat support it likewise:

```php
// Decimal compound assignment
$d = std::decimal("100.50");
$d += 25.25;       // → d = php::Decimal::add(d, php::newDecimal("25.25"))
$d -= 123.45;      // → d = php::Decimal::sub(d, php::newDecimal("123.45"))
$d *= 2;           // → d = php::Decimal::mul(d, php::newDecimal(2))
$d /= 4;           // → d = php::Decimal::div(d, php::newDecimal(4))
$d %= 5.0;         // → d = php::Decimal::mod(d, php::newDecimal("5.0"))

// BigFloat compound assignment (% is not supported)
$bf = std::bigFloat(100.0);
$bf += 50.0;
$bf -= 30.0;
$bf *= 2.0;
$bf /= 3.0;
```

### 7.2 `++` / `--` Are Unavailable

Because Big* types are immutable, the `++` / `--` operators do not match semantically. The compiler gives a clear error message:

```php
$a = std::bigInt(100);
$a++;  // ❌ compile error: Cannot use ++ on php::BigInt. Use += 1 instead.
++$a;  // ❌ compile error: Cannot use ++ on php::BigInt. Use += 1 instead.
--$a;  // ❌ compile error: Cannot use -- on php::BigInt. Use -= 1 instead.
```

The correct alternatives:

```php
$a += 1;   // ✅ instead of $a++
$a -= 1;   // ✅ instead of $a--
```

---

## 8. Universal Method Calls

Big* types support calling methods via the `$value->method()` syntax (Universal Methods). These calls are directly translated at compile time to the corresponding C++ static functions, with no dynamic method dispatch overhead; the math library computation, result allocation, and boxing costs still remain.

### 8.1 BigInt Methods

```php
$a = std::bigInt("12345678901234567890");

// arithmetic methods (all return a new BigInt)
$b = $a->add(1);        // addition: $a + 1
$c = $a->sub(1);        // subtraction: $a - 1
$d = $a->mul(2);        // multiplication: $a * 2
$e = $a->div(10);       // division: $a / 10
$f = $a->mod(1000000);  // modulo: $a % 1000000
$g = $a->pow(3);        // exponentiation: $a ** 3

// unary methods
$h = $a->neg();         // negation: -$a
$i = $a->abs();         // absolute value

// special methods
$j = $a->gcd(15);       // greatest common divisor: gcd($a, 15)

// comparison methods
$cmp = $a->cmp(100);    // comparison: returns -1/0/1
if ($a->cmp(100) > 0) { /* $a > 100 */ }

// type conversion methods
echo $a->toString();    // to string: "12345678901234567890"
echo $a->toInt();       // to int; throws ArithmeticError when out of the PHP int range
echo $a->toFloat();     // to float (may lose precision)
```

### 8.2 Decimal Methods

```php
$d = std::decimal("123.456");

// arithmetic methods
echo $d->add(std::decimal("50.25"))->toString();  // "173.706"
echo $d->sub(std::decimal("50.25"))->toString();  // "73.206"
echo $d->mul(2)->toString();                      // "246.912"
echo $d->div(3)->toString();                      // "41.152"
echo $d->mod(std::decimal("5.0"))->toString();    // "3.456"

// unary methods
echo $d->neg()->toString();   // "-123.456"
echo $d->abs()->toString();   // "123.456"

// comparison and conversion
echo $d->cmp(std::decimal("100")) > 0 ? "greater" : "less";  // "greater"
echo $d->toInt();             // 123
echo $d->toString();          // "123.456"
```

### 8.3 BigFloat Methods

```php
$bf = std::bigFloat(3.14159265);

echo $bf->add(1.0)->toString();   // "4.14159265..."
echo $bf->mul(2.0)->toString();   // "6.2831853..."
echo $bf->div(2.0)->toFloat();    // 1.570796325
echo $bf->neg()->toString();      // "-3.14159265..."
echo $bf->abs()->toString();      // "3.14159265..."

// comparison
echo $bf->cmp(3.0);               // > 0 ($bf > 3.0)
```

### 8.4 Universal Methods vs Operators

Operators and method calls are functionally equivalent; which one to choose depends on coding style:

```php
$a = std::bigInt(100);
$b = std::bigInt(50);

// two equivalent ways of writing
$result1 = $a + $b;             // operator style
$result2 = $a->add($b);         // method call style

// method calls support chaining
$result3 = $a->add(10)->mul(2)->sub(5)->toString();  // "215"
```

---

## 9. Type Conversion

### 9.1 Conversion Between Big* Types

```php
// BigInt → Decimal (exact, recommended approach)
$big = std::bigInt("12345678901234567890");
$dec = std::decimal($big->toString());

// Decimal → BigInt (truncates the fractional part)
$d = std::decimal("123.456");
$i = std::bigInt($d->toInt());  // 123

// Int → BigInt / Decimal / BigFloat
$bi = std::bigInt(42);
$dc = std::decimal(42);
$bf = std::bigFloat(42);

// Float → BigFloat (using a float literal directly for Float → Decimal is not recommended)
$bf2 = std::bigFloat(3.14);

// any type → BigFloat
$bf3 = std::bigFloat($big->toString());
```

### 9.2 Conversion Between Big* and Ordinary Types

```php
// BigInt → ordinary types
$a = std::bigInt("99999999999999999999");
$s = $a->toString();  // "99999999999999999999"
$i = $a->toInt();     // throws ArithmeticError when out of the PHP int range
$f = $a->toFloat();   // 1.0E+20 (may lose precision)

// ordinary types → BigInt (via compile-time functions)
$b = std::bigInt(42);           // int → BigInt
$c = std::bigInt("123456...");  // string → BigInt

// explicit casts and PHP conversion functions convert numerically and do not read the Box resource id
$n = (int) std::decimal("12.75");       // 12
$x = floatval(std::bigInt("42"));       // 42.0
$ok = boolval(std::bigFloat("0"));      // false
```

### 9.3 Limitations on Cross-Type Implicit Mixing

The compiler blocks cross-type implicit mixing operations that may cause precision loss:

```php
$a = std::bigFloat(100.5);
$b = std::bigInt(200);

$c = $a + $b;  // ❌ compile error: Cannot mix BigFloat and BigInt implicitly.
               //    Use std::bigFloat() to convert explicitly.

// the correct approach: explicit conversion
$c = $a + std::bigFloat($b->toString());  // ✅
```

| Combination | Allowed | Description |
|------|---------|------|
| BigInt + BigFloat | ❌ compile error | different precision metrics, explicit conversion required |
| BigInt + Decimal | ❌ compile error | different precision metrics, explicit conversion required |
| BigFloat + Decimal | ❌ compile error | different precision metrics, explicit conversion required |
| BigInt + Int | ✅ automatically promote Int → BigInt | no precision loss |
| BigInt + Float | ❌ compile error | Float cannot be promoted to BigInt exactly |
| Decimal + Int | ✅ automatically promote Int → Decimal | no precision loss |
| Decimal + Float | ✅ automatically promote Float → Decimal | may have a tiny error |
| BigFloat + Int | ✅ automatically promote Int → BigFloat | no precision loss |
| BigFloat + Float | ✅ automatically promote Float → BigFloat | no precision loss |

---

## 10. Mixed Operations and Type Promotion

When Big* types are mixed with ordinary Int/Float, the compiler only performs safe promotions that do not change the numeric model.

**Rules**:

1. If either operand is a Var (non-native type), both are converted to Var and runtime computation uses the ZendVM
2. If both operands are Int/Float, Float takes precedence (Int → Float)
3. BigInt can safely promote Int; Decimal can promote Int and Float literals whose source text is preserved; BigFloat can promote Int/Float
4. No implicit conversion is performed between different Big* types, or between BigInt and Float

```php
// type promotion examples
$a = std::bigInt(100);
$b = 50;                // Int

$c = $a + $b;           // BigInt + Int → BigInt
                        // $b is automatically promoted to BigInt

$d = std::decimal("10.5");
$e = $d + 3;            // Decimal + Int → Decimal
                        // 3 is automatically promoted to Decimal

$f = std::bigFloat(1.5);
$g = $f + 2.0;          // BigFloat + Float → BigFloat
                        // 2.0 is automatically promoted to BigFloat
```

---

## 11. Automatic Detection of Extra-Long Literals

The AOT compiler automatically detects numeric literals that exceed the precision of native types and automatically converts them to the corresponding Big* type. You do **not need to wrap them manually**.

```php
// integer with 19 or more digits → automatically converted to BigInt
$a = 12345678901234567890;
echo $a->toString();  // "12345678901234567890"
// the compiler handles it automatically: equivalent to std::bigInt("12345678901234567890")

// decimal with 16 or more significant digits → automatically converted to Decimal
$b = 3.14159265358979323846;
// the compiler handles it automatically: equivalent to std::decimal("3.14159265358979323846")
```

**Detection rules**:

- pure digits, 19 or more digits → BigInt
- contains a decimal point or exponent, 16 or more significant digits → Decimal
- underscores `_` are disabled (e.g. `1_234_567_890_123_456_789_0`)

> **Recommended practice**: For critical precision, it is still recommended to explicitly use `std::bigInt("...")` or `std::decimal("...")` to ensure clear intent. Automatic detection is a convenience feature suited for rapid prototyping.

---

## 12. Limitations and Notes

### 12.1 Immutability

All Big* types are **immutable**. Every operation creates a new value:

```php
$a = std::bigInt(100);
$b = $a->add(50);    // $a is still 100, $b is 150
$c = $a + 50;        // $a is still 100, $c is 150
```

### 12.2 `++` / `--` Not Supported

See [Section 7.2](#72---are-unavailable). Use `+= 1` / `-= 1` instead.

### 12.3 BigFloat Does Not Support `%` and `**`

```php
$bf = std::bigFloat(10.0);
$bf %= 3;   // ❌ compile error
$bf ** 2;   // ❌ compile error
```

### 12.4 Decimal Does Not Support `**`

```php
$d = std::decimal("10.5");
$d ** 2;    // ❌ compile error
```

### 12.5 Cross Big* Types Cannot Be Implicitly Mixed

BigFloat, Decimal, and BigInt must be explicitly converted:

```php
$a = std::bigFloat(100.5);
$b = std::bigInt(200);
$c = $a + $b;  // ❌ compile error
// change to
$c = $a + std::bigFloat($b->toString());  // ✅
```

This restriction also applies to comparison operations. Before comparing, both sides must be explicitly converted to the same Big* type to avoid compiling to the wrong underlying resource type.

### 12.6 Boundaries and Exceptions

- BigInt negative right shifts use arithmetic right shift, for example `std::bigInt("-3") >> 1` yields `-2`.
- A negative bit index, negative `popCount()`, or an excessively large exponent throws `ValueError`.
- Division by zero throws `DivisionByZeroError`; converting to a PHP int beyond range throws `ArithmeticError`.
- When the absolute value of a BigFloat's exponent exceeds 10000, `toString()` automatically uses scientific notation to avoid constructing an excessively large string.

### 12.7 Cannot Run in the Normal PHP Interpreter

Big* types are a proprietary feature of the AOT compiler, relying on compile-time code generation and C++ underlying libraries. The source code cannot be directly interpreted and executed by the `php` command.

### 12.8 Enabling `use native_types`

Forgetting to add `use native_types` causes Big* variables to be treated as Var (generic type), losing most of the performance advantages of native types.

---

## 13. Complete Examples

### 13.1 Large Integer Factorial

```php
<?php
declare(strict_types=1);
use native_types;

/**
 * Compute the factorial of n, supporting arbitrarily large results
 */
function factorial(int $n): void {
    $result = std::bigInt(1);
    for ($i = 2; $i <= $n; $i++) {
        $result *= $i;
    }
    echo "{$n}! = " . $result->toString() . "\n";
    echo "digits: " . strlen($result->toString()) . "\n";
}

function main(): void {
    factorial(10);   // 10! = 3628800
    factorial(50);   // 3041409320171337804361260816606476884...
    factorial(100);  // 933262154439441526816992388562667004...
}
?>
```

### 13.2 Financial Computation: Order Details

```php
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // use Decimal to represent amounts exactly
    $price = std::decimal("19.99");
    $quantity = 3;
    $taxRate = std::decimal("0.08");

    $subtotal = $price * $quantity;
    $tax = $subtotal * $taxRate;
    $total = $subtotal + $tax;

    echo "unit price: " . $price->toString() . "\n";
    echo "quantity: {$quantity}\n";
    echo "subtotal: " . $subtotal->toString() . "\n";
    echo "tax: " . $tax->toString() . "\n";
    echo "total: " . $total->toString() . "\n";
}
?>
```

Output:

```
unit price: 19.99
quantity: 3
subtotal: 59.97
tax: 4.7976
total: 64.7676
```

### 13.3 High-Precision Pi Computation

```php
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // use BigFloat for high-precision math computation
    $pi = std::bigFloat("3.141592653589793238462643383279502884197");
    $radius = 100;

    // area of a circle
    $area = $pi * std::bigFloat($radius * $radius);
    echo "circle area: " . $area->toString() . "\n";

    // circumference of a circle
    $circumference = $pi * std::bigFloat(2 * $radius);
    echo "circumference: " . $circumference->toString() . "\n";

    // comparison
    $earthRadius = 6371;
    $earthArea = $pi * std::bigFloat($earthRadius * $earthRadius);
    echo "if the radius is {$earthRadius}km...\n";
    echo "approximate area: " . $earthArea->toInt() . " km²\n";
}
?>
```

### 13.4 Comprehensive Example: Mixing Multiple Types

```php
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // BigInt — large integer operations
    $big = std::bigInt("100000000000000000000");
    $big += std::bigInt("99999999999999999999");
    echo "BigInt: " . $big->toString() . "\n";

    // operators + comparison
    $a = std::bigInt(100);
    echo "BigInt + Int: " . ($a + 50)->toString() . "\n";
    echo "BigInt * 5: " . ($a * 5)->toString() . "\n";
    echo "BigInt > 50: " . (int)($a > 50) . "\n";
    echo "a == 100: " . (int)($a == 100) . "\n";

    // Unary minus
    $neg = -$a;
    echo "-a: " . $neg->toString() . "\n";

    // Decimal — exact decimal operations
    $price = std::decimal("99.99");
    $price *= 3;   // compound assignment
    echo "price × 3: " . $price->toString() . "\n";

    // comparison
    $d = std::decimal("100.25");
    echo "d > 50: " . (int)($d > 50) . "\n";
    echo "d != 100: " . (int)($d != 100) . "\n";

    // BigFloat — high-precision floating point
    $bf = std::bigFloat(3.14159);
    $bf *= 2.0;
    echo "pi × 2: " . $bf->toString() . "\n";

    // method chaining
    $result = std::bigInt(100)
        ->add(50)
        ->mul(3)
        ->sub(100)
        ->toString();
    echo "100 + 50 × 3 - 100 = " . $result . "\n";
}
?>
```

Output:

```
BigInt: 200000000000000000099
BigInt + Int: 150
BigInt * 5: 500
BigInt > 50: 1
a == 100: 1
-a: -100
price × 3: 299.97
d > 50: 1
d != 100: 1
pi × 2: 6.2831800000000000
100 + 50 × 3 - 100 = 350
```

---

## Further Reading

- **Type System Specification**: [`docs/NATIVE_TYPES.md`](NATIVE_TYPES.md) — complete type promotion rules, declaration syntax, and C++ API reference
- **BigInt PHPT Tests**: [`tests/compiler/bigint/`](../tests/compiler/bigint/) — integration tests for BigInt features
- **Decimal PHPT Tests**: [`tests/compiler/decimal/`](../tests/compiler/decimal/) — integration tests for Decimal features
- **BigFloat Integration Tests**: [`tests/compiler/bignumber/bigfloat_operators.phpt`](../tests/compiler/bignumber/bigfloat_operators.phpt) — BigFloat operator tests
- **C++ Runtime Header Files**:
  - [`phpx/include/phpx_big_int.h`](../../phpx/include/phpx_big_int.h) — BigInt C++ API
  - [`phpx/include/phpx_decimal.h`](../../phpx/include/phpx_decimal.h) — Decimal C++ API
  - [`phpx/include/phpx_big_float.h`](../../phpx/include/phpx_big_float.h) — BigFloat C++ API

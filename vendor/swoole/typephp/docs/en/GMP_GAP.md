# GMP Function Comparison Table (not implemented)

This document records the functions of the PHP GMP extension that have not yet been implemented in the BigInt type, as a reference for future development.

## Statistics

The GMP extension has 44 functions in total (excluding `gmp_init` and the alias `gmp_div`). 17 are covered, 27 are not covered.

## Implemented

| GMP function | BigInt method / operator | Description |
|----------|---------------------|------|
| `gmp_init` | `std::bigInt()` | construction |
| `gmp_add` | `add()` / `+` | addition |
| `gmp_sub` | `sub()` / `-` | subtraction |
| `gmp_mul` | `mul()` / `*` | multiplication |
| `gmp_div_q` | `div()` / `/` | division (quotient) |
| `gmp_div_r` | `mod()` / `%` | division (remainder) |
| `gmp_div_qr` | `divmod()` | quotient and remainder |
| `gmp_mod` | `mod()` / `%` | modulo |
| `gmp_pow` | `pow()` | power |
| `gmp_powm` | `powmod()` | modular exponentiation |
| `gmp_neg` | `neg()` / `-` (unary) | negation |
| `gmp_abs` | `abs()` | absolute value |
| `gmp_sqrt` | `sqrt()` | square root |
| `gmp_gcd` | `gcd()` | greatest common divisor |
| `gmp_cmp` | `cmp()` / `<=>` | comparison |
| `gmp_and` | `bitAnd()` / `&` | bitwise AND |
| `gmp_or` | `bitOr()` / `\|` | bitwise OR |
| `gmp_xor` | `bitXor()` / `^` | bitwise XOR |
| `gmp_com` | `bitNot()` / `~` | bitwise NOT |
| `gmp_testbit` | `testBit()` | bit test |
| `gmp_popcount` | `popCount()` | population count |
| `gmp_intval` | `toInt()` | to int |
| `gmp_strval` | `toString()` | to string |

## Not implemented (sorted by priority)

### High priority — commonly used number-theory functions

| GMP function | Suggested method name | Signature | Description |
|----------|-----------|------|------|
| `gmp_sign` | `sign()` | `(): int` | sign, returns -1/0/1 |
| `gmp_lcm` | `lcm($x)` | `(BigInt): BigInt` | least common multiple |
| `gmp_perfect_square` | `perfectSquare()` | `(): bool` | whether it is a perfect square |
| `gmp_perfect_power` | `perfectPower()` | `(): bool` | whether it is a perfect power |
| `gmp_prob_prime` | `probPrime($reps = 10)` | `(int): int` | probabilistic primality test (Miller-Rabin) |
| `gmp_nextprime` | `nextPrime()` | `(): BigInt` | next prime |
| `gmp_binomial` | `binomial($k)` | `(int): BigInt` | binomial coefficient C(n, k) |
| `gmp_fact` | `fact()` | `(): BigInt` | factorial n! |

### Medium priority — advanced number-theory functions

| GMP function | Suggested method name | Signature | Description |
|----------|-----------|------|------|
| `gmp_gcdext` | `gcdext($x)` | `(BigInt): array` | extended GCD, returns [g, s, t] such that g = s·a + t·b |
| `gmp_invert` | `invert($mod)` | `(BigInt): BigInt\|false` | modular inverse, returns false when it does not exist |
| `gmp_sqrtrem` | `sqrtrem()` | `(): array` | square root + remainder, returns [root, rem] |
| `gmp_jacobi` | `jacobi($x)` | `(BigInt): int` | Jacobi symbol |
| `gmp_legendre` | `legendre($x)` | `(BigInt): int` | Legendre symbol |
| `gmp_kronecker` | `kronecker($x)` | `(BigInt): int` | Kronecker symbol |

### Low priority — less commonly used

| GMP function | Suggested method name | Signature | Description |
|----------|-----------|------|------|
| `gmp_divexact` | `divExact($x)` | `(BigInt): BigInt` | exact division (used when divisibility is known; faster than ordinary division) |
| `gmp_root` | `root($n)` | `(int): BigInt` | n-th root (truncated) |
| `gmp_rootrem` | `rootrem($n)` | `(int): array` | n-th root + remainder |
| `gmp_hamdist` | `hamDist($x)` | `(BigInt): int` | Hamming distance |

### Not applicable — conflicts with the immutable design

| GMP function | Reason |
|----------|------|
| `gmp_setbit` | directly modifies the GMP object; BigInt is immutable |
| `gmp_clrbit` | directly modifies the GMP object; BigInt is immutable |

### To be evaluated

| GMP function | Description |
|----------|------|
| `gmp_scan0` | finds the first 0 bit from the specified position |
| `gmp_scan1` | finds the first 1 bit from the specified position |
| `gmp_random_bits` | generates a random-bit BigInt (needs a global seed; not suitable as an instance method) |
| `gmp_random_range` | random BigInt in a range (needs a global seed; not suitable as an instance method) |
| `gmp_random_seed` | sets the random seed (global state; not suitable as an instance method) |
| `gmp_import` | imports from a binary string |
| `gmp_export` | exports to a binary string |

## toString enhancement

| Missing feature | Description |
|---------|------|
| `toString($base)` | the current `toString()` only supports decimal. GMP's `gmp_strval` supports base 2-62 output |

## Update log

- 2026-05-27: initial version, compared against PHP 8.4.14 GMP extension

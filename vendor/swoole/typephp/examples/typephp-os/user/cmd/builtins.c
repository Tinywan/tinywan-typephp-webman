#include <stddef.h>
#include <unistd.h>

typedef unsigned __int128 uint128_t;
typedef __int128 int128_t;

static int fail(void)
{
    static const char message[] = "compiler-rt builtins: failed\n";
    (void) write(STDERR_FILENO, message, sizeof(message) - 1);
    return 1;
}

int main(int argc, char **argv)
{
    volatile uint128_t unsigned_numerator = ((uint128_t) 1 << 100) + 123456789u;
    volatile uint128_t unsigned_divisor = ((uint128_t) 1 << 37) + 19u;
    volatile int128_t signed_numerator = -(((int128_t) 1 << 99) + 7654321);
    volatile int128_t signed_divisor = ((int128_t) 1 << 35) + 11;
    uint128_t quotient;
    uint128_t remainder;
    int128_t signed_quotient;
    int128_t signed_remainder;
    (void) argc;
    (void) argv;

    quotient = unsigned_numerator / unsigned_divisor;
    remainder = unsigned_numerator % unsigned_divisor;
    if (quotient * unsigned_divisor + remainder != unsigned_numerator
        || remainder >= unsigned_divisor) {
        return fail();
    }

    signed_quotient = signed_numerator / signed_divisor;
    signed_remainder = signed_numerator % signed_divisor;
    if (signed_quotient * signed_divisor + signed_remainder != signed_numerator
        || signed_remainder > 0) {
        return fail();
    }

    (void) write(STDOUT_FILENO, "compiler-rt builtins: OK\n",
        sizeof("compiler-rt builtins: OK\n") - 1);
    return 0;
}

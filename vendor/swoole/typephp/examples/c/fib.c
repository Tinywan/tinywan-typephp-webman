#include <stdio.h>
#include <stdlib.h>
#include <string.h>

int fib(int n) {
    if (n == 1 || n == 2) {
        return 1;
    } else {
        return fib(n - 1) + fib(n - 2);
    }
}

int main(int argc, char **argv) {
    long long n = atoi(argv[1]);
    printf("斐波那契数列的第%d项是：%d\n", n, fib(n));
    return 0;
}

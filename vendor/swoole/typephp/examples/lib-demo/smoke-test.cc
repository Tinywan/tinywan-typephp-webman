#include "include/typephp_lib_demo.h"

#include <iostream>

int main()
{
    const int first = typephp_lib_demo_add(20, 22);
    const int second = typephp_lib_demo_add(-10, 7);
    if (first != 42 || second != -3) {
        std::cerr << "unexpected results: " << first << ", " << second << '\n';
        return 1;
    }
    return 0;
}

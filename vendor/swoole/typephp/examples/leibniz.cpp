#include <cstdio>
#include <cstdlib>

double pi = 1.0;

int main()
{
    const unsigned rounds = 100000000u + 2u; // rounds + 2, moved out of the loop
    
    for (long i=2u ; i < rounds ; ++i) // use ++i instead of i++
    {
        double x = -1.0 + 2.0 * (i & 0x1); // allows vectorization
        pi += (x / (2u * i - 1u)); // double / unsigned = double
    }
    
    pi *= 4;
    std::printf("%.16f\n", pi); // print 16 decimal digits of pi
    return EXIT_SUCCESS;
}



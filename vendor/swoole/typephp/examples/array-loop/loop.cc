#include <iostream>
#include <vector>
#include <cstdlib>
#include <ctime>
#include <chrono>

int main(int argc, char* argv[]) {
    std::srand(static_cast<unsigned>(std::time(nullptr)));

    long u = std::stoi(argv[1]);
    std::cout << "u: " << u << "\n";

    long r = std::rand() % 10001;
    std::vector<long> a(10000, 0);

    auto begin = std::chrono::high_resolution_clock::now();

    for (int i = 0; i < 10000; i++) {
        for (int j = 0; j < 100000; j++) {
            a[i] += j % u;
        }
        a[i] += r;
    }

    std::cout << a[r] << "\n";

    auto end = std::chrono::high_resolution_clock::now();
    std::chrono::duration<double> diff = end - begin;
    std::cout << "sec: " << diff.count() << "\n";

    return 0;
}

#include <nfft3.h>
#include <iostream>
#include <vector>

int main() {
    const int d = 1;          // 维度（1D）
    const int M = 1000;       // 非均匀点数量
    const int N[] = {2048};   // 均匀网格大小（频域）

    // 分配内存
    nfft_plan plan;
    nfft_init_1d(&plan, N[0], M);

    // 设置非均匀采样点 x_j ∈ [-0.5, 0.5)
    for (int j = 0; j < M; ++j) {
        plan.x[j] = (double)j / M - 0.5;  // 示例：均匀分布，实际可任意
    }

    // 设置源系数 c_j（复数）
    for (int j = 0; j < M; ++j) {
        plan.f_hat[j][0] = 1.0;  // 实部
        plan.f_hat[j][1] = 0.0;  // 虚部
    }

    // 执行 NFFT（Type 1）
    nfft_adjoint(&plan);  // 注意：NFFT3 中 Type 1 用 nfft_adjoint！

    // 输出部分结果
    for (int k = 0; k < 10; ++k) {
        std::cout << "f[" << k << "] = " << plan.f[k] << std::endl;
    }

    // 清理
    nfft_finalize(&plan);
    return 0;
}
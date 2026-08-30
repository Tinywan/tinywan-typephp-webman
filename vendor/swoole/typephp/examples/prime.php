<?php


// 埃拉托斯特尼筛法求素数
function sieveOfEratosthenes(int $limit)
{
    if ($limit < 2) return [];

    // 初始化布尔数组，索引代表数字，值代表是否为素数
    $isPrime = array_fill(0, $limit + 1, true);
    $isPrime[0] = false;
    $isPrime[1] = false; // 0 和 1 不是素数

    for ($i = 2; $i * $i <= $limit; $i++) {
        if ($isPrime[$i]) {
            // 标记 i 的所有倍数为非素数
            for ($j = $i * $i; $j <= $limit; $j += $i) {
                $isPrime[$j] = false;
            }
        }
    }

    // 收集所有素数
    $primes = [];
    for ($num = 2; $num <= $limit; $num++) {
        if ($isPrime[$num]) {
            $primes[] = $num;
        }
    }

    return $primes;
}

function main()
{
    global $argv;
    $n = isset($argv[2]) ? (int)$argv[2] : 10000000;
    $begin = microtime(true);
    // 参数校验
    if ($n < 2) {
        fwrite(STDERR, "请输入一个大于等于2的整数作为上限。\n");
        exit(1);
    }
    $primes = sieveOfEratosthenes($n);
    var_dump(count($primes));
    var_dump(microtime(true) - $begin);
    // 输出每个素数（每行一个）
//    foreach ($primes as $prime) {
//        echo $prime . "\n";
//    }
}

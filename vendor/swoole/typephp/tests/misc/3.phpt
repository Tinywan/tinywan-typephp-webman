--TEST--
mixed: 3
--FILE--
<?php
function main()
{
    $A = [4, 2, 5, 7];
    print_r(sortArrayByParity1($A));
}

function sortArrayByParity1($A)
{
    $len = count($A);
    $j   = $len - 2; //  j为偶数的下标
    // i为奇数的下标
    for ($i = $len - 1; $i >= 0; $i = $i - 2) {
        // 奇数位需要调换的偶数
        if ($A[$i] % 2 !== $i % 2) {
            // 偶数位不需要调换的偶数
            while ($A[$j] % 2 === $j % 2) {
                $j = $j - 2;
            }
            [$A[$i], $A[$j]] = [$A[$j], $A[$i]];
        }
    }
    return $A;
}
?>
--EXPECT--
Array
(
    [0] => 4
    [1] => 5
    [2] => 2
    [3] => 7
)


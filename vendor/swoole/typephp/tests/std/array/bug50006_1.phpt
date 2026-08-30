--TEST--
Bug #50006 (Segfault caused by uksort()) - usort variant
--FILE--
<?php
function magic_sort_cmp($a, $b)
{
    $a = substr($a, 1);
    $b = substr($b, 1);
    if (!$a) {
        return $b ? -1 : 0;
    }
    if (!$b) {
        return 1;
    }
    return magic_sort_cmp($a, $b);
}
function main()
{
    $data = array('bar-bazbazbaz.', 'bar-bazbazbaz-', 'foo');
    usort($data, 'magic_sort_cmp');
    print_r($data);
}
?>
--EXPECT--
Array
(
    [0] => foo
    [1] => bar-bazbazbaz.
    [2] => bar-bazbazbaz-
)

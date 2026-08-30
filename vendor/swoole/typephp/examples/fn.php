<?php
function fn_test_yeild()
{
    echo "第一次调用前\n";
    yield 1;          // 产生值 1，并暂停
    echo "两次调用之间\n";
    yield 2;          // 产生值 2，并暂停
    echo "最后一次调用后\n";
    yield 3;
}
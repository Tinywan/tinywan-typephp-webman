--TEST--
default array property
--FILE--
<?php

class Test
{
    public static $numberMap = [
        0   => '零',
        1   => '一',
        2   => '二',
        3   => '三',
        4   => '四',
        5   => '五',
        6   => '六',
        7   => '七',
        8   => '八',
        9   => '九',
        '-' => '负',
        '.' => '点',
    ];
}

function main(): void
{
    var_dump(Test::$numberMap);
}
?>
--EXPECT--
array(12) {
  [0]=>
  string(3) "零"
  [1]=>
  string(3) "一"
  [2]=>
  string(3) "二"
  [3]=>
  string(3) "三"
  [4]=>
  string(3) "四"
  [5]=>
  string(3) "五"
  [6]=>
  string(3) "六"
  [7]=>
  string(3) "七"
  [8]=>
  string(3) "八"
  [9]=>
  string(3) "九"
  ["-"]=>
  string(3) "负"
  ["."]=>
  string(3) "点"
}

--TEST--
GH-18897 (printf: empty precision is interpreted as precision 6, not as precision 0)
--SKIPIF--
<?php
echo 'skip printf empty precision (%.f) differs from C: C uses precision 6, PHP uses precision 0';
?>
--FILE--
<?php
printf("%.f\n", 3.1415926535);
printf("%.0f\n", 3.1415926535);
?>
--EXPECT--
3
3

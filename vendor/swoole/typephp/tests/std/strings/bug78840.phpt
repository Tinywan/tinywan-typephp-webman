--TEST--
Bug #78840 (imploding $GLOBALS crashes)
--SKIPIF--
<?php
echo 'skip AOT C++ compilation failed';
?>
--FILE--
<?php
$glue = '';
@implode($glue, $GLOBALS);
echo "done\n";
?>
--EXPECT--
done

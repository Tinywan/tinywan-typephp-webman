--TEST--
Bug #76778 (array_reduce leaks memory if callback throws exception)
--SKIPIF--
<?php die("skip memory leak detection in test environment adds output"); ?>
--FILE--
<?php
try {
    array_reduce(
        [1],
        function ($carry, $item) {
            throw new Exception;
        },
        range(1, 3)
    );
} catch (Exception $e) {
}
echo "===DONE===\n";
?>
--EXPECT--
===DONE===

--TEST--
Test dirname() function : error conditions
--SKIPIF--
<?php
echo 'skip Test output differs under AOT compilation';
?>
--FILE--
<?php
echo "*** Testing error conditions ***\n";

// Bad arg
try {
    dirname("/var/tmp/bar.gz", 0);
} catch (\ValueError $e) {
    echo $e->getMessage() . "\n";
}

?>
--EXPECT--
*** Testing error conditions ***
dirname(): Argument #2 ($levels) must be greater than or equal to 1

--TEST--
Loop var optimizer: for counter with strlen bound
--FILE--
<?php
function main(): void {
    $s = "abcdef";
    $last = -1;

    for ($i = 0; $i <= strlen($s); $i++) {
        $last = $i;
    }

    var_dump($i);
    var_dump($last);
}
?>
--EXPECT--
int(7)
int(6)

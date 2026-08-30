--TEST--
Bug #29944 (function defined in switch crashes PHP)
--FILE--
<?php
function foo($bar) {
    if (preg_match('/\d/', $bar)) return true;
    return false;
}

function main() {
    $a = 1;
    $b = "1";
    switch ($a) {
        case 1:
            echo foo($b);
    }
}
?>
--EXPECT--
1

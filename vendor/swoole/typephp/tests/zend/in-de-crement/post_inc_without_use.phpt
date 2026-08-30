--TEST--
POST_INC without use during DFA optimization
--FILE--
<?php

function main() {
    $n = 10;
    for ($i = 0; $i < $n; !$i++) {}
}

?>
--EXPECT--

--TEST--
shell_exec / backtick operator
--FILE--
<?php

function main() {
    $output = `echo hello`;
    var_dump(trim($output));

    $who = "world";
    $output2 = `echo $who`;
    var_dump(trim($output2));

    echo "done\n";
}

?>
--EXPECT--
string(5) "hello"
string(5) "world"
done

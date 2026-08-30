--TEST--
eval() language construct
--FILE--
<?php

function main() {
    // eval with return value
    $result = eval('return 10 + 5;');
    var_dump($result);

    // eval returning string
    $msg = eval('return "hello world";');
    var_dump($msg);

    // eval with static expressions
    $sum = 0;
    for ($i = 1; $i <= 3; $i++) {
        eval('$GLOBALS["__total"] = ($GLOBALS["__total"] ?? 0) + ' . $i . ';');
    }
    var_dump($GLOBALS["__total"]);

    echo "done\n";
}

?>
--EXPECT--
int(15)
string(11) "hello world"
int(6)
done

--TEST--
exit/die language constructs
--FILE--
<?php

function testExit(int $code): void {
    if ($code > 0) {
        echo "should not reach here\n";
    }
    echo "code is zero\n";
}

function processValue(mixed $val): string {
    if (!is_string($val)) {
        die("not a string");
    }
    return "string: " . $val;
}

function main() {
    testExit(0);

    $result = processValue("hello");
    var_dump($result);

    $status = 42;
    $x = $status == 0 ? 'zero' : 'non-zero';
    var_dump($x);
}

?>
--EXPECT--
code is zero
string(13) "string: hello"
string(8) "non-zero"

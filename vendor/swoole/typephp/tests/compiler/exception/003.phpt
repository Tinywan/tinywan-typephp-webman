--TEST--
try catch 2
--FILE--
<?php
namespace PhpTest {
class MyException extends \Exception {
}
}

namespace {
function main() {
    try {
        throw new \PhpTest\MyException('test error');
    } catch (\PhpTest\MyException $e) {
        var_dump($e->getMessage());
    }
}
}
?>
--EXPECT--
string(10) "test error"

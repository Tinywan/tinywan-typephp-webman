--TEST--
static calls
--FILE--
<?php
class Worker
{
    public static function stopAll(int $code = 999, string $log = 'foo'): void
    {
        var_dump($code, $log);
    }
}

function main() {
      Worker::stopAll();
}
?>
--EXPECTF--
int(999)
string(3) "foo"

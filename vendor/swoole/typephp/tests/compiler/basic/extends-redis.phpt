--TEST--
extends redis
--SKIPIF--
<?php
if (!extension_loaded('redis')) {
    die('skip redis extension is not available');
}
try {
    $redis = new Redis();
    if (!$redis->connect('127.0.0.1', 6379, 0.2)) {
        die('skip redis server is not available');
    }
    $redis->close();
} catch (Throwable) {
    die('skip redis server is not available');
}
?>
--FILE--
<?php
class MyRedis extends \redis
{
    public function __construct(?array $options = null)
    {
        parent::__construct($options);
    }
}

function main()
{
    $o = new MyRedis;
    $o->connect('127.0.0.1', 6379);
    $uuid = uniqid();
    var_dump($o->set('key', $uuid));
    var_dump($o->get('key') === $uuid);
}
?>
--EXPECT--
bool(true)
bool(true)

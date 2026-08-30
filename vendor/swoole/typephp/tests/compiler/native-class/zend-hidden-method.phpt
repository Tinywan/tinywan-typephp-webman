--TEST--
Native class: native-only methods on Zend objects use direct calls and stay out of Zend metadata
--FILE--
<?php

#[Native]
class NativeMethodArgument
{
    public int $value = 42;
}

class ZendMethodHost
{
    public function read(NativeMethodArgument $value): int
    {
        return $value->value;
    }
}

function main(): void
{
    $host = new ZendMethodHost();
    $value = new NativeMethodArgument();
    var_dump($host->read($value));
    var_dump(method_exists($host, 'read'));
}
?>
--EXPECT--
int(42)
bool(false)

--TEST--
call abstract base method through concrete object
--FILE--
<?php
abstract class Base
{
    abstract public function run(): string;
}

class Impl extends Base
{
    public function run(): string
    {
        return 'ok';
    }
}

function create(string $type): Base
{
    return match ($type) {
        'a' => new Impl(),
        default => new Impl(),
    };
}

function main(): int
{
    $obj = create('a');
    $r = $obj->run(); // ← CRASH 发生在此虚方法调用
    echo "result: $r\n";
    if ($r === 'ok') {
        echo "ALL OK\n";
        return 0;
    }
    return 1;
}
?>
--EXPECT--
result: ok
ALL OK

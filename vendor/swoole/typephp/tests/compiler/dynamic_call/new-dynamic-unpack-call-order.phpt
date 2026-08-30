--TEST--
dynamic new with unpacked constructor args then method call preserves order
--FILE--
<?php

class DynamicNewOrder
{
    public function __construct(public string $name)
    {
        echo "ctor:$name\n";
    }

    public function run(string $suffix): string
    {
        echo "run:$suffix\n";
        return $this->name . ':' . $suffix;
    }
}

function make_new_args(): array
{
    echo "new-args\n";
    return ['object'];
}

function make_call_arg(): string
{
    echo "call-arg\n";
    return 'method';
}

function main(): void
{
    $class = DynamicNewOrder::class;
    var_dump((new $class(...make_new_args()))->run(make_call_arg()));
}
?>
--EXPECT--
new-args
ctor:object
call-arg
run:method
string(13) "object:method"

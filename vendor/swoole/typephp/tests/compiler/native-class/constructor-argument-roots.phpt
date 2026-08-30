--TEST--
Native class: constructor arguments are evaluated left-to-right and rooted
--FILE--
<?php

#[Native]
class NativeConstructorArgument
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

#[Native]
class NativeConstructorPressure
{
    public int $value;
}

#[Native]
class NativeConstructorPair
{
    public NativeConstructorArgument $first;
    public NativeConstructorArgument $second;

    public function __construct(
        NativeConstructorArgument $first,
        NativeConstructorArgument $second,
    ) {
        $this->first = $first;
        $this->second = $second;
    }
}

function makeNativeConstructorArgument(string $name): NativeConstructorArgument
{
    echo 'make:', $name, PHP_EOL;
    if ($name === 'B') {
        for ($i = 0; $i < 300000; $i++) {
            $filler = new NativeConstructorPressure();
        }
    }
    return new NativeConstructorArgument($name);
}

function main(): void
{
    $pair = new NativeConstructorPair(
        makeNativeConstructorArgument('A'),
        makeNativeConstructorArgument('B'),
    );
    echo $pair->first->name, ':', $pair->second->name, PHP_EOL;
}

?>
--EXPECT--
make:A
make:B
A:B

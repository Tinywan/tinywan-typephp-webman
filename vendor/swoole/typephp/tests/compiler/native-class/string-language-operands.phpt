--TEST--
Native class: include and eval operands use the declared toString method
--FILE--
<?php

#[Native]
class NativeStringOperand
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}

function main(): void
{
    $path = new NativeStringOperand(__DIR__ . '/include-native-path.inc');
    include $path;

    $source = new NativeStringOperand('echo "evaluated\\n";');
    eval($source);
}

?>
--EXPECT--
included
evaluated

--TEST--
Native class: interface property hook contracts remain compile-time only
--FILE--
<?php

interface NativeReadableName
{
    public string $name { get; }
}

interface NativeMutableName extends NativeReadableName
{
    public string $name { get; set; }
}

#[Native]
class NativeHookedName implements NativeMutableName
{
    private string $stored = 'initial';

    public string $name {
        get => strtoupper($this->stored);
        set => $this->stored = trim($value);
    }
}

function main(): void
{
    $value = new NativeHookedName();
    echo $value->name, "\n";
    $value->name = ' changed ';
    echo $value->name, "\n";

    // The interface remains registered for ordinary PHP code, while the
    // Native implementation exists only in the compile-time class graph.
    var_dump(interface_exists(NativeMutableName::class));
    var_dump(class_exists(NativeHookedName::class));
}
?>
--EXPECT--
INITIAL
CHANGED
bool(true)
bool(false)

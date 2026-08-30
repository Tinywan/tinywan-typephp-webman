--TEST--
Native class: tracing GC collects cycles and runs destructors once
--FILE--
<?php

#[Native]
class NativeNode
{
    public ?NativeNode $next = null;
    public string $name = '';

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function __destruct()
    {
        echo $this->name;
    }
}

function main(): void
{
    $a = new NativeNode('A');
    $b = new NativeNode('B');
    $a->next = $b;
    $b->next = $a;
    $a = null;
    $b = null;
    echo "done\n";
}

?>
--EXPECT--
done
BA

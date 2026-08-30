--TEST--
Native class: GC finalization runs destructors from derived to base exactly once
--FILE--
<?php

#[Native]
class NativeDestructorBase
{
    public function __destruct()
    {
        echo 'B';
    }
}

#[Native]
class NativeDestructorChild extends NativeDestructorBase
{
    public function __destruct()
    {
        echo 'C';
    }
}

function main(): void
{
    $value = new NativeDestructorChild();
    $value = null;
    echo "done\n";
}

?>
--EXPECT--
done
CB

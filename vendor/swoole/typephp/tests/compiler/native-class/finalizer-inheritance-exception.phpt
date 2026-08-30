--TEST--
Native class: a throwing derived finalizer does not skip base finalizers
--FILE--
<?php

#[Native]
class NativeThrowingFinalizerBase
{
    public function __destruct()
    {
        global $events;
        $events[] = 'base';
    }
}

#[Native]
class NativeThrowingFinalizerChild extends NativeThrowingFinalizerBase
{
    public function __destruct()
    {
        global $events;
        $events[] = 'child';
        throw new RuntimeException('child finalizer failed');
    }
}

#[Native]
class NativeFinalizerPressureObject
{
    public int $value;
}

function collectThrowingFinalizer(): void
{
    for ($i = 0; $i < 800000; $i++) {
        try {
            $filler = new NativeFinalizerPressureObject();
        } catch (RuntimeException $error) {
            echo $error->getMessage(), "\n";
            return;
        }
    }
    echo "finalizer did not run\n";
}

function main(): void
{
    global $events;
    $events = [];

    $object = new NativeThrowingFinalizerChild();
    $object = null;
    collectThrowingFinalizer();
    var_dump($events);
}

?>
--EXPECT--
child finalizer failed
array(2) {
  [0]=>
  string(5) "child"
  [1]=>
  string(4) "base"
}

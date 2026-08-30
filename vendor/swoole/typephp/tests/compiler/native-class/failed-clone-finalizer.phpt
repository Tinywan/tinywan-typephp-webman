--TEST--
Native class: an unreachable clone whose __clone throws is finalized by the GC
--FILE--
<?php

#[Native]
class NativeFailedCloneFinalizer
{
    public string $kind = 'source';

    public function __clone(): void
    {
        $this->kind = 'clone';
        throw new RuntimeException('clone failed');
    }

    public function __destruct()
    {
        global $finalizedKinds;
        $finalizedKinds[] = $this->kind;
    }
}

#[Native]
class NativeFailedClonePressure
{
    public int $value;
}

function forceFailedCloneCollection(): void
{
    for ($i = 0; $i < 800000; $i++) {
        new NativeFailedClonePressure();
    }
}

function main(): void
{
    global $finalizedKinds;
    $finalizedKinds = [];

    $source = new NativeFailedCloneFinalizer();
    try {
        clone $source;
    } catch (RuntimeException $error) {
        echo $error->getMessage(), "\n";
    }

    forceFailedCloneCollection();
    var_dump($finalizedKinds);
    echo $source->kind, "\n";
}
?>
--EXPECT--
clone failed
array(1) {
  [0]=>
  string(5) "clone"
}
source

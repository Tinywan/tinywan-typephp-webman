--TEST--
Native class: constructor, clone and finalizer exceptions preserve heap invariants
--FILE--
<?php

class NativeLifecycleProbe
{
    public function __construct(private string $name)
    {
    }

    public function __destruct()
    {
        global $releasedProbes;
        $releasedProbes[] = $this->name;
    }
}

#[Native]
class NativeConstructorFailure
{
    public object $probe;

    public function __construct()
    {
        $this->probe = new NativeLifecycleProbe('constructor');
        throw new RuntimeException('constructor failed');
    }

    public function __destruct()
    {
        global $nativeFinalizers;
        $nativeFinalizers[] = 'constructor';
    }
}

#[Native]
class NativeCloneFailure
{
    public object $probe;

    public function __construct()
    {
        $this->probe = new NativeLifecycleProbe('source');
    }

    public function __clone(): void
    {
        $this->probe = new NativeLifecycleProbe('clone');
        throw new RuntimeException('clone failed');
    }
}

#[Native]
class NativeThrowingFinalizer
{
    public function __destruct()
    {
        global $nativeFinalizers;
        $nativeFinalizers[] = 'throwing';
        throw new RuntimeException('finalizer failed');
    }
}

#[Native]
class NativeLifecycleFiller
{
    public int $value;
}

function allocateUntilFinalizerRuns(): void
{
    global $nativeFinalizers;
    for ($i = 0; $i < 800000; $i++) {
        try {
            $filler = new NativeLifecycleFiller();
        } catch (RuntimeException $error) {
            echo $error->getMessage(), "\n";
            return;
        }
    }
    echo "finalizer did not run\n";
}

function main(): void
{
    global $releasedProbes, $nativeFinalizers;
    $releasedProbes = [];
    $nativeFinalizers = [];

    try {
        $failed = new NativeConstructorFailure();
    } catch (RuntimeException $error) {
        echo $error->getMessage(), "\n";
    }
    var_dump($releasedProbes, $nativeFinalizers);

    $source = new NativeCloneFailure();
    try {
        $copy = clone $source;
    } catch (RuntimeException $error) {
        echo $error->getMessage(), "\n";
    }
    var_dump($releasedProbes);

    $throwing = new NativeThrowingFinalizer();
    $throwing = null;
    allocateUntilFinalizerRuns();
    var_dump($nativeFinalizers);
}

?>
--EXPECT--
constructor failed
array(1) {
  [0]=>
  string(11) "constructor"
}
array(0) {
}
clone failed
array(2) {
  [0]=>
  string(11) "constructor"
  [1]=>
  string(5) "clone"
}
finalizer failed
array(1) {
  [0]=>
  string(8) "throwing"
}

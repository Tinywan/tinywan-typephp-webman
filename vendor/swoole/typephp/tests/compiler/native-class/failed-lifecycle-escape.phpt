--TEST--
Native class: objects published before constructor or clone failure never become dangling
--FILE--
<?php

class NativeFailedLifecycleProbe
{
    public function __construct(public string $name)
    {
    }

    public function __destruct()
    {
        global $released;
        $released[] = $this->name;
    }
}

#[Native]
class NativeFailedConstructionEscape
{
    public object $probe;
    public int $value = 42;

    public function __construct()
    {
        global $failedConstruction;
        $this->probe = new NativeFailedLifecycleProbe('constructor field');
        $failedConstruction = $this;
        throw new RuntimeException('constructor failed');
    }

    public function __destruct()
    {
        global $finalized;
        $finalized[] = 'constructor object';
    }
}

#[Native]
class NativeFailedCloneEscape
{
    public object $probe;
    public string $kind = 'source';

    public function __construct()
    {
        $this->probe = new NativeFailedLifecycleProbe('source field');
    }

    public function __clone(): void
    {
        global $failedClone;
        $this->kind = 'clone';
        $this->probe = new NativeFailedLifecycleProbe('clone field');
        $failedClone = $this;
        throw new RuntimeException('clone failed');
    }

    public function __destruct()
    {
        global $finalized;
        $finalized[] = $this->kind;
    }
}

#[Native]
class NativeFailedLifecyclePressure
{
    public int $value;
}

function forceNativeCollection(): void
{
    for ($i = 0; $i < 800000; $i++) {
        $filler = new NativeFailedLifecyclePressure();
    }
}

function main(): void
{
    global $failedConstruction, $failedClone, $released, $finalized;
    $released = [];
    $finalized = [];

    try {
        $unused = new NativeFailedConstructionEscape();
    } catch (RuntimeException $error) {
        echo $error->getMessage(), "\n";
    }
    var_dump($failedConstruction->value, $failedConstruction->probe->name);
    $failedConstruction = null;
    forceNativeCollection();
    var_dump($released, $finalized);

    $source = new NativeFailedCloneEscape();
    try {
        $unusedClone = clone $source;
    } catch (RuntimeException $error) {
        echo $error->getMessage(), "\n";
    }
    var_dump($failedClone->kind, $failedClone === $source, $failedClone->probe->name);
    $failedClone = null;
    forceNativeCollection();
    var_dump($released, $finalized);

    $source = null;
    forceNativeCollection();
    var_dump($released, $finalized);
}

?>
--EXPECT--
constructor failed
int(42)
string(17) "constructor field"
array(1) {
  [0]=>
  string(17) "constructor field"
}
array(0) {
}
clone failed
string(5) "clone"
bool(false)
string(11) "clone field"
array(2) {
  [0]=>
  string(17) "constructor field"
  [1]=>
  string(11) "clone field"
}
array(1) {
  [0]=>
  string(5) "clone"
}
array(3) {
  [0]=>
  string(17) "constructor field"
  [1]=>
  string(11) "clone field"
  [2]=>
  string(12) "source field"
}
array(2) {
  [0]=>
  string(5) "clone"
  [1]=>
  string(6) "source"
}

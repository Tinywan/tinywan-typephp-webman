--TEST--
PHP 8.5 clone-with respects property scope and unlocks readonly properties
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip requires PHP 8.5');
}
?>
--FILE--
<?php

class CloneWithScopeBase
{
    private int $privateValue = 1;
    protected int $protectedValue = 2;
    public readonly int $readonlyValue;

    public function __construct()
    {
        $this->readonlyValue = 3;
    }

    public function withPrivateAndReadonly(): self
    {
        return clone($this, [
            'privateValue' => 10,
            'readonlyValue' => 30,
        ]);
    }

    public function values(): array
    {
        return [$this->privateValue, $this->protectedValue, $this->readonlyValue];
    }

    public function withInvalidPrivate(): self
    {
        return clone($this, ['privateValue' => 'invalid']);
    }

    public function reinitializeReadonly(int $value): void
    {
        $this->readonlyValue = $value;
    }
}

class CloneWithScopeChild extends CloneWithScopeBase
{
    public function withProtected(): self
    {
        return clone($this, ['protectedValue' => 20]);
    }
}

function main(): void
{
    $source = new CloneWithScopeChild();
    $privateCopy = $source->withPrivateAndReadonly();
    $protectedCopy = $source->withProtected();

    var_dump($source->values());
    var_dump($privateCopy->values());
    var_dump($protectedCopy->values());

    // PHP leaves readonly slots omitted from the update array reinitializable
    // once on the clone. A slot explicitly updated by clone-with is locked.
    $protectedCopy->reinitializeReadonly(31);
    var_dump($protectedCopy->values());

    try {
        $privateCopy->reinitializeReadonly(40);
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }

    try {
        $source->withInvalidPrivate();
    } catch (TypeError $error) {
        echo $error::class, ":private\n";
    }

    try {
        clone($source, ['protectedValue' => 99]);
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }

    try {
        clone($source, ['readonlyValue' => 99]);
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(2)
  [2]=>
  int(30)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(20)
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(20)
  [2]=>
  int(31)
}
Cannot modify readonly property CloneWithScopeBase::$readonlyValue
TypeError:private
Cannot access protected property CloneWithScopeChild::$protectedValue
Cannot modify protected(set) readonly property CloneWithScopeBase::$readonlyValue from global scope

--TEST--
PHP 8.5 clone-with uses the consuming class scope inside trait methods
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip requires PHP 8.5');
}
?>
--FILE--
<?php

trait CloneWithTraitScope
{
    private int $traitPrivate = 1;

    public function withTraitPrivate(int $value): self
    {
        return clone($this, ['traitPrivate' => $value]);
    }

    public function traitPrivate(): int
    {
        return $this->traitPrivate;
    }
}

class CloneWithTraitBase
{
    use CloneWithTraitScope;
}

class CloneWithTraitChild extends CloneWithTraitBase {}

function main(): void
{
    $source = new CloneWithTraitChild();
    $copy = $source->withTraitPrivate(9);

    var_dump($source->traitPrivate());
    var_dump($copy->traitPrivate());
}
?>
--EXPECT--
int(1)
int(9)

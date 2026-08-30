--TEST--
PHP 8.5 clone-with preserves TypePHP property handlers and invokes hooks after __clone
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip requires PHP 8.5');
}
?>
--FILE--
<?php

class CloneWithHookValue
{
    public int $value {
        get => $this->value * 2;
        set {
            echo 'set:', $value, "\n";
            if ($value < 0) {
                throw new InvalidArgumentException('negative value');
            }
            $this->value = $value + 1;
        }
    }

    public function __construct()
    {
        $this->value = 1;
    }

    public function __clone(): void
    {
        echo '__clone:', $this->value, "\n";
    }

    public function withValue(int $value): self
    {
        return clone($this, ['value' => $value]);
    }
}

class CloneWithHookChild extends CloneWithHookValue {}

function read_clone_hook_dynamically(mixed $object): int
{
    return $object->value;
}

function main(): void
{
    $source = new CloneWithHookChild();
    $copy = $source->withValue(5);

    var_dump(read_clone_hook_dynamically($source));
    var_dump(read_clone_hook_dynamically($copy));

    try {
        $source->withValue(-1);
    } catch (InvalidArgumentException $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
set:1
__clone:4
set:5
int(4)
int(12)
__clone:4
set:-1
negative value

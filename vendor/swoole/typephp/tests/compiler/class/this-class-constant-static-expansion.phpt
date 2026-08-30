--TEST--
$this class constants resolve from the current TypePHP class
--FILE--
<?php

class ClassConstantParent
{
    protected const INHERITED = 'parent';
}

class ClassConstantReader extends ClassConstantParent
{
    public const VALUE = 23;
    private const PRIVATE_VALUE = 'private';

    public function values(): array
    {
        return [$this::VALUE, $this::PRIVATE_VALUE, $this::INHERITED];
    }
}

function main(): void
{
    var_dump((new ClassConstantReader())->values());
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(23)
  [1]=>
  string(7) "private"
  [2]=>
  string(6) "parent"
}

--TEST--
Only compile-time class constants initialize hoisted local declarations
--FILE--
<?php

class LocalClassConstantBase
{
    public const LIMIT = 128;
}

class LocalClassConstantValues extends LocalClassConstantBase
{
    public const LABEL = 'typephp';

    public function read(): array
    {
        $selfValue = self::LABEL;
        $parentValue = parent::LIMIT;
        $className = self::class;
        $lateStatic = static::LABEL;
        $external = \DateTimeInterface::ATOM;

        return [$selfValue, $parentValue, $className, $lateStatic, $external];
    }
}

function main(): void
{
    var_dump((new LocalClassConstantValues())->read());
}
?>
--EXPECT--
array(5) {
  [0]=>
  string(7) "typephp"
  [1]=>
  int(128)
  [2]=>
  string(24) "LocalClassConstantValues"
  [3]=>
  string(7) "typephp"
  [4]=>
  string(13) "Y-m-d\TH:i:sP"
}

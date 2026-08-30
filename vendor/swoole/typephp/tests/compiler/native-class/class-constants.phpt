--TEST--
Native class: constants resolve entirely at compile time across inheritance
--FILE--
<?php

namespace NativeConstantTest {

#[\Native]
class BaseConfig
{
    private const int PRIVATE_VALUE = 7;
    protected const string PREFIX = 'native';
    public const array ITEMS = [1, 2, 3];

    public function privateValue(int $value = self::PRIVATE_VALUE): int
    {
        return $value;
    }
}

#[\Native]
class ChildConfig extends BaseConfig
{
    public const string CLASS_NAME = self::class;
    public const string PARENT_NAME = parent::class;
    public const string LABEL = parent::PREFIX;

    public function label(string $value = self::LABEL): string
    {
        return $value;
    }
}
}

namespace {
function main(): void
{
    $config = new \NativeConstantTest\ChildConfig();
    var_dump(
        \NativeConstantTest\ChildConfig::CLASS_NAME,
        \NativeConstantTest\ChildConfig::PARENT_NAME,
        \NativeConstantTest\ChildConfig::LABEL,
        \NativeConstantTest\ChildConfig::ITEMS,
        $config->privateValue(),
        $config->label(),
    );
}
}

?>
--EXPECT--
string(30) "NativeConstantTest\ChildConfig"
string(29) "NativeConstantTest\BaseConfig"
string(6) "native"
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
int(7)
string(6) "native"

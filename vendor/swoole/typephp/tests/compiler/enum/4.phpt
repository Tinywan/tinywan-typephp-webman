--TEST--
enum 2
--FILE--
<?php
enum TestEnum
{
    case FOO;
    case BAR;
}

final class TestSpa
{
    public const F = [
        TestEnum::FOO,
        TestEnum::BAR,
    ];
}

function main()
{
    var_dump(TestSpa::F);
}
?>
--EXPECT--
array(2) {
  [0]=>
  enum(TestEnum::FOO)
  [1]=>
  enum(TestEnum::BAR)
}
--TEST--
enum 2
--FILE--
<?php
enum TestEnum: string
{
    case FOO = TestSpa::F;
    case BAR = 'bar';
}

final class TestSpa
{
    public const F = 'foo';
}

function main()
{
    var_dump(TestEnum::cases());
    var_dump(TestEnum::FOO);
    var_dump((string) TestEnum::FOO->value);
}
?>
--EXPECT--
array(2) {
  [0]=>
  enum(TestEnum::FOO)
  [1]=>
  enum(TestEnum::BAR)
}
enum(TestEnum::FOO)
string(3) "foo"
--TEST--
enum 2
--FILE--
<?php
enum TestEnum2: int {
    case Hearts = 2;
    case Diamonds = 1;
    case Clubs = 4;
    case Spades = 3;
}
function main()
{
    var_dump(TestEnum2::cases());
}
?>
--EXPECT--
array(4) {
  [0]=>
  enum(TestEnum2::Hearts)
  [1]=>
  enum(TestEnum2::Diamonds)
  [2]=>
  enum(TestEnum2::Clubs)
  [3]=>
  enum(TestEnum2::Spades)
}

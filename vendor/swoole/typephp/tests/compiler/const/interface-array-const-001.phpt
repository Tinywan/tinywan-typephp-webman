--TEST--
interface array constant inherited by implementing class
--FILE--
<?php
interface InterfaceArrayConstSource
{
    public const DATA = [
        'php',
        'aot',
    ];
}

class InterfaceArrayConstUser implements InterfaceArrayConstSource
{
}

interface InterfaceArrayConstChild extends InterfaceArrayConstSource
{
}

class InterfaceArrayConstChildUser implements InterfaceArrayConstChild
{
}

class InterfaceArrayConstParentUser implements InterfaceArrayConstSource
{
}

class InterfaceArrayConstGrandChildUser extends InterfaceArrayConstParentUser
{
}

function main()
{
    var_dump(InterfaceArrayConstUser::DATA);
    var_dump(InterfaceArrayConstUser::DATA[1]);
    var_dump(InterfaceArrayConstChildUser::DATA);
    var_dump(InterfaceArrayConstGrandChildUser::DATA);
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(3) "php"
  [1]=>
  string(3) "aot"
}
string(3) "aot"
array(2) {
  [0]=>
  string(3) "php"
  [1]=>
  string(3) "aot"
}
array(2) {
  [0]=>
  string(3) "php"
  [1]=>
  string(3) "aot"
}

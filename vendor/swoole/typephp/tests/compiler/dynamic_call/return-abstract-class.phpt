--TEST--
return abstract class
--FILE--
<?php
abstract class Base {
    abstract public function foo();
}

class User extends Base {
    public function foo()
    {
        return 'foo';
    }
}

class Bar {
    public function getUser() : Base {
        return new User();
    }
}

function main()
{
    $bar = new Bar();
    $user = $bar->getUser();
    var_dump($user->foo());
}
?>
--EXPECT--
string(3) "foo"
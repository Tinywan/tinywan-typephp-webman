--TEST--
Trait insteadof works regardless of trait list order
--FILE--
<?php
trait TraitOrderA
{
    public function hello(): void
    {
        echo "A\n";
    }
}

trait TraitOrderB
{
    public function hello(): void
    {
        echo "B\n";
    }
}

class TraitOrderUser
{
    use TraitOrderB, TraitOrderA {
        TraitOrderB::hello insteadof TraitOrderA;
        TraitOrderA::hello as helloA;
    }
}

function main()
{
    $user = new TraitOrderUser();
    $user->hello();
    $user->helloA();
}
?>
--EXPECT--
B
A

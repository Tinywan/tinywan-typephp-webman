--TEST--
Trait alias keeps original method
--FILE--
<?php
trait TraitAliasOriginal
{
    public function hello(): void
    {
        echo "hello\n";
    }
}

class TraitAliasOriginalUser
{
    use TraitAliasOriginal {
        hello as hi;
    }
}

function main()
{
    $user = new TraitAliasOriginalUser();
    $user->hello();
    $user->hi();
}
?>
--EXPECT--
hello
hello

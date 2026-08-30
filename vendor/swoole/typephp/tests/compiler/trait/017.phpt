--TEST--
Trait method can have multiple aliases
--FILE--
<?php
trait TraitMultipleAlias
{
    public function hello(): void
    {
        echo "hello\n";
    }
}

class TraitMultipleAliasUser
{
    use TraitMultipleAlias {
        hello as hi;
        hello as hey;
    }
}

function main()
{
    $user = new TraitMultipleAliasUser();
    $user->hello();
    $user->hi();
    $user->hey();
}
?>
--EXPECT--
hello
hello
hello

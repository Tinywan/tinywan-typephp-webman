--TEST--
Trait parent:: call remains bound to the composing class when inherited
--FILE--
<?php

trait ParentCallTrait
{
    public function source(): string
    {
        return parent::source();
    }
}

class RootClass
{
    public function source(): string
    {
        return 'root';
    }
}

class TraitUser extends RootClass
{
    use ParentCallTrait;
}

class ChildClass extends TraitUser
{
}

class OtherRootClass
{
    public function source(): string
    {
        return 'other';
    }
}

class OtherTraitUser extends OtherRootClass
{
    use ParentCallTrait;
}

function main(): void
{
    var_dump((new ChildClass())->source());
    var_dump((new OtherTraitUser())->source());
}
?>
--EXPECT--
string(4) "root"
string(5) "other"

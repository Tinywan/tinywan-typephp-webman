--TEST--
Trait parent:: call forwards an existing reference parameter
--FILE--
<?php

trait ParentReferenceTrait
{
    public function update(string &$value): void
    {
        parent::update($value);
    }
}

class ReferenceParent
{
    public function update(string &$value): void
    {
        $value = 'updated';
    }
}

class ReferenceChild extends ReferenceParent
{
    use ParentReferenceTrait;
}

function main(): void
{
    $value = 'initial';
    $child = new ReferenceChild();
    $child->update($value);
    var_dump($value);
}
?>
--EXPECT--
string(7) "updated"

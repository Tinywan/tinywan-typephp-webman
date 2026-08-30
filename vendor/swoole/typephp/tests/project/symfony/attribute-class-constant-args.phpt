--TEST--
Symfony pattern: attribute named arguments with class constant values
--FILE--
<?php

#[Attribute(Attribute::TARGET_CLASS)]
class SymfonyLikeAlias
{
    public function __construct(
        public string $id,
        public ?string $when = null,
    ) {
    }
}

interface SymfonyLikeContract
{
}

#[SymfonyLikeAlias(id: SymfonyLikeContract::class, when: 'dev')]
class SymfonyLikeImplementation implements SymfonyLikeContract
{
}

function main(): void
{
    $attribute = (new ReflectionClass(SymfonyLikeImplementation::class))->getAttributes(SymfonyLikeAlias::class)[0]->newInstance();

    var_dump($attribute->id);
    var_dump($attribute->when);
}
?>
--EXPECT--
string(19) "SymfonyLikeContract"
string(3) "dev"

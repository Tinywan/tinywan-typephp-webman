--TEST--
Symfony pattern: class attribute with named arguments and constructor normalization
--FILE--
<?php

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class SymfonyLikeCommand
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        array $aliases = [],
        bool $hidden = false,
        public ?string $help = null,
        public array $usages = [],
    ) {
        if (!$hidden && !$aliases) {
            return;
        }

        $names = explode('|', $name);
        $names = array_merge($names, $aliases);

        if ($hidden && '' !== $names[0]) {
            array_unshift($names, '');
        }

        $this->name = implode('|', $names);
    }
}

#[SymfonyLikeCommand(name: 'cache:clear', description: 'Clear cache', hidden: true, help: 'Clears generated cache')]
class SymfonyLikeClearCacheCommand
{
}

function main(): void
{
    $attribute = (new ReflectionClass(SymfonyLikeClearCacheCommand::class))->getAttributes(SymfonyLikeCommand::class)[0];
    $command = $attribute->newInstance();

    var_dump($command->name);
    var_dump($command->description);
    var_dump($command->usages);
}
?>
--EXPECT--
string(12) "|cache:clear"
string(11) "Clear cache"
array(0) {
}

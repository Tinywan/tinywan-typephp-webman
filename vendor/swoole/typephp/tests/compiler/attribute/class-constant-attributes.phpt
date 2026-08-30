--TEST--
Class-like constants preserve attributes and Reflection metadata
--FILE--
<?php

#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
class ConstantTag
{
    public function __construct(
        public string $name,
        public array $metadata = [],
    ) {
    }
}

class ClassConstantOwner
{
    #[ConstantTag('primary', ['kind' => 'class'])]
    #[ConstantTag(name: 'secondary')]
    public const VALUE = 42;
}

interface InterfaceConstantOwner
{
    #[ConstantTag('interface')]
    public const VALUE = 'interface';
}

trait TraitConstantOwner
{
    #[ConstantTag('trait')]
    public const VALUE = 'trait';
}

class TraitConstantConsumer
{
    use TraitConstantOwner;
}

enum EnumConstantOwner
{
    case Item;

    #[ConstantTag('enum')]
    public const VALUE = 'enum';
}

function dumpConstantAttributes(string $class, string $constant): void
{
    $reflection = new ReflectionClassConstant($class, $constant);
    echo $class, '::', $constant, '=', $reflection->getValue(), "\n";

    $attributes = $reflection->getAttributes(ConstantTag::class);
    var_dump(count($attributes));
    foreach ($attributes as $attribute) {
        $instance = $attribute->newInstance();
        echo $instance->name, ':', $instance->metadata['kind'] ?? 'none', "\n";
    }
}

function main(): void
{
    dumpConstantAttributes(ClassConstantOwner::class, 'VALUE');
    dumpConstantAttributes(InterfaceConstantOwner::class, 'VALUE');
    dumpConstantAttributes(TraitConstantConsumer::class, 'VALUE');
    dumpConstantAttributes(EnumConstantOwner::class, 'VALUE');
}
?>
--EXPECT--
ClassConstantOwner::VALUE=42
int(2)
primary:class
secondary:none
InterfaceConstantOwner::VALUE=interface
int(1)
interface:none
TraitConstantConsumer::VALUE=trait
int(1)
trait:none
EnumConstantOwner::VALUE=enum
int(1)
enum:none

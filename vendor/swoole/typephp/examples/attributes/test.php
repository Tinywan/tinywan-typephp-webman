<?php

#[Attribute(Attribute::TARGET_PROPERTY)]
class PropertyAttributes
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $label = null,
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class IntegerPropertyAttributes extends PropertyAttributes
{
    public function __construct(
        ?string $name = null,
        ?string $label = null,
        public readonly ?int $default = null,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?int $step = null,
        public readonly ?object $object = null,
    ) {
        parent::__construct($name, $label);
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class FloatPropertyAttributes extends PropertyAttributes
{
    public function __construct(
        ?string $name = null,
        ?string $label = null,
        public readonly ?float $default = null,
        public readonly ?float $min = null,
        public readonly ?float $max = null,
    ) {
        parent::__construct($name, $label);
    }
}

class MyClass
{
    #[IntegerPropertyAttributes('prop', 'property: ', 5, 0, 10, 1, new stdClass())]
    public int $prop;
}

$refl = new ReflectionProperty('MyClass', 'prop');
$attributes = $refl->getAttributes();

foreach ($attributes as $attribute) {
    var_dump($attribute->getName());
    var_dump($attribute->getArguments());
    var_dump($attribute->newInstance());
}
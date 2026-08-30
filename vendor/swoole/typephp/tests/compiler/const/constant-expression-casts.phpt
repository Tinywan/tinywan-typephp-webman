--TEST--
PHP 8.5 casts in constant expressions across declaration contexts
--FILE--
<?php

#[Attribute(Attribute::TARGET_CLASS)]
class ConstantCastMetadata
{
    public function __construct(
        public int $integer,
        public bool $boolean,
        public float $float,
        public string $string,
        public array $array,
        public object $object,
    ) {
    }
}

const CAST_INT = (int) 12.75;
const CAST_BOOL = (bool) 0.5;
const CAST_FLOAT = (float) 7;
const CAST_STRING = (string) 123;
const CAST_ARRAY = (array) 'global';
const CAST_OBJECT = (object) ['value' => 'global'];
const CAST_SOURCE = 15.75;
const CAST_FROM_CONSTANT = (int) CAST_SOURCE;

class ConstantCastDefaults
{
    public const INTEGER = (int) 9.75;
    public const BOOLEAN = (bool) 0;
    public const FLOAT = (float) 8;
    public const STRING = (string) 456;
    public const ARRAY = (array) 'class';
    public const SOURCE = 14.75;
    public const FROM_CONSTANT = (int) self::SOURCE;

    public int $integer = (int) 6.75;
    public bool $boolean = (bool) 1;
    public float $float = (float) 5;
    public string $string = (string) 789;
    public array $array = (array) 'property';
}

#[ConstantCastMetadata(
    (int) 4.75,
    (bool) 0.25,
    (float) 3,
    (string) 321,
    (array) 'attribute',
    (object) ['value' => 'attribute'],
)]
class ConstantCastTarget
{
}

function constantCastDefaults(
    int $integer = (int) 2.75,
    bool $boolean = (bool) 0,
    float $float = (float) 1,
    string $string = (string) 654,
    array $array = (array) 'parameter',
    object $object = (object) ['value' => 'parameter'],
): void {
    var_dump($integer, $boolean, $float, $string, $array, $object->value);
}

function main(): void
{
    var_dump(
        CAST_INT,
        CAST_BOOL,
        CAST_FLOAT,
        CAST_STRING,
        CAST_ARRAY,
        CAST_OBJECT->value,
        CAST_FROM_CONSTANT,
    );
    var_dump(
        ConstantCastDefaults::INTEGER,
        ConstantCastDefaults::BOOLEAN,
        ConstantCastDefaults::FLOAT,
        ConstantCastDefaults::STRING,
        ConstantCastDefaults::ARRAY,
        ConstantCastDefaults::FROM_CONSTANT,
    );

    $defaults = new ConstantCastDefaults();
    var_dump(
        $defaults->integer,
        $defaults->boolean,
        $defaults->float,
        $defaults->string,
        $defaults->array,
    );

    constantCastDefaults();

    $attribute = (new ReflectionClass(ConstantCastTarget::class))
        ->getAttributes(ConstantCastMetadata::class)[0]
        ->newInstance();
    var_dump(
        $attribute->integer,
        $attribute->boolean,
        $attribute->float,
        $attribute->string,
        $attribute->array,
        $attribute->object->value,
    );
}
?>
--EXPECT--
int(12)
bool(true)
float(7)
string(3) "123"
array(1) {
  [0]=>
  string(6) "global"
}
string(6) "global"
int(15)
int(9)
bool(false)
float(8)
string(3) "456"
array(1) {
  [0]=>
  string(5) "class"
}
int(14)
int(6)
bool(true)
float(5)
string(3) "789"
array(1) {
  [0]=>
  string(8) "property"
}
int(2)
bool(false)
float(1)
string(3) "654"
array(1) {
  [0]=>
  string(9) "parameter"
}
string(9) "parameter"
int(4)
bool(true)
float(3)
string(3) "321"
array(1) {
  [0]=>
  string(9) "attribute"
}
string(9) "attribute"

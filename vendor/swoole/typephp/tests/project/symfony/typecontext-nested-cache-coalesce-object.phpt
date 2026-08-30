--TEST--
Symfony TypeInfo pattern: nested array cache with coalesce assign returns object
--FILE--
<?php

final class TypeContext
{
    public function __construct(
        public string $calledClass,
        public string $declaringClass,
    ) {
    }
}

final class TypeContextFactory
{
    private array $typeContextCache = [];

    public function createFromClassName(string $calledClassName, ?string $declaringClassName = null): TypeContext
    {
        $declaringClassName ??= $calledClassName;

        return $this->typeContextCache[$declaringClassName][$calledClassName] ??= new TypeContext($calledClassName, $declaringClassName);
    }
}

function main(): void
{
    $factory = new TypeContextFactory();
    $first = $factory->createFromClassName('Child', 'Parent');
    $second = $factory->createFromClassName('Child', 'Parent');
    $third = $factory->createFromClassName('Other');

    var_dump($first === $second);
    var_dump($first->calledClass, $first->declaringClass);
    var_dump($third->calledClass, $third->declaringClass);
}
?>
--EXPECT--
bool(true)
string(5) "Child"
string(6) "Parent"
string(5) "Other"
string(5) "Other"

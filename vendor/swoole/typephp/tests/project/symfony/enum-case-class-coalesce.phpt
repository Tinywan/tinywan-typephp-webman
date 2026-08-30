--TEST--
Symfony pattern: enum case runtime class cached with ??=
--FILE--
<?php

enum SymfonyLikeRequirement
{
    case Slug;
    case Uuid;
}

function enumCaseClasses(UnitEnum ...$cases): array
{
    $class = null;
    $result = [];

    foreach ($cases as $case) {
        $class ??= $case::class;
        $result[] = [$class, $case::class, $case->name];
    }

    return $result;
}

function main(): void
{
    var_dump(enumCaseClasses(SymfonyLikeRequirement::Slug, SymfonyLikeRequirement::Uuid));
}
?>
--EXPECT--
array(2) {
  [0]=>
  array(3) {
    [0]=>
    string(22) "SymfonyLikeRequirement"
    [1]=>
    string(22) "SymfonyLikeRequirement"
    [2]=>
    string(4) "Slug"
  }
  [1]=>
  array(3) {
    [0]=>
    string(22) "SymfonyLikeRequirement"
    [1]=>
    string(22) "SymfonyLikeRequirement"
    [2]=>
    string(4) "Uuid"
  }
}

--TEST--
class_uses reads TypePHP trait metadata and falls back to dynamic PHP classes
--FILE--
<?php

namespace StaticMetadata {

trait LeafTrait
{
}

trait OuterTrait
{
    use LeafTrait;
}

class CompiledClass
{
    use OuterTrait;
}

/** @return array<string, string> */
function classUsesRecursive(string $class): array
{
    $result = [];
    foreach (class_uses($class) as $trait) {
        $result[$trait] = $trait;
        foreach (classUsesRecursive($trait) as $nested) {
            $result[$nested] = $nested;
        }
    }
    return $result;
}

function namedTarget(): string
{
    echo "target\n";
    return CompiledClass::class;
}

function namedAutoload(): bool
{
    echo "autoload\n";
    return false;
}
}

namespace {
function main(): void
{
    var_dump(array_keys(class_uses(StaticMetadata\CompiledClass::class)));
    var_dump(array_keys(class_uses('\\StaticMetadata\\CompiledClass')));
    var_dump(array_keys(class_uses(new StaticMetadata\CompiledClass())));
    var_dump(array_keys(class_uses(StaticMetadata\OuterTrait::class)));
    var_dump(array_keys(class_uses('\\staticmetadata\\outertrait')));
    var_dump(array_keys(class_uses(StaticMetadata\LeafTrait::class)));
    var_dump(array_keys(StaticMetadata\classUsesRecursive(StaticMetadata\CompiledClass::class)));

    class_alias(StaticMetadata\CompiledClass::class, 'StaticMetadata\\CompiledAlias');
    var_dump(array_keys(class_uses('StaticMetadata\\CompiledAlias')));

    var_dump(array_keys(class_uses(
        autoload: StaticMetadata\namedAutoload(),
        object_or_class: StaticMetadata\namedTarget(),
    )));

    eval(<<<'PHP'
namespace DynamicMetadata;
trait RuntimeTrait {}
class RuntimeClass { use RuntimeTrait; }
function inspect(string $class): array { return class_uses($class); }
PHP);
    var_dump(array_keys(class_uses('DynamicMetadata\\RuntimeClass')));
    var_dump(array_keys(DynamicMetadata\inspect(StaticMetadata\CompiledClass::class)));
    var_dump(array_keys(DynamicMetadata\inspect(StaticMetadata\OuterTrait::class)));
}
}
?>
--EXPECT--
array(1) {
  [0]=>
  string(25) "StaticMetadata\OuterTrait"
}
array(1) {
  [0]=>
  string(25) "StaticMetadata\OuterTrait"
}
array(1) {
  [0]=>
  string(25) "StaticMetadata\OuterTrait"
}
array(1) {
  [0]=>
  string(24) "StaticMetadata\LeafTrait"
}
array(1) {
  [0]=>
  string(24) "StaticMetadata\LeafTrait"
}
array(0) {
}
array(2) {
  [0]=>
  string(25) "StaticMetadata\OuterTrait"
  [1]=>
  string(24) "StaticMetadata\LeafTrait"
}
array(1) {
  [0]=>
  string(25) "StaticMetadata\OuterTrait"
}
autoload
target
array(1) {
  [0]=>
  string(25) "StaticMetadata\OuterTrait"
}
array(1) {
  [0]=>
  string(28) "DynamicMetadata\RuntimeTrait"
}
array(1) {
  [0]=>
  string(25) "StaticMetadata\OuterTrait"
}
array(1) {
  [0]=>
  string(24) "StaticMetadata\LeafTrait"
}

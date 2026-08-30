--TEST--
Symfony Serializer pattern: group contexts merged with unpack
--FILE--
<?php
class SymfonyAttributeContextMetadata
{
    private array $normalizationContexts = [];

    public function setNormalizationContextForGroups(array $context, array $groups = []): void
    {
        if (!$groups) {
            $this->normalizationContexts['*'] = $context;
        }

        foreach ($groups as $group) {
            $this->normalizationContexts[$group] = $context;
        }
    }

    public function getNormalizationContextForGroups(array $groups): array
    {
        $contexts = [];
        foreach ($groups as $group) {
            $contexts[] = $this->normalizationContexts[$group] ?? [];
        }

        return array_merge($this->normalizationContexts['*'] ?? [], ...$contexts);
    }
}

function main(): void
{
    $metadata = new SymfonyAttributeContextMetadata();
    $metadata->setNormalizationContextForGroups(['skip_null_values' => true, 'groups' => ['default']]);
    $metadata->setNormalizationContextForGroups(['groups' => ['admin'], 'max_depth' => 2], ['admin']);
    $metadata->setNormalizationContextForGroups(['ignored_attributes' => ['secret']], ['public']);

    var_dump($metadata->getNormalizationContextForGroups(['missing', 'admin', 'public']));
}
?>
--EXPECT--
array(4) {
  ["skip_null_values"]=>
  bool(true)
  ["groups"]=>
  array(1) {
    [0]=>
    string(5) "admin"
  }
  ["max_depth"]=>
  int(2)
  ["ignored_attributes"]=>
  array(1) {
    [0]=>
    string(6) "secret"
  }
}

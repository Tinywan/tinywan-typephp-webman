--TEST--
Symfony pattern: first-class builtin array_map and variadic context merge
--FILE--
<?php

final class AttributeMetadata
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

function countWords(array $words): int
{
    return count(array_filter(array_map(trim(...), $words), static fn ($word) => '' !== $word));
}

function main(): void
{
    var_dump(countWords([' one ', '', " \t ", 'two', ' three ']));

    $metadata = new AttributeMetadata();
    $metadata->setNormalizationContextForGroups(['skip_null' => true]);
    $metadata->setNormalizationContextForGroups(['groups' => ['public']], ['read']);
    $metadata->setNormalizationContextForGroups(['max_depth' => 2], ['detail']);

    var_dump($metadata->getNormalizationContextForGroups(['read', 'missing', 'detail']));
}
?>
--EXPECT--
int(3)
array(3) {
  ["skip_null"]=>
  bool(true)
  ["groups"]=>
  array(1) {
    [0]=>
    string(6) "public"
  }
  ["max_depth"]=>
  int(2)
}

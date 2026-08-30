--TEST--
Symfony Console pattern: nullsafe attribute values with coalesce assignment
--FILE--
<?php

class CommandAttribute
{
    public function __construct(
        public ?string $description = null,
        public ?string $help = null,
        public ?array $usages = null,
    ) {
    }
}

function normalizeCommandMetadata(?CommandAttribute $attribute, array $tag): array
{
    $description = null;
    $help = null;
    $usages = null;

    $description ??= $tag['description'] ?? null;
    $help ??= $tag['help'] ?? null;
    $usages ??= $tag['usages'] ?? null;

    if ($help ??= $attribute?->help) {
        $help = trim($help);
    }
    if ($usages ??= $attribute?->usages) {
        $usages = array_values($usages);
    }
    if ($description ??= $attribute?->description) {
        $description = strtoupper($description);
    }

    return [$description, $help, $usages];
}

function main(): void
{
    var_dump(normalizeCommandMetadata(new CommandAttribute('from attribute', '  help  ', ['a', 'b']), []));
    var_dump(normalizeCommandMetadata(new CommandAttribute('ignored', 'ignored', ['x']), ['description' => 'from tag', 'help' => 'tag help']));
    var_dump(normalizeCommandMetadata(null, []));
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(14) "FROM ATTRIBUTE"
  [1]=>
  string(4) "help"
  [2]=>
  array(2) {
    [0]=>
    string(1) "a"
    [1]=>
    string(1) "b"
  }
}
array(3) {
  [0]=>
  string(8) "FROM TAG"
  [1]=>
  string(8) "tag help"
  [2]=>
  array(1) {
    [0]=>
    string(1) "x"
  }
}
array(3) {
  [0]=>
  NULL
  [1]=>
  NULL
  [2]=>
  NULL
}

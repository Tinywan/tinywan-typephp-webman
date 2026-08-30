--TEST--
target name normalization with path separators and identifier validation
--FILE--
<?php

function normalize_target_name(string $name): array
{
    $outputDir = '';
    if (str_contains($name, '/') || str_contains($name, '\\')) {
        $outputDir = dirname($name);
        $name = basename($name);
    }

    $name = str_replace(['-', '*'], '_', $name);
    $valid = preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;

    return [$outputDir, $name, $valid];
}

function main(): void
{
    var_dump(normalize_target_name('build/my-app'));
    var_dump(normalize_target_name('C:\\tmp\\my*app'));
    var_dump(normalize_target_name('bad.name'));
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(5) "build"
  [1]=>
  string(6) "my_app"
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  string(1) "."
  [1]=>
  string(13) "C:\tmp\my_app"
  [2]=>
  bool(false)
}
array(3) {
  [0]=>
  string(0) ""
  [1]=>
  string(8) "bad.name"
  [2]=>
  bool(false)
}

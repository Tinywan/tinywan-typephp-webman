--TEST--
short option preprocessing splits compact short flag values
--FILE--
<?php

function preprocess_short_options(array $argv): array
{
    $processed = [$argv[0]];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (preg_match('/^-([a-zA-Z])(.+)$/', $arg, $matches)) {
            $option = $matches[1];
            $value = $matches[2];
            $processed[] = "-{$option}";
            $processed[] = $value;
        } elseif (preg_match('/^-([a-zA-Z]{2,})$/', $arg, $matches)) {
            $options = str_split($matches[1]);
            foreach ($options as $opt) {
                $processed[] = "-{$opt}";
            }
        } else {
            $processed[] = $arg;
        }
    }

    return $processed;
}

function main(): void
{
    var_dump(preprocess_short_options(['compiler.php', '-Iinclude', '-abc', '--debug', 'project.yml']));
}
?>
--EXPECT--
array(7) {
  [0]=>
  string(12) "compiler.php"
  [1]=>
  string(2) "-I"
  [2]=>
  string(7) "include"
  [3]=>
  string(2) "-a"
  [4]=>
  string(2) "bc"
  [5]=>
  string(7) "--debug"
  [6]=>
  string(11) "project.yml"
}

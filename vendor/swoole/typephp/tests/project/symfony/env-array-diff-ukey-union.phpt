--TEST--
Symfony Process pattern: environment union with array_diff_ukey callback
--FILE--
<?php

function mergeEnv(array $env, array $defaults, bool $windows): array
{
    $result = ['PATH' => '/bin'];
    $result += $windows ? array_diff_ukey($env, $result, 'strcasecmp') : $env;
    $result += $windows ? array_diff_ukey($defaults, $result, 'strcasecmp') : $defaults;

    return $result;
}

function main(): void
{
    var_dump(mergeEnv(['Path' => '/usr/bin', 'APP_ENV' => 'dev'], ['HOME' => '/tmp'], true));
    var_dump(mergeEnv(['Path' => '/usr/bin', 'APP_ENV' => 'dev'], ['HOME' => '/tmp'], false));
}
?>
--EXPECT--
array(3) {
  ["PATH"]=>
  string(4) "/bin"
  ["APP_ENV"]=>
  string(3) "dev"
  ["HOME"]=>
  string(4) "/tmp"
}
array(4) {
  ["PATH"]=>
  string(4) "/bin"
  ["Path"]=>
  string(8) "/usr/bin"
  ["APP_ENV"]=>
  string(3) "dev"
  ["HOME"]=>
  string(4) "/tmp"
}

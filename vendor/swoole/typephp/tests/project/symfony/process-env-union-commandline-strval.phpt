--TEST--
Symfony Process style env array union and commandline array_map(strval(...))
--FILE--
<?php
class SymfonyProcessCommandCase
{
    private static array $executables = [];

    public function __construct(
        private array|string $commandline,
        private array $env = [],
    ) {
    }

    public function normalize(array $runtimeEnv, array $defaultEnv): array
    {
        $env = $runtimeEnv;
        if ($this->env) {
            $env += '\\' === DIRECTORY_SEPARATOR ? array_diff_ukey($this->env, $env, 'strcasecmp') : $this->env;
        }

        $env += '\\' === DIRECTORY_SEPARATOR ? array_diff_ukey($defaultEnv, $env, 'strcasecmp') : $defaultEnv;

        if (is_array($commandline = $this->commandline)) {
            $commandline = array_values(array_map(strval(...), $commandline));
        }

        if ('\\' === DIRECTORY_SEPARATOR && isset($commandline[0][0]) && strlen($commandline[0]) === strcspn($commandline[0], ':/\\')) {
            $commandline[0] = (self::$executables[$commandline[0]] ??= $commandline[0]) ?? $commandline[0];
        }

        return [$env, $commandline];
    }
}

function main(): void
{
    $process = new SymfonyProcessCommandCase(['php', '-r', 123], ['APP_ENV' => 'local', 'NEW_VAR' => 'yes']);
    [$env, $commandline] = $process->normalize(['APP_ENV' => 'runtime'], ['PATH' => '/usr/bin', 'APP_ENV' => 'default']);

    var_dump($env);
    var_dump($commandline);
}
?>
--EXPECT--
array(3) {
  ["APP_ENV"]=>
  string(7) "runtime"
  ["NEW_VAR"]=>
  string(3) "yes"
  ["PATH"]=>
  string(8) "/usr/bin"
}
array(3) {
  [0]=>
  string(3) "php"
  [1]=>
  string(2) "-r"
  [2]=>
  string(3) "123"
}

--TEST--
Symfony Runtime style global options and server/env coalesce assignment
--FILE--
<?php
class SymfonyRuntimeOptionsCase
{
    public array $options;

    public function __construct(array $options = [])
    {
        $envKey = $options['env_var_name'] ??= 'APP_ENV';
        $debugKey = $options['debug_var_name'] ??= 'APP_DEBUG';

        if (isset($options['project_dir']) && $projectDirVar = $options['project_dir_var'] ?? 'APP_PROJECT_DIR') {
            $_SERVER[$projectDirVar] = $_ENV[$projectDirVar] = $options['project_dir'];
        }

        $_SERVER[$envKey] ??= $_ENV[$envKey] ?? 'dev';
        $_SERVER[$debugKey] ??= $_ENV[$debugKey] ?? !in_array($_SERVER[$envKey], (array) ($options['prod_envs'] ?? ['prod']), true);

        $options['debug'] ??= '1' === (string) $_SERVER[$debugKey];
        $options['disable_dotenv'] = true;

        $this->options = $options;
    }
}

function main(): void
{
    unset($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG'], $_SERVER['APP_PROJECT_DIR']);
    unset($_ENV['APP_ENV'], $_ENV['APP_DEBUG'], $_ENV['APP_PROJECT_DIR']);

    $runtime = new SymfonyRuntimeOptionsCase(['project_dir' => '/app', 'prod_envs' => ['prod', 'stage']]);

    var_dump($_SERVER['APP_ENV']);
    var_dump($_SERVER['APP_DEBUG']);
    var_dump($_SERVER['APP_PROJECT_DIR']);
    var_dump($_ENV['APP_PROJECT_DIR']);
    var_dump($runtime->options['env_var_name']);
    var_dump($runtime->options['debug_var_name']);
    var_dump($runtime->options['debug']);
}
?>
--EXPECT--
string(3) "dev"
bool(true)
string(4) "/app"
string(4) "/app"
string(7) "APP_ENV"
string(9) "APP_DEBUG"
bool(true)

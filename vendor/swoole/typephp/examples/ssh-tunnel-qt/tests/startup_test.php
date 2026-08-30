<?php

require dirname(__DIR__) . '/app/TunnelRule.php';
require dirname(__DIR__) . '/app/TunnelRepository.php';
require dirname(__DIR__) . '/app/SshOutputParser.php';

$startedRuleIds = [];
$renderedRows = [];

function qt_tunnel_create(string $title): mixed
{
    return new stdClass();
}

function qt_tunnel_is_open(mixed $window): bool
{
    return false;
}

function qt_tunnel_process_events(mixed $window): void {}
function qt_tunnel_poll_event(mixed $window): array { return []; }

function qt_tunnel_set_rules(mixed $window, array $rules): void
{
    global $renderedRows;
    $renderedRows = $rules;
}

function qt_tunnel_start_process(
    mixed $window,
    string $id,
    string $program,
    array $arguments
): bool {
    global $startedRuleIds;
    $startedRuleIds[] = $id;
    return true;
}

function qt_tunnel_stop_process(mixed $window, string $id): void {}
function qt_tunnel_append_log(mixed $window, string $id, string $message): void {}
function qt_tunnel_show_error(mixed $window, string $message): void {}
function qt_tunnel_destroy(mixed $window): void {}

require dirname(__DIR__) . '/app/TunnelApplication.php';

function startup_rule(string $id, string $type): TunnelRule
{
    return new TunnelRule([
        'id' => $id,
        'name' => $id,
        'type' => $type,
        'ssh_host' => 'gateway.example.com',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'identity_file' => '',
        'bind_host' => '127.0.0.1',
        'bind_port' => $type === TunnelRule::TYPE_SOCKS5 ? 1080 : 8080,
        'target_host' => '127.0.0.1',
        'target_port' => 3000,
        // Legacy configurations may still contain this field. It must no
        // longer disable startup.
        'auto_start' => false,
    ]);
}

$file = sys_get_temp_dir() . '/typephp-ssh-startup-' . uniqid('', true) . '.json';
$repository = new TunnelRepository($file);
$repository->create(startup_rule('first', TunnelRule::TYPE_LOCAL));
$repository->create(startup_rule('second', TunnelRule::TYPE_SOCKS5));

$application = new TunnelApplication($repository);
$result = $application->run();

sort($startedRuleIds);
if ($result !== 0 || $startedRuleIds !== ['first', 'second'] || count($renderedRows) !== 2) {
    throw new RuntimeException('all persisted tunnel rules must start during application startup');
}

unlink($file);
unlink($file . '.bak');
echo "ssh-tunnel startup tests passed\n";

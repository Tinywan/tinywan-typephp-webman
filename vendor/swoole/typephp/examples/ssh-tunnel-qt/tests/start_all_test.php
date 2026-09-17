<?php

require dirname(__DIR__) . '/app/TunnelRule.php';
require dirname(__DIR__) . '/app/TunnelRepository.php';
require dirname(__DIR__) . '/app/SshOutputParser.php';

$scriptedEvents = [];
$startedRuleIds = [];
$startAttempts = [];
$renderedRows = [];
$shownErrors = [];

function qt_tunnel_create(string $title): mixed
{
    return new stdClass();
}

function qt_tunnel_is_open(mixed $window): bool
{
    global $scriptedEvents;
    return $scriptedEvents !== [];
}

function qt_tunnel_process_events(mixed $window): void {}

function qt_tunnel_poll_event(mixed $window): array
{
    global $scriptedEvents;
    return array_shift($scriptedEvents) ?? [];
}

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
    global $startedRuleIds, $startAttempts;
    $startAttempts[$id] = ($startAttempts[$id] ?? 0) + 1;
    // The "broken" rule starts once so the application comes up, then fails
    // on every later attempt to exercise start-all error isolation.
    if ($id === 'broken' && $startAttempts[$id] > 1) {
        return false;
    }
    $startedRuleIds[] = $id;
    return true;
}

function qt_tunnel_stop_process(mixed $window, string $id): void {}
function qt_tunnel_append_log(mixed $window, string $id, string $message): void {}

function qt_tunnel_show_error(mixed $window, string $message): void
{
    global $shownErrors;
    $shownErrors[] = $message;
}

function qt_tunnel_destroy(mixed $window): void {}

require dirname(__DIR__) . '/app/TunnelApplication.php';

function start_all_rule(string $id): TunnelRule
{
    return new TunnelRule([
        'id' => $id,
        'name' => $id,
        'type' => TunnelRule::TYPE_LOCAL,
        'ssh_host' => 'gateway.example.com',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'identity_file' => '',
        'local_host' => '127.0.0.1',
        'local_port' => 8080,
        'remote_host' => '127.0.0.1',
        'remote_port' => 3000,
    ]);
}

function start_all_event(string $type, string $id): array
{
    return $type === 'start_all' ? ['type' => $type] : ['type' => $type, 'id' => $id];
}

function statuses_by_id(array $rows): array
{
    $statuses = [];
    foreach ($rows as $row) {
        $statuses[$row['id']] = $row['status'];
    }
    return $statuses;
}

$file = sys_get_temp_dir() . '/typephp-ssh-start-all-' . uniqid('', true) . '.json';
$repository = new TunnelRepository($file);
foreach (['a', 'b', 'c', 'broken'] as $id) {
    $repository->create(start_all_rule($id));
}

$scriptedEvents = [
    start_all_event('process_stopped', 'a'),
    start_all_event('process_stopped', 'b'),
    start_all_event('process_stopped', 'broken'),
    start_all_event('start_all', ''),
];

$application = new TunnelApplication($repository);
$result = $application->run();

if ($result !== 0) {
    throw new RuntimeException('application run must return 0');
}
if ($startedRuleIds !== ['a', 'b', 'c', 'broken', 'a', 'b']) {
    throw new RuntimeException(
        'start all must restart every stopped rule once and skip transitioning ones, got '
        . var_export($startedRuleIds, true)
    );
}
if (count($shownErrors) !== 1 || !str_contains($shownErrors[0], 'broken')) {
    throw new RuntimeException(
        'a failing rule must be reported without aborting the remaining starts, got '
        . var_export($shownErrors, true)
    );
}
if (statuses_by_id($renderedRows) !== [
    'a' => 'starting',
    'b' => 'starting',
    'c' => 'starting',
    'broken' => 'error',
]) {
    throw new RuntimeException('unexpected statuses after start all: ' . var_export($renderedRows, true));
}

// A second start-all with every rule running must be a no-op: the button is
// disabled in the UI, and the controller must tolerate the event anyway.
$startedRuleIds = [];
$startAttempts = [];
$shownErrors = [];
$scriptedEvents = [
    start_all_event('process_started', 'a'),
    start_all_event('process_started', 'b'),
    start_all_event('process_started', 'c'),
    start_all_event('process_started', 'broken'),
    start_all_event('start_all', ''),
];

$application = new TunnelApplication($repository);
$result = $application->run();

if ($result !== 0 || $startedRuleIds !== ['a', 'b', 'c', 'broken'] || $shownErrors !== []) {
    throw new RuntimeException(
        'start all must not touch running rules, got '
        . var_export([$startedRuleIds, $shownErrors], true)
    );
}

unlink($file);
unlink($file . '.bak');
echo "ssh-tunnel start-all tests passed\n";

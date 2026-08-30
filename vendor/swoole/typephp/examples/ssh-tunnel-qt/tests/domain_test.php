<?php

require dirname(__DIR__) . '/app/TunnelRule.php';
require dirname(__DIR__) . '/app/TunnelRepository.php';
require dirname(__DIR__) . '/app/SshOutputParser.php';

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nexpected: " . var_export($expected, true)
            . "\nactual:   " . var_export($actual, true)
        );
    }
}

function make_rule(array $overrides): TunnelRule
{
    return new TunnelRule(array_merge([
        'name' => 'Example',
        'type' => TunnelRule::TYPE_LOCAL,
        'ssh_host' => 'gateway.example.com',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'identity_file' => '/keys/id_ed25519',
        'local_host' => '127.0.0.1',
        'local_port' => 3307,
        'remote_host' => 'db.internal',
        'remote_port' => 3306,
    ], $overrides));
}

$local = make_rule([]);
assert_same(
    [
        '-N', '-T',
        '-o', 'ExitOnForwardFailure=yes',
        '-o', 'ServerAliveInterval=30',
        '-o', 'ServerAliveCountMax=3',
        '-o', 'BatchMode=yes',
        '-p', '22',
        '-i', '/keys/id_ed25519',
        '-L', '127.0.0.1:3307:db.internal:3306',
        'deploy@gateway.example.com',
    ],
    $local->sshArguments(),
    'local forwarding arguments'
);

$remote = make_rule([
    'type' => TunnelRule::TYPE_REMOTE,
    'local_host' => '127.0.0.1',
    'local_port' => 3000,
    'remote_host' => '127.0.0.1',
    'remote_port' => 8080,
]);
assert_same(
    '-R',
    $remote->sshArguments()[14],
    'remote forwarding uses -R'
);
assert_same(
    '127.0.0.1:8080:127.0.0.1:3000',
    $remote->sshArguments()[15],
    'remote forwarding mapping'
);

$socks = make_rule([
    'type' => TunnelRule::TYPE_SOCKS5,
    'local_port' => 1080,
    'remote_host' => '',
    'remote_port' => 0,
    'identity_file' => '',
]);
$socksArguments = $socks->sshArguments();
assert_same('-vv', $socksArguments[12], 'SOCKS5 enables destination diagnostics');
assert_same('-D', $socksArguments[13], 'SOCKS5 uses -D');
assert_same('127.0.0.1:1080', $socksArguments[14], 'SOCKS5 bind address');

$outputParser = new SshOutputParser();
assert_same(
    null,
    $outputParser->parse(
        'socks-rule',
        'debug2: channel 53: dynamic request: socks5 host missing.example port 443 command 1',
        true
    ),
    'SOCKS5 destination diagnostic is retained but hidden'
);
assert_same(
    'SOCKS5 连接失败：missing.example:443（connect failed: Name or service not known）',
    $outputParser->parse(
        'socks-rule',
        'channel 53: open failed: connect failed: Name or service not known',
        true
    ),
    'SOCKS5 failure includes domain and port'
);
assert_same(
    null,
    $outputParser->parse(
        'socks-rule',
        'debug2: channel 40: dynamic request: socks5 host 2001:db8::10 port 8443 command 1',
        true
    ),
    'IPv6 SOCKS5 destination is retained'
);
assert_same(
    'SOCKS5 连接失败：[2001:db8::10]:8443（connect failed: Connection timed out）',
    $outputParser->parse(
        'socks-rule',
        'channel 40: open failed: connect failed: Connection timed out',
        true
    ),
    'SOCKS5 failure formats IPv6 endpoint'
);
assert_same(
    'channel 9: open failed: connect failed: Connection refused',
    $outputParser->parse(
        'socks-rule',
        'channel 9: open failed: connect failed: Connection refused',
        true
    ),
    'unmatched channel failure remains visible'
);
assert_same(
    'ordinary ssh error',
    $outputParser->parse('local-rule', 'ordinary ssh error', false),
    'non-SOCKS output remains unchanged'
);
assert_same(
    'debug2: channel 7: pre_dynamic: have 12',
    $outputParser->parse(
        'debug-socks-rule',
        'debug2: channel 7: pre_dynamic: have 12',
        true,
        true
    ),
    'enabled debug output remains visible'
);

$debugLocal = make_rule(['debug' => true]);
assert_same(
    '-vv',
    $debugLocal->sshArguments()[14],
    'debug checkbox enables OpenSSH DEBUG2 output for non-SOCKS rules'
);
assert_same(true, $debugLocal->toArray()['debug'], 'debug setting is persisted');

$ipv6 = make_rule([
    'local_host' => '::1',
    'remote_host' => '2001:db8::10',
]);
assert_same(
    '[::1]:3307:[2001:db8::10]:3306',
    $ipv6->sshArguments()[15],
    'IPv6 forwarding hosts use OpenSSH brackets'
);
assert_same('[::1]:3307', $ipv6->localAddressLabel(), 'local forwarding local address');
assert_same('[2001:db8::10]:3306', $ipv6->remoteAddressLabel(), 'local forwarding remote address');
assert_same('127.0.0.1:3000', $remote->localAddressLabel(), 'remote forwarding local address');
assert_same('127.0.0.1:8080', $remote->remoteAddressLabel(), 'remote forwarding remote address');
assert_same('127.0.0.1:1080', $socks->localAddressLabel(), 'SOCKS5 local address');
assert_same('gateway.example.com:22', $socks->remoteAddressLabel(), 'SOCKS5 gateway address');

$legacyRemote = new TunnelRule([
    'name' => 'Legacy remote rule',
    'type' => TunnelRule::TYPE_REMOTE,
    'ssh_host' => 'gateway.example.com',
    'ssh_port' => 22,
    'ssh_user' => 'deploy',
    'bind_host' => '0.0.0.0',
    'bind_port' => 9000,
    'target_host' => '127.0.0.1',
    'target_port' => 9001,
]);
assert_same('127.0.0.1:9001', $legacyRemote->localAddressLabel(), 'legacy target migrates to local');
assert_same('0.0.0.0:9000', $legacyRemote->remoteAddressLabel(), 'legacy bind migrates to remote');

$temporary = sys_get_temp_dir() . '/typephp-ssh-tunnel-' . uniqid('', true) . '.json';
$repository = new TunnelRepository($temporary);
$repository->create($local);
assert_same(1, count($repository->all()), 'create');
assert_same(true, is_file($temporary . '.bak'), 'durable backup created');

$updated = make_rule(array_merge($local->toArray(), ['name' => 'Updated']));
$repository->update($updated);
assert_same('Updated', $repository->find($local->id)?->name, 'read/update');

$repository->delete($local->id);
assert_same([], $repository->all(), 'delete');

file_put_contents($temporary, '{corrupt json');
assert_same([], $repository->all(), 'corrupt primary recovered from backup');
assert_same([], json_decode((string) file_get_contents($temporary), true), 'primary restored from backup');

unlink($temporary);
unlink($temporary . '.bak');

$invalidRejected = false;
try {
    make_rule(['local_port' => 70000]);
} catch (InvalidArgumentException) {
    $invalidRejected = true;
}
assert_same(true, $invalidRejected, 'invalid port rejected');

echo "ssh-tunnel domain tests passed\n";

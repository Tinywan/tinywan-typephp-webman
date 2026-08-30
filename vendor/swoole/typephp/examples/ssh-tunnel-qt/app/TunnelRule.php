<?php

/**
 * SSH tunnel rule and command-line generation.
 *
 * This class deliberately contains no Qt-specific code.  The GUI only
 * collects values; validation and SSH semantics belong to TypePHP.
 */
class TunnelRule
{
    public const TYPE_LOCAL = 'local';
    public const TYPE_REMOTE = 'remote';
    public const TYPE_SOCKS5 = 'socks5';

    public string $id;
    public string $name;
    public string $type;
    public string $sshHost;
    public int $sshPort;
    public string $sshUser;
    public string $identityFile;
    public bool $debug;
    public string $localHost;
    public int $localPort;
    public string $remoteHost;
    public int $remotePort;

    public function __construct(array $data)
    {
        $this->id = (string) ($data['id'] ?? '');
        $this->name = trim((string) ($data['name'] ?? ''));
        $this->type = (string) ($data['type'] ?? self::TYPE_LOCAL);
        $this->sshHost = trim((string) ($data['ssh_host'] ?? ''));
        $this->sshPort = (int) ($data['ssh_port'] ?? 22);
        $this->sshUser = trim((string) ($data['ssh_user'] ?? ''));
        $this->identityFile = trim((string) ($data['identity_file'] ?? ''));
        $this->debug = (bool) ($data['debug'] ?? false);

        if (array_key_exists('local_host', $data) || array_key_exists('remote_host', $data)) {
            $this->localHost = trim((string) ($data['local_host'] ?? '127.0.0.1'));
            $this->localPort = (int) ($data['local_port'] ?? 0);
            $this->remoteHost = trim((string) ($data['remote_host'] ?? ''));
            $this->remotePort = (int) ($data['remote_port'] ?? 0);
        } elseif ($this->type === self::TYPE_REMOTE) {
            // Migrate the old -R-oriented bind/target schema to endpoints as
            // users understand them: target was local, bind was remote.
            $this->localHost = trim((string) ($data['target_host'] ?? '127.0.0.1'));
            $this->localPort = (int) ($data['target_port'] ?? 0);
            $this->remoteHost = trim((string) ($data['bind_host'] ?? '127.0.0.1'));
            $this->remotePort = (int) ($data['bind_port'] ?? 0);
        } else {
            // Local forwarding and SOCKS used bind as the local endpoint.
            $this->localHost = trim((string) ($data['bind_host'] ?? '127.0.0.1'));
            $this->localPort = (int) ($data['bind_port'] ?? 0);
            $this->remoteHost = trim((string) ($data['target_host'] ?? ''));
            $this->remotePort = (int) ($data['target_port'] ?? 0);
        }

        if ($this->id === '') {
            $this->id = str_replace('.', '', uniqid('rule_', true));
        }
        $this->validate();
    }

    public function validate(): void
    {
        if ($this->name === '') {
            throw new InvalidArgumentException('规则名称不能为空');
        }
        if (!in_array($this->type, [self::TYPE_LOCAL, self::TYPE_REMOTE, self::TYPE_SOCKS5], true)) {
            throw new InvalidArgumentException('不支持的隧道类型：' . $this->type);
        }
        if ($this->sshHost === '') {
            throw new InvalidArgumentException('SSH 服务器不能为空');
        }
        if ($this->sshUser === '') {
            throw new InvalidArgumentException('SSH 用户不能为空');
        }
        $this->assertPort($this->sshPort, 'SSH 端口');
        $this->assertPort($this->localPort, '本机端口');
        if ($this->localHost === '') {
            throw new InvalidArgumentException('本机地址不能为空');
        }
        if ($this->type !== self::TYPE_SOCKS5) {
            if ($this->remoteHost === '') {
                throw new InvalidArgumentException('远程地址不能为空');
            }
            $this->assertPort($this->remotePort, '远程端口');
        }
    }

    private function assertPort(int $port, string $field): void
    {
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException($field . '必须在 1 到 65535 之间');
        }
    }

    /**
     * Generate arguments for QProcess.  No shell is involved, so values such
     * as file names and hosts are passed as individual arguments.
     */
    public function sshArguments(): array
    {
        $arguments = [
            '-N',
            '-T',
            '-o', 'ExitOnForwardFailure=yes',
            '-o', 'ServerAliveInterval=30',
            '-o', 'ServerAliveCountMax=3',
            '-o', 'BatchMode=yes',
            '-p', (string) $this->sshPort,
        ];

        if ($this->identityFile !== '') {
            $arguments[] = '-i';
            $arguments[] = $this->identityFile;
        }

        if ($this->debug || $this->type === self::TYPE_SOCKS5) {
            // SOCKS needs DEBUG2 internally so a failed channel can be
            // correlated with its requested destination.
            $arguments[] = '-vv';
        }

        if ($this->type === self::TYPE_LOCAL) {
            $arguments[] = '-L';
            $arguments[] = $this->formatForwardHost($this->localHost) . ':' . $this->localPort
                . ':' . $this->formatForwardHost($this->remoteHost) . ':' . $this->remotePort;
        } elseif ($this->type === self::TYPE_REMOTE) {
            $arguments[] = '-R';
            $arguments[] = $this->formatForwardHost($this->remoteHost) . ':' . $this->remotePort
                . ':' . $this->formatForwardHost($this->localHost) . ':' . $this->localPort;
        } else {
            $arguments[] = '-D';
            $arguments[] = $this->formatForwardHost($this->localHost) . ':' . $this->localPort;
        }

        $arguments[] = $this->sshUser . '@' . $this->sshHost;
        return $arguments;
    }

    private function formatForwardHost(string $host): string
    {
        if (str_contains($host, ':')
            && !str_starts_with($host, '[')
            && !str_ends_with($host, ']')) {
            return '[' . $host . ']';
        }
        return $host;
    }

    public function typeLabel(): string
    {
        if ($this->type === self::TYPE_LOCAL) {
            return '服务器端口 → 本地端口';
        }
        if ($this->type === self::TYPE_REMOTE) {
            return '本地端口 → 服务器端口';
        }
        return '服务器 → 本地 SOCKS5 代理';
    }

    public function localAddressLabel(): string
    {
        return $this->displayAddress($this->localHost, $this->localPort);
    }

    public function remoteAddressLabel(): string
    {
        if ($this->type === self::TYPE_SOCKS5) {
            return $this->displayAddress($this->sshHost, $this->sshPort);
        }
        return $this->displayAddress($this->remoteHost, $this->remotePort);
    }

    private function displayAddress(string $host, int $port): string
    {
        return $this->formatForwardHost($host) . ':' . $port;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'ssh_host' => $this->sshHost,
            'ssh_port' => $this->sshPort,
            'ssh_user' => $this->sshUser,
            'identity_file' => $this->identityFile,
            'debug' => $this->debug,
            'local_host' => $this->localHost,
            'local_port' => $this->localPort,
            'remote_host' => $this->remoteHost,
            'remote_port' => $this->remotePort,
        ];
    }
}

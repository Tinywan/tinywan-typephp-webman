<?php

/**
 * Converts OpenSSH's channel-oriented SOCKS diagnostics into messages that
 * identify the destination requested by the local SOCKS client.
 */
class SshOutputParser
{
    /** @var array<string, array<string, string>> */
    private array $socksTargets = [];

    public function parse(
        string $tunnelId,
        string $line,
        bool $isSocks5,
        bool $showDebug = false
    ): ?string
    {
        $line = trim($line);
        if ($line === '' || !$isSocks5) {
            return $line === '' ? null : $line;
        }

        $matches = [];
        if (preg_match(
            '/^(?:debug\d+: )?channel (\d+): dynamic request: socks[45] host (.+) port (\d+) command \d+$/',
            $line,
            $matches
        ) === 1) {
            $channel = (string) $matches[1];
            $host = (string) $matches[2];
            $port = (string) $matches[3];
            $this->socksTargets[$tunnelId][$channel] = $this->formatEndpoint($host, $port);
            return $showDebug ? $line : null;
        }

        if (preg_match(
            '/^(?:debug\d+: )?channel (\d+): open failed: (.+)$/',
            $line,
            $matches
        ) === 1) {
            $channel = (string) $matches[1];
            $reason = (string) $matches[2];
            $target = (string) ($this->socksTargets[$tunnelId][$channel] ?? '');
            unset($this->socksTargets[$tunnelId][$channel]);
            if ($target !== '') {
                return 'SOCKS5 连接失败：' . $target . '（' . $reason . '）';
            }
            return $line;
        }

        if (preg_match('/^debug\d+: channel (\d+): free:/', $line, $matches) === 1) {
            unset($this->socksTargets[$tunnelId][(string) $matches[1]]);
            return $showDebug ? $line : null;
        }

        // -vv is enabled only to obtain the SOCKS destination. Do not expose
        // unrelated OpenSSH handshake/channel diagnostics in the UI.
        if (preg_match('/^debug\d+:/', $line) === 1) {
            return $showDebug ? $line : null;
        }
        return $line;
    }

    public function clear(string $tunnelId): void
    {
        unset($this->socksTargets[$tunnelId]);
    }

    private function formatEndpoint(string $host, string $port): string
    {
        if (str_contains($host, ':')
            && !str_starts_with($host, '[')
            && !str_ends_with($host, ']')) {
            return '[' . $host . ']:' . $port;
        }
        return $host . ':' . $port;
    }
}

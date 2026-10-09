<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Http;

final class Downloader
{
    private readonly ?string $proxy;

    public function __construct(?string $proxy = null)
    {
        $proxy = $proxy === null ? null : trim($proxy);
        if ($proxy === '') {
            throw new \InvalidArgumentException('Proxy URL must not be empty');
        }
        $this->proxy = $proxy;
    }

    public function downloadText(string $url): string
    {
        $curl = $this->findCurl();
        // curl has consistent support for HTTP(S) and SOCKS proxies. Keep the
        // existing stream-first path when no explicit proxy was requested.
        if ($this->proxy !== null && $curl !== null) {
            return $this->capture($this->curlCommand($curl, $url));
        }

        $context = stream_context_create($this->streamContextOptions());
        $contents = @file_get_contents($url, false, $context);
        if (is_string($contents)) {
            return $contents;
        }
        if ($curl === null) {
            throw new \RuntimeException("Unable to download {$url}; enable allow_url_fopen or install curl");
        }
        return $this->capture($this->curlCommand($curl, $url));
    }

    public function downloadFile(string $url, string $target): void
    {
        $curl = $this->findCurl();
        if ($curl !== null) {
            $this->run($this->curlCommand($curl, $url, $target));
            return;
        }
        $contents = $this->downloadText($url);
        if (file_put_contents($target, $contents) === false) {
            throw new \RuntimeException("Unable to write {$target}");
        }
    }

    /** @return array{http: array<string, int|string|bool>} */
    public function streamContextOptions(): array
    {
        $options = [
            'timeout' => 30,
            'user_agent' => 'TypePHP/tpc',
        ];
        if ($this->proxy !== null) {
            [$proxy, $authorization] = $this->streamProxy();
            $options['proxy'] = $proxy;
            $options['request_fulluri'] = true;
            if ($authorization !== null) {
                $options['header'] = 'Proxy-Authorization: Basic ' . $authorization;
            }
        }
        return ['http' => $options];
    }

    /** @return list<string> */
    public function curlCommand(string $curl, string $url, ?string $target = null): array
    {
        $command = [$curl, '--fail', '--location', '--retry', '3'];
        if ($this->proxy !== null) {
            $command[] = '--proxy';
            $command[] = $this->proxy;
        }
        if ($target !== null) {
            $command[] = '--output';
            $command[] = $target;
        }
        $command[] = $url;
        return $command;
    }

    /** @return array{string, string|null} */
    private function streamProxy(): array
    {
        $proxy = $this->proxy;
        if (!str_contains($proxy, '://')) {
            $proxy = 'http://' . $proxy;
        }
        $parts = parse_url($proxy);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'tcp'], true) || $host === '') {
            throw new \RuntimeException(
                'The configured proxy requires curl; the PHP stream fallback supports only HTTP proxies',
            );
        }
        $port = (int) ($parts['port'] ?? 80);
        $connectionHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $authorization = null;
        if (isset($parts['user'])) {
            $authorization = base64_encode(
                rawurldecode((string) $parts['user']) . ':' . rawurldecode((string) ($parts['pass'] ?? '')),
            );
        }
        return ["tcp://{$connectionHost}:{$port}", $authorization];
    }

    private function findCurl(): ?string
    {
        $command = PHP_OS_FAMILY === 'Windows' ? 'where curl 2>NUL' : 'command -v curl 2>/dev/null';
        $output = trim((string) shell_exec($command));
        if ($output === '') {
            return null;
        }
        return strtok($output, "\r\n") ?: null;
    }

    /** @param list<string> $command */
    private function run(array $command): void
    {
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) {
            throw new \RuntimeException('Download command failed');
        }
    }

    /** @param list<string> $command */
    private function capture(array $command): string
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start download command');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            $message = trim($stderr);
            if ($this->proxy !== null) {
                $message = str_replace($this->proxy, '[proxy]', $message);
            }
            throw new \RuntimeException($message);
        }
        return $stdout;
    }
}

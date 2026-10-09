<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

use TypePhp\Http\Downloader;

final class PeclExtensionSource
{
    private readonly \Closure $output;
    private readonly Downloader $downloader;

    /** @param callable(string):void $output */
    public function __construct(
        private readonly string $cacheDirectory,
        callable $output,
        ?string $proxy = null,
    ) {
        $this->output = \Closure::fromCallable($output);
        $this->downloader = new Downloader($proxy);
    }

    /** @return array{directory:string,version:string} */
    public function prepare(string $extension): array
    {
        $extension = SapiExtensionRequirements::normalize($extension);
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $extension) !== 1) {
            throw new \InvalidArgumentException("Invalid PECL extension name `{$extension}`");
        }

        $version = trim($this->downloader->downloadText(
            'https://pecl.php.net/rest/r/' . rawurlencode($extension) . '/stable.txt',
        ));
        if (preg_match('/^[0-9][A-Za-z0-9._-]*$/D', $version) !== 1) {
            throw new \RuntimeException("PECL extension `{$extension}` has no stable source release");
        }

        $root = $this->cacheDirectory . '/pecl/' . $extension . '-' . $version;
        if ($this->isComplete($root)) {
            return ['directory' => $root, 'version' => $version];
        }
        $this->mkdir($this->cacheDirectory . '/pecl');
        $lock = fopen($this->cacheDirectory . '/pecl/download.lock', 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Unable to lock the PECL source cache');
        }
        try {
            if ($this->isComplete($root)) {
                return ['directory' => $root, 'version' => $version];
            }
            if (is_dir($root)) {
                throw new \RuntimeException("Incomplete PECL source cache must be removed: {$root}");
            }
            $archives = $this->cacheDirectory . '/archives';
            $this->mkdir($archives);
            $archive = $archives . '/' . $extension . '-' . $version . '.tgz';
            if (!is_file($archive)) {
                ($this->output)("Downloading PECL extension {$extension}-{$version}");
                $temporary = $archive . '.part-' . bin2hex(random_bytes(6));
                try {
                    $this->downloader->downloadFile(
                        'https://pecl.php.net/get/' . rawurlencode($extension . '-' . $version) . '.tgz',
                        $temporary,
                    );
                    if (!rename($temporary, $archive)) {
                        throw new \RuntimeException("Unable to cache PECL archive: {$archive}");
                    }
                } finally {
                    if (is_file($temporary)) {
                        @unlink($temporary);
                    }
                }
            }
            ($this->output)("Extracting PECL extension {$extension}-{$version}");
            $this->run(['tar', '-xzf', $archive, '-C', $this->cacheDirectory . '/pecl']);
            if (!$this->isComplete($root)) {
                throw new \RuntimeException("PECL archive did not produce extension source: {$root}");
            }
            return ['directory' => $root, 'version' => $version];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function isComplete(string $directory): bool
    {
        return is_dir($directory)
            && (is_file($directory . '/config.m4') || is_file($directory . '/config0.m4'));
    }

    /** @param list<string> $command */
    private function run(array $command): void
    {
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) {
            throw new \RuntimeException('Command failed: ' . implode(' ', $command));
        }
    }

    private function mkdir(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create PECL source cache directory: {$directory}");
        }
    }
}

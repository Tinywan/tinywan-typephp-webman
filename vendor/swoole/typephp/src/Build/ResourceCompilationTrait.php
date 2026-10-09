<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

use TypePhp\Backend\Msvc;
use TypePhp\Generator\ResourceFileGenerator;

trait ResourceCompilationTrait
{
    public function hasResourceFile(): bool
    {
        if (!$this->isWindows()) {
            return false;
        }
        $generator = $this->createResourceGenerator();
        return ($generator !== null && $generator->hasResource()) || $this->embeddedArchiveFile !== null;
    }

    public function getResourceRcFile(): string
    {
        return $this->getBuildDir() . DIRECTORY_SEPARATOR . 'app_resource.rc';
    }

    public function getResourceResFile(): string
    {
        return $this->getBuildDir() . DIRECTORY_SEPARATOR . 'app_resource.res';
    }

    protected function createResourceGenerator(): ?ResourceFileGenerator
    {
        if (empty($this->resourceConfig)) {
            return null;
        }
        $projectDir = $this->resourceConfig['_projectDir'] ?? getcwd();
        return new ResourceFileGenerator($this->resourceConfig, $projectDir);
    }

    protected function compileResourceFile(): void
    {
        if (!$this->isWindows()) {
            return;
        }

        $generator = $this->createResourceGenerator();
        if (($generator === null || !$generator->hasResource()) && $this->embeddedArchiveFile === null) {
            return;
        }

        $rcFile = $this->getResourceRcFile();
        $rcContent = $generator?->generate() ?? '';
        if ($this->embeddedArchiveFile !== null) {
            $archive = $this->embeddedArchiveFile;
            $archivePath = str_replace('\\', '/', $archive);
            $rcContent .= '// archive-sha256: ' . hash_file('sha256', $archive) . PHP_EOL;
            $rcContent .= '24103 RCDATA "' . addcslashes($archivePath, '"') . '"' . PHP_EOL;
        }
        $this->writeFile($rcFile, "\xEF\xBB\xBF" . $rcContent);
        $this->climate->info('Generated resource file: ' . $rcFile);

        $backend = $this->getCompilerBackend();
        if ($backend instanceof Msvc) {
            $resFile = $this->getResourceResFile();
            $inputs = [$rcContent];
            if ($generator !== null) {
                foreach ([$generator->getIconPath(), $generator->getManifestPath()] as $file) {
                    if ($file !== null && is_file($file)) {
                        $inputs[] = hash_file('sha256', $file);
                    }
                }
            }
            $cacheKey = hash('sha256', implode("\n", $inputs));
            $cacheFile = $this->getBuildDir() . '/cache/resource/app_resource.sha256';
            if (!$this->climate->arguments->defined('force') && is_file($resFile)
                && is_file($cacheFile) && trim((string) file_get_contents($cacheFile)) === $cacheKey) {
                $this->climate->darkGray('Resource compiled: ' . $resFile . ' [cache]');
                return;
            }
            $cmd = $backend->compileResourceFile($rcFile, $resFile);
            $this->climate->comment($cmd);

            exec($cmd . ' 2>&1', $output, $ret);
            if (!empty($output)) {
                foreach ($output as $line) {
                    $this->climate->out($line);
                }
            }
            if ($ret !== 0) {
                $this->error('Resource compilation failed: ' . $rcFile);
            }
            if (!file_exists($resFile)) {
                $this->error('Resource file not generated: ' . $resFile);
            }
            if (!is_dir(dirname($cacheFile)) && !mkdir(dirname($cacheFile), 0777, true)
                && !is_dir(dirname($cacheFile))) {
                throw new \RuntimeException('Cannot create resource cache directory: ' . dirname($cacheFile));
            }
            if (file_put_contents($cacheFile, $cacheKey . PHP_EOL) === false) {
                throw new \RuntimeException('Cannot write resource cache metadata: ' . $cacheFile);
            }
            $this->climate->green('Resource compiled: ' . $resFile);
        } else {
            $this->climate->out('Resource files are only supported with MSVC backend on Windows');
        }
    }
}

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
        return $generator !== null && $generator->hasResource();
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
        if ($generator === null || !$generator->hasResource()) {
            return;
        }

        $rcFile = $this->getResourceRcFile();
        $rcContent = $generator->generate();
        $this->writeFile($rcFile, "\xEF\xBB\xBF" . $rcContent);
        $this->climate->info('Generated resource file: ' . $rcFile);

        $backend = $this->getCompilerBackend();
        if ($backend instanceof Msvc) {
            $resFile = $this->getResourceResFile();
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
            $this->climate->green('Resource compiled: ' . $resFile);
        } else {
            $this->climate->warning('Resource files are only supported with MSVC backend on Windows');
        }
    }
}

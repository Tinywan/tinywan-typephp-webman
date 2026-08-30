<?php

namespace TypePhp\Installer;

final class LibPhpxInstaller
{
    public function __construct(private readonly InteractiveConsole $console = new InteractiveConsole())
    {
    }

    public function ensure(string $phpxDir, string $phpDir): ?string
    {
        $phpxDir = rtrim($phpxDir, '/');
        $phpDir = rtrim($phpDir, '/');
        if (PHP_OS_FAMILY !== 'Linux' || $this->hasLibPhpx($phpxDir)) {
            return $phpxDir;
        }
        if (!$this->console->isInteractive()) {
            $this->console->write('libphpx.so is missing. Run tpc.php in an interactive terminal to build it automatically, or set PHPX_HOME.');
            return null;
        }

        $this->validateSourceTree($phpxDir);
        $this->validatePhpInstallation($phpDir);
        $this->console->write("The PHPX installation does not provide libphpx.so: {$phpxDir}");
        if (!$this->console->confirm('Build libphpx.so now?', true)) {
            return null;
        }

        $this->installBuildDependencies($phpDir);
        $buildDir = $phpxDir . '/build';
        $this->mkdir($buildDir);
        $this->run([
            'cmake',
            '-S', $phpxDir,
            '-B', $buildDir,
            '-DCMAKE_BUILD_TYPE=Release',
            '-DBUILD_TESTS=OFF',
            '-Dphp_dir=' . $phpDir,
        ], $phpDir);
        $this->run([
            'cmake',
            '--build', $buildDir,
            '--parallel', (string) min(8, max(1, $this->cpuCount())),
            '--target', 'phpx',
        ], $phpDir);

        if (!$this->hasLibPhpx($phpxDir)) {
            throw new \RuntimeException("Build completed but {$phpxDir}/lib/libphpx.so was not created");
        }
        $this->console->write("libphpx.so built successfully in {$phpxDir}/lib");
        return $phpxDir;
    }

    public function hasLibPhpx(string $phpxDir): bool
    {
        return is_file(rtrim($phpxDir, '/') . '/lib/libphpx.so');
    }

    private function validateSourceTree(string $phpxDir): void
    {
        if (!is_file($phpxDir . '/CMakeLists.txt') || !is_dir($phpxDir . '/src') || !is_dir($phpxDir . '/include')) {
            throw new \RuntimeException("PHPX source tree is incomplete: {$phpxDir}");
        }
    }

    private function validatePhpInstallation(string $phpDir): void
    {
        $php = $phpDir . '/bin/php';
        $phpConfig = $phpDir . '/bin/php-config';
        if (!is_executable($php) || !is_executable($phpConfig)) {
            throw new \RuntimeException("PHP installation must provide bin/php and bin/php-config: {$phpDir}");
        }
    }

    private function installBuildDependencies(string $phpDir): void
    {
        $manager = LinuxPackageManager::detect();
        if ($manager === null) {
            $this->console->write('No supported package manager (apt-get/dnf/yum) was found; continuing with existing build tools.');
            return;
        }
        $packages = $manager->missingPackages($manager->packagesForBuildTools());
        $this->console->write('Detected package manager: ' . $manager->command);
        if ($packages === []) {
            $this->console->write('All detected PHPX build dependencies are already installed.');
            return;
        }
        if (!$this->console->confirm('Install missing PHPX build dependencies (' . implode(', ', $packages) . ')?', true)) {
            return;
        }
        $useSudo = function_exists('posix_geteuid') && posix_geteuid() !== 0;
        $refresh = $manager->refreshCommand($useSudo);
        if ($refresh !== null) {
            $this->run($refresh, $phpDir);
        }
        $this->run($manager->installCommand($packages, $useSudo), $phpDir);
    }

    private function run(array $command, string $phpDir): void
    {
        $this->console->write('$ ' . implode(' ', array_map('escapeshellarg', $command)));
        $environment = getenv();
        $environment['PHP_HOME'] = $phpDir;
        $environment['PATH'] = $phpDir . '/bin:' . ($environment['PATH'] ?? '');
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, null, $environment);
        if (!is_resource($process) || proc_close($process) !== 0) {
            throw new \RuntimeException('Command failed: ' . implode(' ', $command));
        }
    }

    private function mkdir(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory {$directory}");
        }
    }

    private function cpuCount(): int
    {
        $count = (int) trim((string) shell_exec('getconf _NPROCESSORS_ONLN 2>/dev/null'));
        return $count > 0 ? $count : 1;
    }
}

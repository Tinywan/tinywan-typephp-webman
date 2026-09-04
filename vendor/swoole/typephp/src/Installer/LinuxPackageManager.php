<?php

namespace TypePhp\Installer;

final readonly class LinuxPackageManager
{
    private const array COMMANDS = ['apt-get', 'dnf', 'yum'];

    public function __construct(public string $command)
    {
        if (!in_array($command, self::COMMANDS, true)) {
            throw new \InvalidArgumentException("Unsupported package manager: {$command}");
        }
    }

    public static function detect(?callable $commandExists = null): ?self
    {
        $commandExists ??= static fn(string $command): bool => trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null')) !== '';
        foreach (self::COMMANDS as $command) {
            if ($commandExists($command)) {
                return new self($command);
            }
        }
        return null;
    }

    public function packagesForConfigureOptions(array $options): array
    {
        $text = implode(' ', $options);
        $groups = [
            'base' => [
                'apt-get' => ['autoconf', 'bison', 're2c', 'libxml2-dev', 'libsqlite3-dev'],
                'dnf' => ['autoconf', 'bison', 're2c', 'libxml2-devel', 'sqlite-devel'],
                'yum' => ['autoconf', 'bison', 're2c', 'libxml2-devel', 'sqlite-devel'],
            ],
            'curl' => ['needle' => '--with-curl', 'apt-get' => ['libcurl4-openssl-dev'], 'dnf' => ['libcurl-devel'], 'yum' => ['libcurl-devel']],
            'openssl' => ['needle' => '--with-openssl', 'apt-get' => ['libssl-dev'], 'dnf' => ['openssl-devel'], 'yum' => ['openssl-devel']],
            'zlib' => ['needle' => '--with-zlib', 'apt-get' => ['zlib1g-dev'], 'dnf' => ['zlib-devel'], 'yum' => ['zlib-devel']],
            'bz2' => ['needle' => '--with-bz2', 'apt-get' => ['libbz2-dev'], 'dnf' => ['bzip2-devel'], 'yum' => ['bzip2-devel']],
            'mbstring' => ['needle' => '--enable-mbstring', 'apt-get' => ['libonig-dev'], 'dnf' => ['oniguruma-devel'], 'yum' => ['oniguruma-devel']],
            'zip' => ['needle' => '--with-zip', 'apt-get' => ['libzip-dev'], 'dnf' => ['libzip-devel'], 'yum' => ['libzip-devel']],
            'readline' => ['needle' => '--with-readline', 'apt-get' => ['libreadline-dev'], 'dnf' => ['readline-devel'], 'yum' => ['readline-devel']],
            'libedit' => ['needle' => '--with-libedit', 'apt-get' => ['libedit-dev'], 'dnf' => ['libedit-devel'], 'yum' => ['libedit-devel']],
            'icu' => ['needle' => '--enable-intl', 'apt-get' => ['libicu-dev'], 'dnf' => ['libicu-devel'], 'yum' => ['libicu-devel']],
            'xslt' => ['needle' => '--with-xsl', 'apt-get' => ['libxslt1-dev'], 'dnf' => ['libxslt-devel'], 'yum' => ['libxslt-devel']],
            'gmp' => ['needle' => '--with-gmp', 'apt-get' => ['libgmp-dev'], 'dnf' => ['gmp-devel'], 'yum' => ['gmp-devel']],
            'sodium' => ['needle' => '--with-sodium', 'apt-get' => ['libsodium-dev'], 'dnf' => ['libsodium-devel'], 'yum' => ['libsodium-devel']],
            'ffi' => ['needle' => '--with-ffi', 'apt-get' => ['libffi-dev'], 'dnf' => ['libffi-devel'], 'yum' => ['libffi-devel']],
            'pgsql' => ['needle' => '--with-pgsql', 'apt-get' => ['libpq-dev'], 'dnf' => ['libpq-devel'], 'yum' => ['libpq-devel']],
            'pdo_pgsql' => ['needle' => '--with-pdo-pgsql', 'apt-get' => ['libpq-dev'], 'dnf' => ['libpq-devel'], 'yum' => ['libpq-devel']],
            'ldap' => ['needle' => '--with-ldap', 'apt-get' => ['libldap2-dev'], 'dnf' => ['openldap-devel'], 'yum' => ['openldap-devel']],
            'gd' => ['needle' => '--enable-gd', 'apt-get' => ['libpng-dev', 'libjpeg-dev', 'libwebp-dev', 'libfreetype6-dev'], 'dnf' => ['libpng-devel', 'libjpeg-turbo-devel', 'libwebp-devel', 'freetype-devel'], 'yum' => ['libpng-devel', 'libjpeg-turbo-devel', 'libwebp-devel', 'freetype-devel']],
        ];

        $packages = [...$this->packagesForBuildTools(), ...$groups['base'][$this->command]];
        unset($groups['base']);
        foreach ($groups as $group) {
            if (str_contains($text, $group['needle'])) {
                array_push($packages, ...$group[$this->command]);
            }
        }
        return array_values(array_unique($packages));
    }

    public function packagesForBuildTools(): array
    {
        return match ($this->command) {
            'apt-get' => ['build-essential', 'cmake', 'pkg-config'],
            'dnf' => ['gcc', 'gcc-c++', 'make', 'cmake', 'pkgconf-pkg-config'],
            'yum' => ['gcc', 'gcc-c++', 'make', 'cmake', 'pkgconfig'],
            default => throw new \LogicException("Unsupported package manager: {$this->command}"),
        };
    }

    public function installCommand(array $packages, bool $useSudo): array
    {
        $prefix = $useSudo ? ['sudo'] : [];
        $args = match ($this->command) {
            'apt-get' => ['apt-get', 'install', '-y'],
            'dnf' => ['dnf', 'install', '-y'],
            'yum' => ['yum', 'install', '-y'],
            default => throw new \LogicException("Unsupported package manager: {$this->command}"),
        };
        return [...$prefix, ...$args, ...$packages];
    }

    public function refreshCommand(bool $useSudo): ?array
    {
        if ($this->command !== 'apt-get') {
            return null;
        }
        return [...($useSudo ? ['sudo'] : []), 'apt-get', 'update'];
    }

    public function missingPackages(array $packages, ?callable $isInstalled = null): array
    {
        $isInstalled ??= function (string $package): bool {
            $command = $this->command === 'apt-get'
                ? "dpkg-query -W -f='\${Status}' " . escapeshellarg($package) . ' 2>/dev/null'
                : 'rpm -q ' . escapeshellarg($package) . ' 2>/dev/null';
            $output = trim((string) shell_exec($command));
            return $this->command === 'apt-get'
                ? str_contains($output, 'install ok installed')
                : $output !== '';
        };
        return array_values(array_filter($packages, static fn(string $package): bool => !$isInstalled($package)));
    }
}

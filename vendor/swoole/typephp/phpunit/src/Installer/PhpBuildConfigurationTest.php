<?php

namespace TypePhp\Tests\Installer;

use PHPUnit\Framework\TestCase;
use TypePhp\Installer\LinuxPackageManager;
use TypePhp\Installer\LibPhpInstaller;
use TypePhp\Installer\PhpBuildConfiguration;

final class PhpBuildConfigurationTest extends TestCase
{
    public function testDerivePreservesExtensionsAndReplacesInstallationOptions(): void
    {
        $options = PhpBuildConfiguration::derive(
            "'--prefix=/usr' '--with-curl' '--enable-mbstring' '--with-apxs2=/usr/bin/apxs' " .
            "'--with-config-file-path=/etc/php/8.4/cli' '--enable-fpm'",
            '/home/test/.typephp'
        );

        self::assertContains('--prefix=/home/test/.typephp', $options);
        self::assertContains('--enable-embed=shared', $options);
        self::assertContains('--with-curl', $options);
        self::assertContains('--enable-mbstring', $options);
        self::assertNotContains('--with-apxs2=/usr/bin/apxs', $options);
        self::assertNotContains('--enable-fpm', $options);
    }

    public function testDetectPackageManagerUsesSupportedPriority(): void
    {
        $manager = LinuxPackageManager::detect(static fn(string $command): bool => in_array($command, ['dnf', 'yum'], true));
        self::assertSame('dnf', $manager?->command);
    }

    public function testPackagesFollowEnabledConfigureOptions(): void
    {
        $manager = new LinuxPackageManager('apt-get');
        $packages = $manager->packagesForConfigureOptions(['--with-curl', '--enable-mbstring']);

        self::assertContains('build-essential', $packages);
        self::assertContains('cmake', $packages);
        self::assertContains('libcurl4-openssl-dev', $packages);
        self::assertContains('libonig-dev', $packages);
        self::assertNotContains('libzip-dev', $packages);
    }

    public function testBuildToolPackagesIncludeCompilerMakeAndCmake(): void
    {
        self::assertSame(
            ['build-essential', 'cmake', 'pkg-config'],
            (new LinuxPackageManager('apt-get'))->packagesForBuildTools()
        );
        self::assertSame(
            ['gcc', 'gcc-c++', 'make', 'cmake', 'pkgconf-pkg-config'],
            (new LinuxPackageManager('dnf'))->packagesForBuildTools()
        );
    }

    public function testMissingPackagesFiltersInstalledPackages(): void
    {
        $manager = new LinuxPackageManager('apt-get');
        $missing = $manager->missingPackages(
            ['make', 're2c'],
            static fn(string $package): bool => $package === 'make'
        );
        self::assertSame(['re2c'], $missing);
    }

    public function testExtensionNamesWithoutSuffixAlsoResolveSharedObjects(): void
    {
        self::assertSame(
            ['/php/extensions/swoole', '/php/extensions/swoole.so'],
            LibPhpInstaller::extensionFileCandidates('/php/extensions/swoole')
        );
        self::assertSame(
            ['/php/extensions/opcache.so'],
            LibPhpInstaller::extensionFileCandidates('/php/extensions/opcache.so')
        );
    }
}

<?php

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\OfficialPhpSource;

final class OfficialPhpSourceTest extends TestCase
{
    public function testDefaultCacheUsesTypePhpHomeDirectory(): void
    {
        self::assertSame('/home/example/.typephp', OfficialPhpSource::defaultCacheDirectory('/home/example'));
    }

    public function testVersionIsReadFromSourceHeader(): void
    {
        $directory = sys_get_temp_dir() . '/typephp-source-version-' . bin2hex(random_bytes(6));
        mkdir($directory . '/main', 0777, true);
        file_put_contents($directory . '/main/php_version.h', '#define PHP_VERSION "8.4.14"');
        try {
            self::assertSame('8.4.14', OfficialPhpSource::version($directory));
        } finally {
            unlink($directory . '/main/php_version.h');
            rmdir($directory . '/main');
            rmdir($directory);
        }
    }

    public function testCachedMinorReleaseIsUsedWithoutNetworkAccess(): void
    {
        $directory = sys_get_temp_dir() . '/typephp-source-cache-' . bin2hex(random_bytes(6));
        foreach (['8.4.9', '8.4.25'] as $version) {
            $source = $directory . '/src/php-' . $version;
            foreach (['main', 'Zend', 'ext/standard'] as $child) {
                mkdir($source . '/' . $child, 0777, true);
            }
            foreach (['configure', 'main/php.h', 'main/php_version.h', 'Zend/zend.h', 'ext/standard/fsock.h'] as $file) {
                touch($source . '/' . $file);
            }
        }
        try {
            $manager = new OfficialPhpSource($directory, static function (string $message): void {});
            self::assertSame($directory . '/src/php-8.4.25', $manager->prepare('8.4'));
        } finally {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($directory);
        }
    }
}

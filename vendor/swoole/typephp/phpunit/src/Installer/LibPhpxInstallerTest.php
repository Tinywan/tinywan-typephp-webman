<?php

namespace TypePhp\Tests\Installer;

use PHPUnit\Framework\TestCase;
use TypePhp\Installer\LibPhpxInstaller;

final class LibPhpxInstallerTest extends TestCase
{
    public function testDetectsSharedLibraryOnlyInPhpxLibDirectory(): void
    {
        $root = sys_get_temp_dir() . '/typephp-phpx-' . bin2hex(random_bytes(6));
        mkdir($root . '/lib', 0755, true);
        $installer = new LibPhpxInstaller();

        try {
            self::assertFalse($installer->hasLibPhpx($root));
            touch($root . '/lib/libphpx.a');
            self::assertFalse($installer->hasLibPhpx($root));
            touch($root . '/lib/libphpx.so');
            self::assertTrue($installer->hasLibPhpx($root . '/'));
        } finally {
            @unlink($root . '/lib/libphpx.so');
            @unlink($root . '/lib/libphpx.a');
            @rmdir($root . '/lib');
            @rmdir($root);
        }
    }
}

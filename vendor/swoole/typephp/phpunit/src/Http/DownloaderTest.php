<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhpTest\Http;

use PHPUnit\Framework\TestCase;
use TypePhp\Http\Downloader;

/**
 * @internal
 * @coversNothing
 */
final class DownloaderTest extends TestCase
{
    public function testProxyIsAddedToCurlDownloads(): void
    {
        $downloader = new Downloader('socks5h://127.0.0.1:1080');

        self::assertSame(
            [
                '/usr/bin/curl',
                '--fail',
                '--location',
                '--retry',
                '3',
                '--proxy',
                'socks5h://127.0.0.1:1080',
                '--output',
                '/tmp/archive.tar.xz',
                'https://www.php.net/archive.tar.xz',
            ],
            $downloader->curlCommand(
                '/usr/bin/curl',
                'https://www.php.net/archive.tar.xz',
                '/tmp/archive.tar.xz',
            ),
        );
    }

    public function testHttpProxyIsConfiguredForStreamFallback(): void
    {
        $downloader = new Downloader('http://user:p%40ss@proxy.example:8080');

        self::assertSame(
            [
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'TypePHP/tpc',
                    'proxy' => 'tcp://proxy.example:8080',
                    'request_fulluri' => true,
                    'header' => 'Proxy-Authorization: Basic ' . base64_encode('user:p@ss'),
                ],
            ],
            $downloader->streamContextOptions(),
        );
    }

    public function testEmptyProxyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Downloader('  ');
    }
}

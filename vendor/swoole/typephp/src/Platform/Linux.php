<?php

namespace TypePhp\Platform;

/**
 * Linux 平台实现
 */
class Linux extends UnixPlatform
{
    public function getName(): string
    {
        return 'Linux';
    }

    public function isCurrent(): bool
    {
        return PHP_OS === 'Linux';
    }

    public function getSharedLibraryExtension(): string
    {
        return '.so';
    }

    public function getDefaultCompiler(): string
    {
        return 'g++';
    }

    public function getIntegerLiteralSuffix(): string
    {
        return 'L';
    }

    /**
     * 获取共享库链接选项
     */
    public function getSharedLinkFlag(): string
    {
        return '-shared';
    }
}

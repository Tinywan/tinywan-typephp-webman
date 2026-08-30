<?php

namespace TypePhp\Platform;

/**
 * 平台工厂类
 * 自动检测并创建当前平台的实例
 */
class PlatformFactory
{
    /**
     * 创建当前平台的实例
     */
    public static function create(): PlatformBase
    {
        if ((new Windows())->isCurrent()) {
            return new Windows();
        } elseif ((new Linux())->isCurrent()) {
            return new Linux();
        } elseif ((new Macos())->isCurrent()) {
            return new Macos();
        } else {
            throw new \RuntimeException("Unsupported platform: " . PHP_OS);
        }
    }

    /**
     * 获取当前平台名称
     */
    public static function getCurrentPlatformName(): string
    {
        return self::create()->getName();
    }

    /**
     * 判断是否为 Windows 平台
     */
    public static function isWindows(): bool
    {
        return (new Windows())->isCurrent();
    }

    /**
     * 判断是否为 Linux 平台
     */
    public static function isLinux(): bool
    {
        return (new Linux())->isCurrent();
    }

    /**
     * 判断是否为 macOS 平台
     */
    public static function isMacos(): bool
    {
        return (new Macos())->isCurrent();
    }
}

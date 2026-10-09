<?php

namespace TypePhp\Build;

use RuntimeException;

/** Enforces PHP Nano's link-input and forbidden host-capability boundary. */
final class NativeDependencyAuditor
{
    /**
     * Reviewed native exceptions outside ISO C/C++ and POSIX.1-2008.
     * POSIX functions do not need to be repeated in this list.
     */
    public const array NON_POSIX_HOST_FUNCTION_ALLOWLIST = [
        'flock',
    ];

    /** Bionic/libc++ implementation imports required by an Android JNI library. */
    public const array ANDROID_SYSTEM_ABI_ALLOWLIST = [
        'closelog',
        'openlog',
        'syscall',
        'syslog',
    ];

    /** @param list<string> $flags */
    public function assertLinkFlags(string $target, array $flags): void
    {
        $allowedLibraries = $target === 'wasip2'
            ? ['-lsetjmp', '-lunwind']
            : [];
        foreach ($flags as $flag) {
            if (str_starts_with($flag, '-l') && !in_array($flag, $allowedLibraries, true)) {
                throw new RuntimeException(
                    "PHP Nano target must not link external library `{$flag}`"
                );
            }
            if (str_starts_with($flag, '-L')) {
                throw new RuntimeException(
                    "PHP Nano target must not add external library path `{$flag}`"
                );
            }
        }
    }

    public function assertUndefinedSymbols(string $target, string $nmOutput): void
    {
        $forbidden = [];
        foreach (preg_split('/\R/', $nmOutput) ?: [] as $line) {
            if (preg_match('/\bU\s+([^\s]+)\s*$/', trim($line), $match) !== 1) {
                continue;
            }
            $symbol = preg_replace('/@.*$/', '', $match[1]) ?? $match[1];
            $symbol = ltrim($symbol, '_');
            if ($this->isForbiddenSymbol($symbol, $target)) {
                $forbidden[$symbol] = true;
            }
        }
        if ($forbidden !== []) {
            $symbols = array_keys($forbidden);
            sort($symbols);
            throw new RuntimeException(
                'PHP Nano artifact imports forbidden host capabilities: '
                . implode(', ', $symbols)
            );
        }
    }

    public function assertWindowsImports(string $dumpbinOutput): void
    {
        $runtimeDlls = [];
        $forbidden = [];
        foreach (preg_split('/\R/', $dumpbinOutput) ?: [] as $line) {
            $value = trim($line);
            if (preg_match('/^[A-Za-z0-9_.-]+\.dll$/i', $value) === 1
                && preg_match('/^php(?:x|\d.*)?\.dll$/i', $value) === 1) {
                $runtimeDlls[strtolower($value)] = true;
            }
            if (preg_match('/(?:^|\s)([A-Za-z_][A-Za-z0-9_@?$]*)\s*$/', $value, $match) === 1
                && $this->isForbiddenSymbol($match[1], 'windows')) {
                $forbidden[$match[1]] = true;
            }
        }
        if ($runtimeDlls !== []) {
            throw new RuntimeException(
                'PHP Nano Windows artifact imports a dynamic PHP runtime: '
                . implode(', ', array_keys($runtimeDlls))
            );
        }
        if ($forbidden !== []) {
            throw new RuntimeException(
                'PHP Nano Windows artifact imports forbidden host capabilities: '
                . implode(', ', array_keys($forbidden))
            );
        }
    }

    private function isForbiddenSymbol(string $symbol, string $target): bool
    {
        if ($target === 'wasip2') {
            // LLVM nm prints Preview 1 imports using either their plain field
            // name or a generated __imported_wasi_snapshot_preview1_* name.
            $symbol = preg_replace(
                '/^(?:imported_)?wasi_snapshot_preview1_/',
                '',
                $symbol,
            ) ?? $symbol;
            /* C/C++ standard-library implementations may use clocks, entropy,
             * environment, and WASI filesystem imports. Network remains out. */
            if ($symbol === 'clock_time_get' || $symbol === 'poll_oneoff' || $symbol === 'random_get') {
                return false;
            }
            if (str_starts_with($symbol, 'sock_')) {
                return true;
            }
            if ($symbol === 'proc_raise' || $symbol === 'sched_yield') {
                return true;
            }
        } elseif ($target === 'android'
            && in_array($symbol, self::ANDROID_SYSTEM_ABI_ALLOWLIST, true)
        ) {
            return false;
        } elseif (in_array($symbol, self::NON_POSIX_HOST_FUNCTION_ALLOWLIST, true)) {
            return false;
        }

        return preg_match(
            '/^(?:'
            . 'fork|vfork|exec(?:l|le|lp|lpe|v|ve|vp|vpe)?|system|popen|pclose|'
            . 'posix_spawn(?:p)?|wait|waitpid|kill|'
            . 'dlopen|dlsym|dlclose|dlerror|'
            . 'socket|socketpair|connect|bind|listen|accept|accept4|send|sendto|sendmsg|'
            . 'recv|recvfrom|recvmsg|shutdown|getsockopt|setsockopt|getpeername|getsockname|'
            . 'getaddrinfo|freeaddrinfo|getnameinfo|gethostname|gethostbyaddr|gethostbyname|'
            . 'gethostbynamel|inet_addr|inet_ntoa|inet_ntop|inet_pton|res_init|res_query|'
            . 'pipe|pipe2|ioctl|select|pselect|poll|ppoll|'
            . 'setenv|putenv|unsetenv|'
            . 'openlog|closelog|syslog|'
            . 'signal|sigaction|sigprocmask|pthread_sigmask|raise|alarm|setitimer|'
            . 'mmap|munmap|mprotect|madvise|'
            . 'syscall'
            . ')$/',
            $symbol,
        ) === 1;
    }
}

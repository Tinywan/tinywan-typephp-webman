<?php

function tunnel_config_path(): string
{
    $override = getenv('TYPEPHP_SSH_TUNNEL_CONFIG');
    if (is_string($override) && $override !== '') {
        return $override;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $base = getenv('APPDATA');
        if (!is_string($base) || $base === '') {
            $base = '.';
        }
        return $base . DIRECTORY_SEPARATOR . 'TypePHP' . DIRECTORY_SEPARATOR
            . 'ssh-tunnel-manager.json';
    }

    $base = getenv('XDG_CONFIG_HOME');
    if (!is_string($base) || $base === '') {
        $home = getenv('HOME');
        $base = (is_string($home) && $home !== '')
            ? $home . DIRECTORY_SEPARATOR . '.config'
            : '.';
    }
    return $base . DIRECTORY_SEPARATOR . 'typephp' . DIRECTORY_SEPARATOR
        . 'ssh-tunnel-manager.json';
}

function main(): int
{
    try {
        $application = new TunnelApplication(new TunnelRepository(tunnel_config_path()));
        return $application->run();
    } catch (Throwable $error) {
        fwrite(STDERR, 'SSH Tunnel Manager: ' . $error->getMessage() . PHP_EOL);
        return 1;
    }
}

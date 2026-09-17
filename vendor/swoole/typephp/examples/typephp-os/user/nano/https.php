<?php

function main(int $argc, array $argv): void
{
    $url = 'https://httpcan.org/get';
    $handle = curl_init($url);
    if ($handle === false) {
        echo "curl_init failed\n";
        return;
    }

    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
    curl_setopt($handle, CURLOPT_NOSIGNAL, true);
    curl_setopt($handle, CURLOPT_CAINFO, '/CACERT.PEM');
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($handle, CURLOPT_TIMEOUT, 20);
    curl_setopt($handle, CURLOPT_USERAGENT, 'TypePHP-OS/0.1');

    $body = curl_exec($handle);
    if ($body === false) {
        echo 'HTTPS request failed: ', curl_error($handle), "\n";
        return;
    }

    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $version = curl_getinfo($handle, CURLINFO_HTTP_VERSION);
    $curlVersion = curl_version();
    $digest = openssl_digest('TypePHP-OS', 'sha256');
    echo 'GET ', $url, "\n";
    echo 'HTTP status: ', (string) $status, "\n";
    echo 'HTTP version: ', $version === CURL_HTTP_VERSION_2_0 ? '2' : (string) $version, "\n";
    echo 'TLS backend: ', (string) $curlVersion['ssl_version'], "\n";
    echo 'OpenSSL SHA-256: ', (string) $digest, "\n";
    echo $body, "\n";
}

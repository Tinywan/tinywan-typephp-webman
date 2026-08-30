--TEST--
Test invalid bindto
--FILE--
<?php
$ctx = stream_context_create([
    'socket' => [
        'bindto' => 'invalid',
    ],
]);
$fp = stream_socket_client(
    'tcp://www.' . str_repeat('x', 100) . '.com:80',
    $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx
);
?>
--EXPECTF--
Warning: %s: php_network_getaddresses: getaddrinfo for www.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.com failed: %s in %s

Warning: %s: Unable to connect to tcp://www.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.com:80 (php_network_getaddresses: getaddrinfo for %s failed: %s) in %s

<?php
function main()
{
    $http = new Swoole\Http\Server('127.0.0.1', 9501);


    $http->on('request', function ($request, $response) {
        try {
            $response->header('Content-Type', 'text/plain');
            $response->end('Hello World');
        } catch (Exception $e) {
            $response->status(500);
            $response->end('error: ' . $e->getMessage());
        }
    });

    $http->start();
}
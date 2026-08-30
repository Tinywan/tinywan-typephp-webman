<?php

use Swoole\Server;

function main()
{
    $serv = new Server("0.0.0.0", 9501);
    $serv->on('receive', function (Server $serv, $fd, $reactor_id, $data) {
        echo "[#" . $serv->worker_id . "]\tClient[$fd] receive data: $data\n";
        $serv->send($fd, "hello {$data}\n");
    });
    $serv->start();
}

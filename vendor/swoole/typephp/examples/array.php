<?php
function safe_write($fd, $data)
{
    $len = strlen($data);
    do {
        $w = fwrite($fd, $data);
        $len -= $w;
    } while ($len && ($data = substr($data, $w)) !== FALSE);
}

function sync($tmp)
{
    global $pipe;
    $data = "hello world";
    safe_write($pipe, $data);
}

function main()
{
    global $pipe;
    $pipes = stream_socket_pair(AF_UNIX, SOCK_STREAM, 0);
    $pipe = $pipes[0];
}
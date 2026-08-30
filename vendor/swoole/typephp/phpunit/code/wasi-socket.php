<?php

function main(): void
{
    stream_socket_server('tcp://127.0.0.1:0');
}

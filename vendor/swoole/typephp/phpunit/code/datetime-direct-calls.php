<?php

function datetimeDirectCalls(int $timestamp): string
{
    $now = time();
    return date('Y-m-d', $timestamp)
        . gmdate('Y-m-d H:i:s', $timestamp)
        . date('U', $timestamp)
        . $now;
}

<?php

function main(): void
{
    pcntl_signal(SIGTERM, static function (): void {});
}

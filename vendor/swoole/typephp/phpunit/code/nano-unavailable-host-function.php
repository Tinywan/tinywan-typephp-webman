<?php

function main(): void
{
    pcntl_setns(1, 0);
}

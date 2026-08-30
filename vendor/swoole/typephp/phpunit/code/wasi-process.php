<?php

function main(): void
{
    proc_open('true', [], $pipes);
}

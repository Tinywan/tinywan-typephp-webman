<?php

function compilationStatisticsExample(): string
{
    $value = json_encode(['time' => time()]);
    return $value === false ? '' : $value;
}

<?php

function taskboard_data_path(): string
{
    $override = getenv('TYPEPHP_TASKBOARD_DATA');
    if (is_string($override) && $override !== '') {
        return $override;
    }
    $base = getenv('APPDATA');
    if (!is_string($base) || $base === '') {
        $base = getenv('HOME');
    }
    if (!is_string($base) || $base === '') {
        $base = '.';
    }
    return $base . DIRECTORY_SEPARATOR . 'TypePHP' . DIRECTORY_SEPARATOR . 'taskboard.sqlite';
}

function main(): int
{
    try {
        return (new TaskBoardApplication(new TaskStore(taskboard_data_path())))->run();
    } catch (Throwable $error) {
        fwrite(STDERR, 'TypePHP Taskboard: ' . $error->getMessage() . PHP_EOL);
        return 1;
    }
}

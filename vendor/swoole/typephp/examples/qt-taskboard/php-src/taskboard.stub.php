<?php

/** Qt renders the view and returns user input; all task decisions live in PHP. */
function qt_board_create(string $title): mixed {}
function qt_board_is_open(mixed $window): bool {}
function qt_board_process_events(mixed $window): void {}
function qt_board_poll_event(mixed $window): array {}
function qt_board_set_view(mixed $window, array $rows, array $metrics, string $selectedId): void {}
function qt_board_snapshot(mixed $window, string $path): bool {}
function qt_board_show_error(mixed $window, string $message): void {}
function qt_board_destroy(mixed $window): void {}

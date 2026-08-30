<?php

/** Native Qt GUI and QProcess bridge. Application logic lives in TypePHP. */
function qt_tunnel_create(string $title): mixed {}
function qt_tunnel_is_open(mixed $window): bool {}
function qt_tunnel_process_events(mixed $window): void {}
function qt_tunnel_poll_event(mixed $window): array {}
function qt_tunnel_set_rules(mixed $window, array $rules): void {}
function qt_tunnel_start_process(
    mixed $window,
    string $id,
    string $program,
    array $arguments
): bool {}
function qt_tunnel_stop_process(mixed $window, string $id): void {}
function qt_tunnel_append_log(mixed $window, string $id, string $message): void {}
function qt_tunnel_show_error(mixed $window, string $message): void {}
function qt_tunnel_destroy(mixed $window): void {}

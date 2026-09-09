<?php

function ui_create_window(string $title, int $width, int $height): void {}

function ui_add_image(string $resourceName, int $x, int $y, int $width, int $height): int {}

function ui_add_label(
    string $text,
    int $x,
    int $y,
    int $width,
    int $height,
    int $fontSize,
    bool $bold,
): int {}

function ui_add_text_input(string $hint, int $x, int $y, int $width, int $height): int {}

function ui_add_button(string $title, int $x, int $y, int $width, int $height, int $style): int {}

function ui_set_control_text(int $controlId, string $text): void {}

function ui_get_control_text(int $controlId): string {}

function ui_show_window(): void {}

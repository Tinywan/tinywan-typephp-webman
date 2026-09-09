<?php

/** Enter the UIKit-owned iOS application lifecycle. */
function ui_app_run(string $applicationName): void {}

/** Thin wrappers around UIKit; coordinates use the shared 640x380 logical canvas. */
function ui_create_window(string $title, int $width, int $height): void {}

function ui_add_label(
    string $text,
    int $x,
    int $y,
    int $width,
    int $height,
    int $fontSize,
    bool $bold
): int {}

function ui_add_button(string $title, int $x, int $y, int $width, int $height, int $style): int {}

function ui_set_control_text(int $controlId, string $text): void {}

function ui_show_window(): void {}

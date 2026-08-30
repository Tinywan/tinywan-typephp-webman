<?php

/**
 * Win32 API declarations (stub)
 * C++ only provides thin wrappers around Win32 APIs.
 * ALL game logic is implemented in PHP.
 */

// Window management
function win_create_window(string $title, int $width, int $height): int {}
function win_show_window(int $hWnd, int $cmdShow): void {}
function win_quit_requested(): bool {}
function win_post_quit(int $exitCode): void {}
function win_peek_message(): array {}
function win_get_tick_count(): int {}
function win_message_box(int $hWnd, string $text, string $caption, int $uType): int {}

// GDI drawing primitives
function win_begin_paint(int $hWnd): int {}
function win_end_paint(int $hWnd, int $hdc): void {}
function win_fill_rect(int $hdc, int $x, int $y, int $w, int $h, int $rgb): void {}
function win_draw_block(int $hdc, int $x, int $y, int $size, int $rgb): void {}
function win_draw_line(int $hdc, int $x1, int $y1, int $x2, int $y2, int $rgb): void {}
function win_draw_text(int $hdc, int $x, int $y, string $text, int $fontSize, int $rgb, int $bold): void {}

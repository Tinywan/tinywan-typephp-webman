<?php

/**
 * Win32 API declarations (stub).
 *
 * C++ (cpp-src/win32.cc) only provides thin wrappers around Win32 APIs and
 * GDI drawing primitives. ALL game logic and rendering live in PHP.
 *
 * These empty declarations tell the TypePHP compiler about the native
 * functions; the real implementations are linked from win32.cc.
 */

// ---- Window & message loop ----
function win_create_window(string $title, int $width, int $height): int {}
/** Current client-area size as [width, height]. */
function win_get_client_size(int $hWnd): array {}
function win_show_window(int $hWnd, int $cmdShow): void {}
function win_quit_requested(): bool {}
function win_post_quit(int $exitCode): void {}
/** Returns [type, a, b, c]; empty array when no message pending. */
function win_peek_message(): array {}
function win_get_tick_count(): int {}
function win_message_box(int $hWnd, string $text, string $caption, int $uType): int {}
function win_message_beep(int $type): void {}

// ---- Double-buffered frame ----
function win_begin_paint(int $hWnd): int {}
function win_end_paint(int $hWnd, int $hdc): void {}

// ---- GDI primitives ----
function win_fill_rect(int $hdc, int $x, int $y, int $w, int $h, int $rgb): void {}
function win_draw_block(int $hdc, int $x, int $y, int $size, int $rgb): void {}
function win_draw_line(int $hdc, int $x1, int $y1, int $x2, int $y2, int $rgb): void {}
/** Ellipse centered at (cx, cy) with width/height. */
function win_fill_ellipse(int $hdc, int $cx, int $cy, int $w, int $h, int $rgb): void {}
function win_fill_rounded_rect(int $hdc, int $x, int $y, int $w, int $h, int $radius, int $rgb): void {}
function win_stroke_rounded_rect(int $hdc, int $x, int $y, int $w, int $h, int $radius, int $rgb, int $thickness): void {}
/** Plain ASCII text. */
function win_draw_text(int $hdc, int $x, int $y, string $text, int $fontSize, int $rgb, int $bold): void {}
/** UTF-8 text; align: 0=left, 1=center, 2=right. width=0 means no alignment. */
function win_draw_text_ex(int $hdc, int $x, int $y, string $text, int $fontSize, int $rgb, int $bold, int $width, int $align): void {}

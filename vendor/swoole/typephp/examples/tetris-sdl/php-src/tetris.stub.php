<?php

/**
 * Tetris Game C++ API declarations (stub)
 * These functions are implemented in C++, PHP layer only declares them
 */

// 游戏控制函数
function tetris_new(): mixed {}
function tetris_reset(mixed $game): void {}
function tetris_get_score(mixed $game): int {}
function tetris_is_game_over(mixed $game): bool {}

// Stub functions - logic is in PHP, these are just placeholders
function tetris_rotate(mixed $game): void {}
function tetris_move_down(mixed $game): bool {}
function tetris_move_left(mixed $game): bool {}
function tetris_move_right(mixed $game): bool {}
function tetris_hard_drop(mixed $game): void {}

// SDL 窗口和渲染函数
function tetris_poll_event(mixed $game): array {}
function tetris_set_board(mixed $game, array $board): void {}
function tetris_set_score(mixed $game, int $score): void {}
function tetris_render(mixed $game, int $hWnd): void {}

// SDL 工具函数
function SDL_GetTicks(): int {}

// 工具函数
function tetris_messagebox(int $hWnd, string $text, string $caption, int $uType): int {}
function tetris_post_quit(int $exitCode): void {}

<?php

/**
 * Windows API wrapper function declarations (stub)
 * These functions are implemented in C++, PHP layer only declares them
 */

// Show message box
function messagebox(int $hWnd, string $text, string $caption, int $uType): int {}

// Get module handle
function get_module_handle(string $moduleName): int {}

// Create window
function create_window(string $className, string $windowName, int $style, int $x, int $y, int $width, int $height): int {}

// Show window
function show_window(int $hWnd, int $cmdShow): bool {}

// Update window
function update_window(int $hWnd): bool {}

// Exit message loop
function post_quit_message(int $exitCode): void {}

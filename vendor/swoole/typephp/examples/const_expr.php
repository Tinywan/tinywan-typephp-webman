<?php

/**
 * Tetris Game - Pure PHP Logic + Win32 C++ Drawing Primitives
 *
 * This project demonstrates the AOT compiler's capability:
 * - C++ only wraps Win32 APIs (window, message, GDI drawing)
 * - ALL game logic is implemented in PHP
 */

// ============================================================
// Constants
// ============================================================

const BLOCK_SIZE = 30;
const BOARD_HEIGHT = 20;
const WINDOW_HEIGHT = BLOCK_SIZE * BOARD_HEIGHT + 40;

function main(): void
{
    var_dump(BLOCK_SIZE, BOARD_HEIGHT, WINDOW_HEIGHT);
}

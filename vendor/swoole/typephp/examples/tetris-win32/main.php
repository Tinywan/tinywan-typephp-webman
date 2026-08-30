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
const BOARD_WIDTH = 10;
const BOARD_HEIGHT = 20;
const SIDEBAR_WIDTH = 180;
const WINDOW_WIDTH = BLOCK_SIZE * BOARD_WIDTH + SIDEBAR_WIDTH + 16;
const WINDOW_HEIGHT = BLOCK_SIZE * BOARD_HEIGHT + 40;

// Win32 constants
const SW_SHOW = 5;
const MB_YESNO = 0x00000004;
const IDYES = 6;
const VK_LEFT = 0x25;
const VK_RIGHT = 0x27;
const VK_UP = 0x26;
const VK_DOWN = 0x28;
const VK_SPACE = 0x20;
const VK_W = 0x57;
const VK_A = 0x41;
const VK_S = 0x53;
const VK_D = 0x44;
const WM_KEYDOWN = 0x0100;
const WM_QUIT = 0x0012;

// RGB helper
function rgb(int $r, int $g, int $b): int
{
    return ($r | ($g << 8) | ($b << 16));
}

// Piece colors
const COLOR_CYAN    = 0xFFFF00; // I
const COLOR_YELLOW  = 0x00FFFF; // O
const COLOR_PURPLE  = 0x800080; // T
const COLOR_GREEN   = 0x00FF00; // S
const COLOR_RED     = 0x0000FF; // Z
const COLOR_BLUE    = 0xFF0000; // J
const COLOR_ORANGE  = 0x00A5FF; // L

const PIECE_COLORS = [
    COLOR_CYAN,
    COLOR_YELLOW,
    COLOR_PURPLE,
    COLOR_GREEN,
    COLOR_RED,
    COLOR_BLUE,
    COLOR_ORANGE,
];

// 7 tetromino shapes (4 rotations each, 4x4 grid) - defined in PHP!
const SHAPES = [
    // I
    [
        [[0,0,0,0],[1,1,1,1],[0,0,0,0],[0,0,0,0]],
        [[0,0,1,0],[0,0,1,0],[0,0,1,0],[0,0,1,0]],
        [[0,0,0,0],[0,0,0,0],[1,1,1,1],[0,0,0,0]],
        [[0,1,0,0],[0,1,0,0],[0,1,0,0],[0,1,0,0]],
    ],
    // O
    [
        [[0,0,0,0],[0,1,1,0],[0,1,1,0],[0,0,0,0]],
        [[0,0,0,0],[0,1,1,0],[0,1,1,0],[0,0,0,0]],
        [[0,0,0,0],[0,1,1,0],[0,1,1,0],[0,0,0,0]],
        [[0,0,0,0],[0,1,1,0],[0,1,1,0],[0,0,0,0]],
    ],
    // T
    [
        [[0,0,0,0],[0,1,0,0],[1,1,1,0],[0,0,0,0]],
        [[0,0,0,0],[0,1,0,0],[0,1,1,0],[0,1,0,0]],
        [[0,0,0,0],[0,0,0,0],[1,1,1,0],[0,1,0,0]],
        [[0,0,0,0],[0,1,0,0],[1,1,0,0],[0,1,0,0]],
    ],
    // S
    [
        [[0,0,0,0],[0,1,1,0],[1,1,0,0],[0,0,0,0]],
        [[0,0,0,0],[0,1,0,0],[0,1,1,0],[0,0,1,0]],
        [[0,0,0,0],[0,0,0,0],[0,1,1,0],[1,1,0,0]],
        [[0,0,0,0],[1,0,0,0],[1,1,0,0],[0,1,0,0]],
    ],
    // Z
    [
        [[0,0,0,0],[1,1,0,0],[0,1,1,0],[0,0,0,0]],
        [[0,0,0,0],[0,0,1,0],[0,1,1,0],[0,1,0,0]],
        [[0,0,0,0],[0,0,0,0],[1,1,0,0],[0,1,1,0]],
        [[0,0,0,0],[0,1,0,0],[1,1,0,0],[1,0,0,0]],
    ],
    // J
    [
        [[0,0,0,0],[1,0,0,0],[1,1,1,0],[0,0,0,0]],
        [[0,0,0,0],[0,1,1,0],[0,1,0,0],[0,1,0,0]],
        [[0,0,0,0],[0,0,0,0],[1,1,1,0],[0,0,1,0]],
        [[0,0,0,0],[0,1,0,0],[0,1,0,0],[1,1,0,0]],
    ],
    // L
    [
        [[0,0,0,0],[0,0,1,0],[1,1,1,0],[0,0,0,0]],
        [[0,0,0,0],[0,1,0,0],[0,1,0,0],[0,1,1,0]],
        [[0,0,0,0],[0,0,0,0],[1,1,1,0],[1,0,0,0]],
        [[0,0,0,0],[1,1,0,0],[0,1,0,0],[0,1,0,0]],
    ],
];

// ============================================================
// TetrisBoard - Pure PHP game logic
// ============================================================

class TetrisBoard
{
    public array $board;       // 20x10 grid, 0=empty, 1-7=piece type
    public int $score;
    public bool $gameOver;
    public int $pieceType;     // 0-6
    public int $pieceRotation; // 0-3
    public int $pieceX;        // column
    public int $pieceY;        // row
    public int $nextType;      // next piece type

    public function __construct()
    {
        $this->board = array_fill(0, BOARD_HEIGHT, array_fill(0, BOARD_WIDTH, 0));
        $this->score = 0;
        $this->gameOver = false;
        $this->pieceType = random_int(0, 6);
        $this->pieceRotation = 0;
        $this->pieceX = 3;
        $this->pieceY = 0;
        $this->nextType = random_int(0, 6);
    }

    public function reset(): void
    {
        $this->board = array_fill(0, BOARD_HEIGHT, array_fill(0, BOARD_WIDTH, 0));
        $this->score = 0;
        $this->gameOver = false;
        $this->pieceType = random_int(0, 6);
        $this->pieceRotation = 0;
        $this->pieceX = 3;
        $this->pieceY = 0;
        $this->nextType = random_int(0, 6);
    }

    /** Check if the given piece at given position/rotation collides */
    public function collides(int $px, int $py, int $rot): bool
    {
        $shape = SHAPES[$this->pieceType][$rot];
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                if ($shape[$i][$j]) {
                    $bx = $px + $j;
                    $by = $py + $i;
                    if ($bx < 0 || $bx >= BOARD_WIDTH || $by >= BOARD_HEIGHT) {
                        return true;
                    }
                    if ($by >= 0 && $this->board[$by][$bx] != 0) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /** Lock current piece onto the board */
    public function lockPiece(): void
    {
        $shape = SHAPES[$this->pieceType][$this->pieceRotation];
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                if ($shape[$i][$j]) {
                    $bx = $this->pieceX + $j;
                    $by = $this->pieceY + $i;
                    if ($by >= 0 && $by < BOARD_HEIGHT && $bx >= 0 && $bx < BOARD_WIDTH) {
                        $this->board[$by][$bx] = $this->pieceType + 1;
                    }
                }
            }
        }
    }

    /** Clear completed lines, return number of lines cleared */
    public function clearLines(): int
    {
        $lines = 0;
        for ($i = BOARD_HEIGHT - 1; $i >= 0; $i--) {
            $full = true;
            for ($j = 0; $j < BOARD_WIDTH; $j++) {
                if ($this->board[$i][$j] == 0) {
                    $full = false;
                    break;
                }
            }
            if ($full) {
                $lines++;
                // Shift rows down
                for ($k = $i; $k > 0; $k--) {
                    for ($j = 0; $j < BOARD_WIDTH; $j++) {
                        $this->board[$k][$j] = $this->board[$k - 1][$j];
                    }
                }
                for ($j = 0; $j < BOARD_WIDTH; $j++) {
                    $this->board[0][$j] = 0;
                }
                $i++; // recheck same row
            }
        }
        return $lines;
    }

    /** Spawn a new piece */
    public function spawnPiece(): void
    {
        $this->pieceType = $this->nextType;
        $this->nextType = random_int(0, 6);
        $this->pieceRotation = 0;
        $this->pieceX = 3;
        $this->pieceY = 0;
        if ($this->collides($this->pieceX, $this->pieceY, $this->pieceRotation)) {
            $this->gameOver = true;
        }
    }

    /** Add score for clearing lines */
    public function addLineScore(int $lines): void
    {
        $scores = [0, 100, 300, 500, 800];
        if ($lines > 0 && $lines <= 4) {
            $this->score += $scores[$lines];
        }
    }

    /** Move piece down. Returns true if moved, false if locked */
    public function moveDown(): bool
    {
        if ($this->gameOver) return false;
        if (!$this->collides($this->pieceX, $this->pieceY + 1, $this->pieceRotation)) {
            $this->pieceY++;
            return true;
        }
        // Lock and spawn
        $this->lockPiece();
        $lines = $this->clearLines();
        $this->addLineScore($lines);
        $this->spawnPiece();
        return false;
    }

    public function moveLeft(): bool
    {
        if ($this->gameOver) return false;
        if (!$this->collides($this->pieceX - 1, $this->pieceY, $this->pieceRotation)) {
            $this->pieceX--;
            return true;
        }
        return false;
    }

    public function moveRight(): bool
    {
        if ($this->gameOver) return false;
        if (!$this->collides($this->pieceX + 1, $this->pieceY, $this->pieceRotation)) {
            $this->pieceX++;
            return true;
        }
        return false;
    }

    public function rotate(): bool
    {
        if ($this->gameOver) return false;
        $newRot = ($this->pieceRotation + 1) % 4;
        if (!$this->collides($this->pieceX, $this->pieceY, $newRot)) {
            $this->pieceRotation = $newRot;
            return true;
        }
        return false;
    }

    public function hardDrop(): void
    {
        if ($this->gameOver) return;
        while (!$this->collides($this->pieceX, $this->pieceY + 1, $this->pieceRotation)) {
            $this->pieceY++;
            $this->score += 2;
        }
        $this->lockPiece();
        $lines = $this->clearLines();
        $this->addLineScore($lines);
        $this->spawnPiece();
    }

    public function getLevel(): int
    {
        return intdiv($this->score, 500) + 1;
    }
}

// ============================================================
// TetrisRenderer - Uses C++ drawing primitives
// ============================================================

class TetrisRenderer
{
    private int $hWnd;

    public function __construct(int $hWnd)
    {
        $this->hWnd = $hWnd;
    }

    public function render(TetrisBoard $game): void
    {
        $hdc = win_begin_paint($this->hWnd);
        $boardPxW = BLOCK_SIZE * BOARD_WIDTH;
        $boardPxH = BLOCK_SIZE * BOARD_HEIGHT;

        // Clear background
        win_fill_rect($hdc, 0, 0, $boardPxW + SIDEBAR_WIDTH, $boardPxH, rgb(0, 0, 0));

        // Draw board (locked pieces)
        for ($i = 0; $i < BOARD_HEIGHT; $i++) {
            for ($j = 0; $j < BOARD_WIDTH; $j++) {
                if ($game->board[$i][$j] != 0) {
                    $colorIdx = $game->board[$i][$j] - 1;
                    win_draw_block($hdc, $j * BLOCK_SIZE, $i * BLOCK_SIZE, BLOCK_SIZE, PIECE_COLORS[$colorIdx]);
                }
            }
        }

        // Draw current piece
        if (!$game->gameOver) {
            $shape = SHAPES[$game->pieceType][$game->pieceRotation];
            for ($i = 0; $i < 4; $i++) {
                for ($j = 0; $j < 4; $j++) {
                    if ($shape[$i][$j]) {
                        $bx = $game->pieceX + $j;
                        $by = $game->pieceY + $i;
                        if ($by >= 0 && $by < BOARD_HEIGHT && $bx >= 0 && $bx < BOARD_WIDTH) {
                            win_draw_block($hdc, $bx * BLOCK_SIZE, $by * BLOCK_SIZE, BLOCK_SIZE, PIECE_COLORS[$game->pieceType]);
                        }
                    }
                }
            }
        }

        // Draw grid lines
        $gridColor = rgb(40, 40, 40);
        for ($i = 0; $i <= BOARD_HEIGHT; $i++) {
            win_draw_line($hdc, 0, $i * BLOCK_SIZE, $boardPxW, $i * BLOCK_SIZE, $gridColor);
        }
        for ($j = 0; $j <= BOARD_WIDTH; $j++) {
            win_draw_line($hdc, $j * BLOCK_SIZE, 0, $j * BLOCK_SIZE, $boardPxH, $gridColor);
        }

        // Sidebar separator
        win_draw_line($hdc, $boardPxW, 0, $boardPxW, $boardPxH, rgb(100, 100, 100));

        // Sidebar content
        $sx = $boardPxW + 10;
        $white = rgb(255, 255, 255);
        $gray = rgb(150, 150, 150);

        // SCORE
        win_draw_text($hdc, $sx, 20, "SCORE", 24, $white, 1);
        win_draw_text($hdc, $sx, 48, (string)$game->score, 28, $white, 1);

        // NEXT
        win_draw_text($hdc, $sx, 110, "NEXT", 24, $white, 1);
        $previewSize = 20;
        $previewX = $sx + 10;
        $previewY = 140;
        $nextShape = SHAPES[$game->nextType][0];
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                if ($nextShape[$i][$j]) {
                    win_draw_block($hdc, $previewX + $j * $previewSize, $previewY + $i * $previewSize, $previewSize, PIECE_COLORS[$game->nextType]);
                }
            }
        }

        // LEVEL
        win_draw_text($hdc, $sx, 230, "LEVEL", 24, $white, 1);
        win_draw_text($hdc, $sx, 258, (string)$game->getLevel(), 28, $white, 1);

        // Controls
        win_draw_text($hdc, $sx, 340, "Arrow/WASD: Move", 14, $gray, 0);
        win_draw_text($hdc, $sx, 360, "Up/W: Rotate", 14, $gray, 0);
        win_draw_text($hdc, $sx, 380, "Space: Drop", 14, $gray, 0);

        // Game Over overlay
        if ($game->gameOver) {
            win_fill_rect($hdc, 0, 0, $boardPxW, $boardPxH, rgb(0, 0, 0));
            // Center "GAME OVER" text
            $goX = intdiv($boardPxW, 2) - 90;
            $goY = intdiv($boardPxH, 2) - 30;
            win_draw_text($hdc, $goX, $goY, "GAME OVER", 36, rgb(255, 50, 50), 1);
        }

        win_end_paint($this->hWnd, $hdc);
    }
}

// ============================================================
// TetrisGame - Main game controller
// ============================================================

class TetrisGame
{
    private TetrisBoard $board;
    private int $hWnd;
    private TetrisRenderer $renderer;
    private int $lastDropTime;
    private int $dropInterval;

    public function __construct()
    {
        $this->board = new TetrisBoard();
        $this->hWnd = 0;
        $this->lastDropTime = 0;
        $this->dropInterval = 1000;
    }

    public function initWindow(): void
    {
        $this->hWnd = win_create_window("Tetris - PHP AOT", WINDOW_WIDTH, WINDOW_HEIGHT);

        if ($this->hWnd == 0) {
            echo "Error: window creation failed!\n";
            return;
        }

        win_show_window($this->hWnd, SW_SHOW);
        $this->renderer = new TetrisRenderer($this->hWnd);
        $this->lastDropTime = win_get_tick_count();
        echo "Window created\n";
    }

    public function run(): void
    {
        $running = true;
        $frameCount = 0;
        echo "Game started!\n";

        while ($running) {
            // Process Windows messages
            while (true) {
                $msg = win_peek_message();
                if (count($msg) == 0) break;

                $msgType = $msg[1] ?? 0;

                if ($msgType == WM_KEYDOWN) {
                    $keyCode = $msg[2] ?? 0;
                    $this->handleKeyPress($keyCode);
                }

                if ($msgType == WM_QUIT) {
                    $running = false;
                    break;
                }
            }

            if (win_quit_requested()) {
                $running = false;
            }
            if (!$running) break;

            // Auto drop
            $currentTime = win_get_tick_count();
            if ($currentTime - $this->lastDropTime > $this->dropInterval) {
                if (!$this->board->gameOver) {
                    $this->board->moveDown();
                    $this->dropInterval = max(200, 1000 - intdiv($this->board->score, 500) * 100);
                }
                $this->lastDropTime = $currentTime;
            }

            // Render
            $this->renderer->render($this->board);

            // Check game over
            if ($this->board->gameOver) {
                $this->handleGameOver();
                break;
            }

            // Log every 60 frames
            $frameCount++;
            if ($frameCount % 60 == 0) {
                echo "Frame {$frameCount}, score={$this->board->score}\n";
            }

            usleep(16000); // ~60 FPS
        }

        echo "Game ended\n";
    }

    private function handleKeyPress(int $keyCode): void
    {
        switch ($keyCode) {
            case VK_LEFT:
            case VK_A:
                $this->board->moveLeft();
                break;
            case VK_RIGHT:
            case VK_D:
                $this->board->moveRight();
                break;
            case VK_UP:
            case VK_W:
                $this->board->rotate();
                break;
            case VK_DOWN:
            case VK_S:
                $this->board->moveDown();
                break;
            case VK_SPACE:
                $this->board->hardDrop();
                break;
        }
    }

    private function handleGameOver(): void
    {
        $score = $this->board->score;
        echo "Game Over! Score: {$score}\n";

        $result = win_message_box(
            $this->hWnd,
            "Game Over!\nScore: {$score}\nPlay again?",
            "Game Over",
            MB_YESNO
        );

        if ($result == IDYES) {
            $this->board->reset();
            $this->lastDropTime = win_get_tick_count();
            $this->dropInterval = 1000;
        } else {
            win_post_quit(0);
        }
    }
}

// ============================================================
// Entry point
// ============================================================

function main(): void
{
    date_default_timezone_set('Asia/Shanghai');

    echo "========================================\n";
    echo "   Tetris - PHP AOT Compiler Demo\n";
    echo "   (Game logic 100% in PHP)\n";
    echo "========================================\n\n";

    $game = new TetrisGame();
    $game->initWindow();
    $game->run();

    echo "\nThanks for playing!\n";
}

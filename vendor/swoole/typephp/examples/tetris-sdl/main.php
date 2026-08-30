<?php

/**
 * Tetris Game - Logic in PHP, SDL operations in C++
 */

// SDL Constants
const SDL_WINDOWPOS_UNDEFINED = 0x1FFF0000;
const SDL_WINDOW_SHOWN = 0x00000004;
const SDL_QUIT = 256;
const SDL_KEYDOWN = 768;

// Tetromino shapes (7 types)
const TETROMINOES = [
    // I
    [[0,0,0,0], [1,1,1,1], [0,0,0,0], [0,0,0,0]],
    // O
    [[0,0,0,0], [0,1,1,0], [0,1,1,0], [0,0,0,0]],
    // T
    [[0,0,0,0], [0,1,0,0], [1,1,1,0], [0,0,0,0]],
    // S
    [[0,0,0,0], [0,1,1,0], [1,1,0,0], [0,0,0,0]],
    // Z
    [[0,0,0,0], [1,1,0,0], [0,1,1,0], [0,0,0,0]],
    // J
    [[0,0,0,0], [1,0,0,0], [1,1,1,0], [0,0,0,0]],
    // L
    [[0,0,0,0], [0,0,1,0], [1,1,1,0], [0,0,0,0]],
];

class TetrisGame
{
    private mixed $game;
    private array $board;
    private array $currentPiece;
    private int $currentX;
    private int $currentY;
    private int $currentType;
    private int $score;
    private bool $gameOver;
    private int $lastDropTime;
    private int $dropInterval;
    
    const BOARD_WIDTH = 10;
    const BOARD_HEIGHT = 20;
    
    public function __construct()
    {
        echo "正在创建游戏实例...\n";
        $this->game = tetris_new();
        echo "游戏实例已创建\n";
        
        $this->initGameState();
        
        // Initialize timing
        $this->lastDropTime = SDL_GetTicks();
        $this->dropInterval = 1000; // 1 second auto drop
        
        echo "[PHP] Initial time: {$this->lastDropTime}, interval: {$this->dropInterval}ms\n";
    }
    
    private function initGameState(): void
    {
        // Initialize empty board
        $this->board = [];
        for ($i = 0; $i < self::BOARD_HEIGHT; $i++) {
            $row = [];
            for ($j = 0; $j < self::BOARD_WIDTH; $j++) {
                $row[] = 0;
            }
            $this->board[] = $row;
        }
        
        $this->score = 0;
        $this->gameOver = false;
        $this->spawnNewPiece();
        
        echo "[PHP] Game initialized\n";
    }
    
    private function spawnNewPiece(): void
    {
        $this->currentType = rand(0, 6);
        $this->currentPiece = TETROMINOES[$this->currentType];
        $this->currentX = intdiv(self::BOARD_WIDTH - 4, 2);
        $this->currentY = 0;
        
        // Check game over
        if (!$this->isValidPosition($this->currentX, $this->currentY)) {
            $this->gameOver = true;
            echo "[PHP] Game Over!\n";
        }
        
        echo "[PHP] Spawned piece type: {$this->currentType}\n";
    }
    
    private function isValidPosition(int $x, int $y): bool
    {
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                if ($this->currentPiece[$i][$j]) {
                    $newX = $x + $j;
                    $newY = $y + $i;
                    
                    if ($newX < 0 || $newX >= self::BOARD_WIDTH || $newY >= self::BOARD_HEIGHT) {
                        return false;
                    }
                    
                    if ($newY >= 0 && $this->board[$newY][$newX]) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
    
    public function moveLeft(): bool
    {
        if ($this->gameOver) return false;
        
        if ($this->isValidPosition($this->currentX - 1, $this->currentY)) {
            $this->currentX--;
            return true;
        }
        return false;
    }
    
    public function moveRight(): bool
    {
        if ($this->gameOver) return false;
        
        if ($this->isValidPosition($this->currentX + 1, $this->currentY)) {
            $this->currentX++;
            return true;
        }
        return false;
    }
    
    public function moveDown(): bool
    {
        if ($this->gameOver) return false;
        
        if ($this->isValidPosition($this->currentX, $this->currentY + 1)) {
            $this->currentY++;
            return true;
        } else {
            $this->lockPiece();
            $this->spawnNewPiece();
            return false;
        }
    }
    
    public function rotate(): void
    {
        if ($this->gameOver) return;
        
        // Rotate 90 degrees clockwise
        $newPiece = [];
        for ($i = 0; $i < 4; $i++) {
            $newPiece[$i] = [];
            for ($j = 0; $j < 4; $j++) {
                $newPiece[$i][$j] = $this->currentPiece[3 - $j][$i];
            }
        }
        
        // Save old piece
        $oldPiece = $this->currentPiece;
        $this->currentPiece = $newPiece;
        
        // Check if rotation is valid
        if (!$this->isValidPosition($this->currentX, $this->currentY)) {
            // Revert
            $this->currentPiece = $oldPiece;
        }
    }
    
    private function lockPiece(): void
    {
        $color = $this->currentType + 1;
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                if ($this->currentPiece[$i][$j]) {
                    $boardY = $this->currentY + $i;
                    $boardX = $this->currentX + $j;
                    if ($boardY >= 0 && $boardY < self::BOARD_HEIGHT && $boardX >= 0 && $boardX < self::BOARD_WIDTH) {
                        $this->board[$boardY][$boardX] = $color;
                    }
                }
            }
        }
        
        echo "[PHP] Piece locked\n";
        $this->clearLines();
    }
    
    private function clearLines(): void
    {
        $linesCleared = 0;
        for ($y = self::BOARD_HEIGHT - 1; $y >= 0; $y--) {
            $fullLine = true;
            for ($x = 0; $x < self::BOARD_WIDTH; $x++) {
                if ($this->board[$y][$x] == 0) {
                    $fullLine = false;
                    break;
                }
            }
            
            if ($fullLine) {
                array_splice($this->board, $y, 1);
                $newRow = [];
                for ($x = 0; $x < self::BOARD_WIDTH; $x++) {
                    $newRow[] = 0;
                }
                array_unshift($this->board, $newRow);
                $linesCleared++;
                $y++;
            }
        }
        
        if ($linesCleared > 0) {
            $this->score += $linesCleared * 100;
            echo "[PHP] Cleared {$linesCleared} lines, score: {$this->score}\n";
        }
    }
    
    private function updateBoard(): void
    {
        // Create render board with current piece
        $renderBoard = $this->board;
        
        if (!$this->gameOver) {
            $color = $this->currentType + 1;
            for ($i = 0; $i < 4; $i++) {
                for ($j = 0; $j < 4; $j++) {
                    if ($this->currentPiece[$i][$j]) {
                        $boardY = $this->currentY + $i;
                        $boardX = $this->currentX + $j;
                        if ($boardY >= 0 && $boardY < self::BOARD_HEIGHT && $boardX >= 0 && $boardX < self::BOARD_WIDTH) {
                            $renderBoard[$boardY][$boardX] = $color;
                        }
                    }
                }
            }
        }
        
        // Sync to C++
        tetris_set_board($this->game, $renderBoard);
        tetris_set_score($this->game, $this->score);
    }
    
    private function handleKeyPress(int $keyCode): void
    {
        switch ($keyCode) {
            case 1073741904: // Left arrow
            case 97:  // 'a'
                $this->moveLeft();
                break;
                
            case 1073741903: // Right arrow
            case 100: // 'd'
                $this->moveRight();
                break;
                
            case 1073741906: // Up arrow
            case 119: // 'w'
                $this->rotate();
                break;
                
            case 1073741905: // Down arrow
            case 115: // 's'
                $this->moveDown();
                break;
                
            case 32: // Space
                while ($this->moveDown()) {
                    // Hard drop
                }
                break;
        }
    }
    
    public function run(): void
    {
        echo "游戏开始！\n";
        $frameCount = 0;
        
        while (true) {
            // Handle events
            $event = tetris_poll_event($this->game);
            
            if (!empty($event)) {
                $eventType = $event[0] ?? 0;
                
                if ($eventType == 256) { // SDL_QUIT
                    echo "[PHP] Received QUIT event\n";
                    break;
                }
                
                if ($eventType == 768) { // SDL_KEYDOWN
                    $keyCode = $event[1] ?? 0;
                    $this->handleKeyPress($keyCode);
                }
            }
            
            // Auto drop
            $currentTime = SDL_GetTicks();
            if ($currentTime - $this->lastDropTime > $this->dropInterval) {
                if (!$this->gameOver) {
                    echo "[PHP] Auto drop triggered at time {$currentTime}\n";
                    $this->moveDown();
                    // Increase speed based on score (max speed 100ms)
                    $this->dropInterval = max(100, 1000 - intdiv($this->score, 500) * 50);
                }
                $this->lastDropTime = $currentTime;
            }
            
            // Update and render
            $this->updateBoard();
            tetris_render($this->game, 0);
            
            $frameCount++;
            if ($frameCount % 60 == 0) {
                echo "[PHP] Frame {$frameCount}, Score: {$this->score}\n";
            }
            
            if ($this->gameOver) {
                echo "游戏结束！最终得分: {$this->score}\n";
                break;
            }
            
            usleep(16000); // ~60 FPS
        }
    }
}

function main(): void
{
    date_default_timezone_set('Asia/Shanghai');
    
    echo "========================================\n";
    echo "   俄罗斯方块 - PHP 编译器演示\n";
    echo "========================================\n\n";
    
    $game = new TetrisGame();
    
    echo "控制说明:\n";
    echo "  A/D 或 ← → : 左右移动\n";
    echo "  W 或 ↑   : 旋转方块\n";
    echo "  S 或 ↓   : 加速下落\n";
    echo "  空格 : 直接落下\n\n";
    
    $game->run();
    
    echo "\n感谢游玩！\n";
}

#include <phpx.h>
#include <SDL2/SDL.h>
#include <map>
#include <algorithm>
#include <cstdio>
#include <cstdlib>
#include <cstring>

using namespace php;

// Game constants
#define BLOCK_SIZE 30
#define BOARD_WIDTH 10
#define BOARD_HEIGHT 20

// Global window map to store SDL_Window* and SDL_Renderer* by handle
static std::map<Int, std::pair<SDL_Window*, SDL_Renderer*>> g_windows;
static Int g_nextHandle = 1;

// Simple test function to verify SDL is working
void php_test_sdl_loop() {
    printf("[C++] Starting test_sdl_loop...\n");
    if (SDL_Init(SDL_INIT_VIDEO) < 0) {
        printf("[C++] SDL Init Error: %s\n", SDL_GetError());
        return;
    }

    SDL_Window* window = SDL_CreateWindow("Test Window", SDL_WINDOWPOS_UNDEFINED, SDL_WINDOWPOS_UNDEFINED, 640, 480, SDL_WINDOW_SHOWN);
    if (!window) {
        printf("[C++] Test Window Error: %s\n", SDL_GetError());
        return;
    }

    SDL_Renderer* renderer = SDL_CreateRenderer(window, -1, SDL_RENDERER_ACCELERATED);
    if (!renderer) {
        printf("[C++] Test Renderer Error: %s\n", SDL_GetError());
        SDL_DestroyWindow(window);
        return;
    }

    bool running = true;
    SDL_Event event;
    int frame = 0;
    while (running) {
        // Set color to Green
        SDL_SetRenderDrawColor(renderer, 0, 255, 0, 255);
        SDL_RenderClear(renderer);
        SDL_RenderPresent(renderer);
        
        while (SDL_PollEvent(&event)) {
            if (event.type == SDL_QUIT) {
                running = false;
            }
        }
        
        frame++;
        if (frame % 60 == 0) {
            printf("[C++] Test loop running... frame %d\n", frame);
        }
        SDL_Delay(16);
    }

    SDL_DestroyRenderer(renderer);
    SDL_DestroyWindow(window);
    SDL_Quit();
    printf("[C++] Test loop exited.\n");
}

// Colors for each piece type (SDL RGBA)
static const SDL_Color COLORS[7] = {
    {0, 255, 255, 255},   // I - Cyan
    {255, 255, 0, 255},   // O - Yellow
    {128, 0, 128, 255},   // T - Purple
    {0, 255, 0, 255},     // S - Green
    {255, 0, 0, 255},     // Z - Red
    {0, 0, 255, 255},     // J - Blue
    {255, 165, 0, 255}    // L - Orange
};

static void draw_filled_rect(SDL_Renderer* renderer, int x, int y, int w, int h) {
    SDL_Rect rect;
    rect.x = x;
    rect.y = y;
    rect.w = w;
    rect.h = h;
    SDL_RenderFillRect(renderer, &rect);
}

static void draw_seven_segment_digit(SDL_Renderer* renderer, int x, int y, int digit, int scale) {
    static const bool SEGMENTS[10][7] = {
        {true,  true,  true,  true,  true,  true,  false}, // 0
        {false, true,  true,  false, false, false, false}, // 1
        {true,  true,  false, true,  true,  false, true }, // 2
        {true,  true,  true,  true,  false, false, true }, // 3
        {false, true,  true,  false, false, true,  true }, // 4
        {true,  false, true,  true,  false, true,  true }, // 5
        {true,  false, true,  true,  true,  true,  true }, // 6
        {true,  true,  true,  false, false, false, false}, // 7
        {true,  true,  true,  true,  true,  true,  true }, // 8
        {true,  true,  true,  true,  false, true,  true }  // 9
    };

    if (digit < 0 || digit > 9) {
        return;
    }

    const int width = 10 * scale;
    const int height = 18 * scale;
    const int thickness = 2 * scale;
    const int midY = y + height / 2 - thickness / 2;

    if (SEGMENTS[digit][0]) draw_filled_rect(renderer, x + thickness, y, width - 2 * thickness, thickness);
    if (SEGMENTS[digit][1]) draw_filled_rect(renderer, x + width - thickness, y + thickness, thickness, height / 2 - thickness);
    if (SEGMENTS[digit][2]) draw_filled_rect(renderer, x + width - thickness, y + height / 2, thickness, height / 2 - thickness);
    if (SEGMENTS[digit][3]) draw_filled_rect(renderer, x + thickness, y + height - thickness, width - 2 * thickness, thickness);
    if (SEGMENTS[digit][4]) draw_filled_rect(renderer, x, y + height / 2, thickness, height / 2 - thickness);
    if (SEGMENTS[digit][5]) draw_filled_rect(renderer, x, y + thickness, thickness, height / 2 - thickness);
    if (SEGMENTS[digit][6]) draw_filled_rect(renderer, x + thickness, midY, width - 2 * thickness, thickness);
}

static void draw_score_number(SDL_Renderer* renderer, int x, int y, int score) {
    char scoreStr[16];
    snprintf(scoreStr, sizeof(scoreStr), "%d", std::max(0, score));

    int length = 0;
    while (scoreStr[length] != '\0') {
        length++;
    }

    const int scale = length > 6 ? 1 : 2;
    const int digitWidth = 10 * scale;
    const int spacing = 4 * scale;
    const int maxDigits = scale == 1 ? 12 : 6;

    int start = std::max(0, length - maxDigits);
    for (int i = start; i < length; i++) {
        int digit = scoreStr[i] - '0';
        int offset = i - start;
        draw_seven_segment_digit(renderer, x + offset * (digitWidth + spacing), y, digit, scale);
    }
}

// Simple game state - stores board data from PHP
class TetrisBox : public Box {
public:
    int board[BOARD_HEIGHT][BOARD_WIDTH];
    int score;
    bool gameOver;
    bool sdlInitialized;
    SDL_Window* window;
    SDL_Renderer* renderer;
    
    TetrisBox() : score(0), gameOver(false), sdlInitialized(false), window(nullptr), renderer(nullptr) {
        memset(board, 0, sizeof(board));
        printf("[C++] TetrisBox constructor called\n");
    }
    
    ~TetrisBox() override {
        printf("[C++] TetrisBox destructor called\n");
        cleanup();
    }

    void cleanup() {
        if (renderer) {
            SDL_DestroyRenderer(renderer);
            renderer = nullptr;
        }
        if (window) {
            SDL_DestroyWindow(window);
            window = nullptr;
        }
        if (sdlInitialized) {
            SDL_QuitSubSystem(SDL_INIT_VIDEO);
            sdlInitialized = false;
        }
    }
};

// Create new game instance
var php_tetris_new() {
    printf("[C++] php_tetris_new called\n");
    auto box = new TetrisBox();
    
    // Initialize SDL and create window
    if (SDL_Init(SDL_INIT_VIDEO) < 0) {
        printf("[C++] SDL Init Error: %s\n", SDL_GetError());
    } else {
        box->sdlInitialized = true;
        box->window = SDL_CreateWindow(
            "俄罗斯方块 - PHP版",
            SDL_WINDOWPOS_UNDEFINED,
            SDL_WINDOWPOS_UNDEFINED,
            BLOCK_SIZE * BOARD_WIDTH + 200,
            BLOCK_SIZE * BOARD_HEIGHT + 40,
            SDL_WINDOW_SHOWN
        );
        
        if (box->window) {
            box->renderer = SDL_CreateRenderer(box->window, -1, SDL_RENDERER_ACCELERATED);
            printf("[C++] Window and renderer created\n");
        }
    }
    
    return {box};
}

// Reset game
void php_tetris_reset(var box) {
    auto tetris = box.toBox<TetrisBox>();
    tetris->score = 0;
    tetris->gameOver = false;
    memset(tetris->board, 0, sizeof(tetris->board));
}

// Get score
Int php_tetris_get_score(var box) {
    auto tetris = box.toBox<TetrisBox>();
    return tetris->score;
}

// Check if game over
Bool php_tetris_is_game_over(var box) {
    auto tetris = box.toBox<TetrisBox>();
    return tetris->gameOver;
}

// Stub functions - logic is in PHP
Bool php_tetris_move_down(var box) { return true; }
Bool php_tetris_move_left(var box) { return true; }
Bool php_tetris_move_right(var box) { return true; }
void php_tetris_rotate(var box) {}
void php_tetris_hard_drop(var box) {}

// Process SDL events - returns array [type, sym, scancode] or empty array if no event
Array php_tetris_poll_event(var box) {
    SDL_Event event;
    Array result;
    if (SDL_PollEvent(&event)) {
        // Convert SDL_Event to PHP array
        result.append((Int)event.type);
        if (event.type == SDL_KEYDOWN || event.type == SDL_KEYUP) {
            result.append((Int)event.key.keysym.sym);
            result.append((Int)event.key.keysym.scancode);
        }
    }
    return result;
}

// Get SDL ticks (milliseconds since SDL init)
Int php_sdl_getticks() {
    return (Int)SDL_GetTicks();
}

// Set board state from PHP
void php_tetris_set_board(var box, Array board) {
    auto tetris = box.toBox<TetrisBox>();
    
    // Copy board data from PHP array to C++ array
    for (size_t i = 0; i < BOARD_HEIGHT && i < board.count(); i++) {
        auto row = board[i].toArray();
        for (size_t j = 0; j < BOARD_WIDTH && j < row.count(); j++) {
            tetris->board[i][j] = row[j].toInt();
        }
    }
}

// Set score calculated by PHP game logic
void php_tetris_set_score(var box, Int score) {
    auto tetris = box.toBox<TetrisBox>();
    tetris->score = (int)score;
}

// Render game - Draw board from PHP
void php_tetris_render(var box, Int hWnd) {
    auto tetris = box.toBox<TetrisBox>();
    
    if (!tetris->renderer) {
        return;
    }
    
    SDL_Renderer* renderer = tetris->renderer;
    
    // Clear background (Black)
    SDL_SetRenderDrawColor(renderer, 0, 0, 0, 255); 
    SDL_RenderClear(renderer);
    
    // Draw board from PHP
    for (int i = 0; i < BOARD_HEIGHT; i++) {
        for (int j = 0; j < BOARD_WIDTH; j++) {
            if (tetris->board[i][j]) {
                int colorIndex = tetris->board[i][j] - 1;
                if (colorIndex >= 0 && colorIndex < 7) {
                    SDL_SetRenderDrawColor(renderer, COLORS[colorIndex].r, COLORS[colorIndex].g, COLORS[colorIndex].b, COLORS[colorIndex].a);
                }
                
                SDL_Rect blockRect;
                blockRect.x = j * BLOCK_SIZE;
                blockRect.y = i * BLOCK_SIZE;
                blockRect.w = BLOCK_SIZE;
                blockRect.h = BLOCK_SIZE;
                SDL_RenderFillRect(renderer, &blockRect);
            }
        }
    }
    
    // Draw score panel on the right
    int panelX = BOARD_WIDTH * BLOCK_SIZE + 20;
    int panelY = 20;
    
    // Draw background for score panel
    SDL_SetRenderDrawColor(renderer, 40, 40, 40, 255);
    SDL_Rect panelRect;
    panelRect.x = panelX;
    panelRect.y = panelY;
    panelRect.w = 180;
    panelRect.h = 200;
    SDL_RenderFillRect(renderer, &panelRect);
    
    // Draw "SCORE" label using simple rectangles
    SDL_SetRenderDrawColor(renderer, 255, 255, 0, 255);
    for (int i = 0; i < 5; i++) {
        SDL_Rect bar;
        bar.x = panelX + 10;
        bar.y = panelY + 10 + i * 3;
        bar.w = 60;
        bar.h = 2;
        SDL_RenderFillRect(renderer, &bar);
    }
    
    // Display score value as seven-segment digits
    SDL_SetRenderDrawColor(renderer, 0, 255, 0, 255);
    draw_score_number(renderer, panelX + 20, panelY + 55, tetris->score);
    
    // Update the screen
    SDL_RenderPresent(renderer);
}

// Handle keyboard input
void php_tetris_handle_key(var box, Int keyCode) {
    auto tetris = box.toBox<TetrisBox>();
    // Simplified: just increase score for testing
    tetris->score += 1;
}

// Post quit message
void php_tetris_post_quit(Int exitCode) {
    SDL_Event event;
    memset(&event, 0, sizeof(event));
    event.type = SDL_QUIT;
    SDL_PushEvent(&event);
}

// Show message box with UTF-8 support
Int php_tetris_messagebox(Int hWnd, String text, String caption, Int uType) {
    // Use SDL's built-in message box function
    SDL_MessageBoxFlags flags = SDL_MESSAGEBOX_INFORMATION;
    if (uType & 0x00000010) { // MB_ICONERROR
        flags = SDL_MESSAGEBOX_ERROR;
    } else if (uType & 0x00000030) { // MB_ICONWARNING
        flags = SDL_MESSAGEBOX_WARNING;
    }
    
    int result = SDL_ShowSimpleMessageBox(flags, caption.data(), text.data(), nullptr);
    
    // Return appropriate value based on button pressed
    // For OK button, return 1
    return result == 0 ? 1 : 0;
}

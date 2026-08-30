/**
 * Tetris Win32 API Layer
 * 
 * C++ only wraps Win32 APIs as thin drawing primitives.
 * ALL game logic lives in PHP to showcase the AOT compiler.
 */

#include <phpx.h>
#include <windows.h>
#include <cstdio>

using namespace php;

// ============================================================
// Win32 Window & Message
// ============================================================

static bool g_quitRequested = false;

LRESULT CALLBACK TetrisWndProc(HWND hWnd, UINT msg, WPARAM wParam, LPARAM lParam) {
    switch (msg) {
        case WM_CLOSE:
            g_quitRequested = true;
            PostQuitMessage(0);
            return 0;
        case WM_DESTROY:
            g_quitRequested = true;
            PostQuitMessage(0);
            return 0;
    }
    return DefWindowProc(hWnd, msg, wParam, lParam);
}

// Create window, returns hWnd
Int php_win_create_window(String title, Int width, Int height) {
    SetConsoleOutputCP(65001);

    WNDCLASS wc;
    ZeroMemory(&wc, sizeof(wc));
    wc.style = CS_HREDRAW | CS_VREDRAW;
    wc.lpfnWndProc = TetrisWndProc;
    wc.hInstance = GetModuleHandle(NULL);
    wc.hCursor = LoadCursor(NULL, IDC_ARROW);
    wc.hbrBackground = (HBRUSH)(COLOR_WINDOW + 1);
    wc.lpszClassName = "TetrisWindow";
    RegisterClass(&wc);

    HWND hWnd = CreateWindowEx(
        0, "TetrisWindow", title.data(),
        WS_OVERLAPPEDWINDOW & ~WS_THICKFRAME & ~WS_MAXIMIZEBOX,
        CW_USEDEFAULT, CW_USEDEFAULT,
        (int)width, (int)height,
        NULL, NULL, GetModuleHandle(NULL), NULL
    );
    return (Int)hWnd;
}

// Show window
void php_win_show_window(Int hWnd, Int cmdShow) {
    ShowWindow((HWND)hWnd, (int)cmdShow);
}

// Check if quit was requested (by WndProc)
Bool php_win_quit_requested() {
    return g_quitRequested;
}

// Post quit message
void php_win_post_quit(Int exitCode) {
    PostQuitMessage((int)exitCode);
}

// PeekMessage wrapper - returns [hwnd, message, wParam, lParam] or empty array
Array php_win_peek_message() {
    MSG msg;
    ZeroMemory(&msg, sizeof(msg));
    if (PeekMessage(&msg, NULL, 0, 0, PM_REMOVE)) {
        Array result;
        result.append((Int)msg.hwnd);
        result.append((Int)msg.message);
        result.append((Int)msg.wParam);
        result.append((Int)msg.lParam);
        TranslateMessage(&msg);
        DispatchMessage(&msg);
        return result;
    }
    return Array();
}

// GetTickCount
Int php_win_get_tick_count() {
    return (Int)GetTickCount();
}

// MessageBox with UTF-8 support
Int php_win_message_box(Int hWnd, String text, String caption, Int uType) {
    int wtext_len = MultiByteToWideChar(CP_UTF8, 0, text.data(), -1, NULL, 0);
    wchar_t* wtext = new wchar_t[wtext_len];
    MultiByteToWideChar(CP_UTF8, 0, text.data(), -1, wtext, wtext_len);

    int wcaption_len = MultiByteToWideChar(CP_UTF8, 0, caption.data(), -1, NULL, 0);
    wchar_t* wcaption = new wchar_t[wcaption_len];
    MultiByteToWideChar(CP_UTF8, 0, caption.data(), -1, wcaption, wcaption_len);

    int result = MessageBoxW((HWND)hWnd, wtext, wcaption, (UINT)uType);

    delete[] wtext;
    delete[] wcaption;
    return result;
}

// ============================================================
// Win32 GDI Drawing Primitives
// ============================================================

// Begin a double-buffered frame. Returns memDC handle as int.
Int php_win_begin_paint(Int hWnd) {
    HDC hdc = GetDC((HWND)hWnd);
    RECT rc;
    GetClientRect((HWND)hWnd, &rc);

    HDC memDC = CreateCompatibleDC(hdc);
    HBITMAP memBitmap = CreateCompatibleBitmap(hdc, rc.right, rc.bottom);
    SelectObject(memDC, memBitmap);

    // Release the screen DC now - we only needed it to create compatible objects
    ReleaseDC((HWND)hWnd, hdc);

    return (Int)memDC;
}

// End the double-buffered frame: blit back-buffer to screen and cleanup.
void php_win_end_paint(Int hWnd, Int hdcHandle) {
    HDC memDC = (HDC)hdcHandle;
    RECT rc;
    GetClientRect((HWND)hWnd, &rc);

    // Blit memDC -> screen
    HDC hdc = GetDC((HWND)hWnd);
    BitBlt(hdc, 0, 0, rc.right, rc.bottom, memDC, 0, 0, SRCCOPY);
    ReleaseDC((HWND)hWnd, hdc);

    // Cleanup memDC
    DeleteDC(memDC);
}

// Fill rectangle with RGB color
void php_win_fill_rect(Int hdc, Int x, Int y, Int w, Int h, Int rgbColor) {
    HBRUSH brush = CreateSolidBrush((COLORREF)rgbColor);
    RECT r = {(int)x, (int)y, (int)(x + w), (int)(y + h)};
    FillRect((HDC)hdc, &r, brush);
    DeleteObject(brush);
}

// Draw a colored block with border
void php_win_draw_block(Int hdc, Int x, Int y, Int size, Int rgbColor) {
    COLORREF color = (COLORREF)rgbColor;
    // Fill interior
    HBRUSH brush = CreateSolidBrush(color);
    RECT r = {(int)x + 1, (int)y + 1, (int)(x + size - 1), (int)(y + size - 1)};
    FillRect((HDC)hdc, &r, brush);
    DeleteObject(brush);
    // Draw border
    HPEN borderPen = CreatePen(PS_SOLID, 1, RGB(
        (BYTE)(GetRValue(color) * 0.6),
        (BYTE)(GetGValue(color) * 0.6),
        (BYTE)(GetBValue(color) * 0.6)));
    HPEN oldPen = (HPEN)SelectObject((HDC)hdc, borderPen);
    HBRUSH oldBrush = (HBRUSH)SelectObject((HDC)hdc, GetStockObject(NULL_BRUSH));
    Rectangle((HDC)hdc, (int)x, (int)y, (int)(x + size), (int)(y + size));
    SelectObject((HDC)hdc, oldBrush);
    SelectObject((HDC)hdc, oldPen);
    DeleteObject(borderPen);
}

// Draw a line
void php_win_draw_line(Int hdc, Int x1, Int y1, Int x2, Int y2, Int rgbColor) {
    HPEN pen = CreatePen(PS_SOLID, 1, (COLORREF)rgbColor);
    HPEN oldPen = (HPEN)SelectObject((HDC)hdc, pen);
    MoveToEx((HDC)hdc, (int)x1, (int)y1, NULL);
    LineTo((HDC)hdc, (int)x2, (int)y2);
    SelectObject((HDC)hdc, oldPen);
    DeleteObject(pen);
}

// Draw text at position with given font size and color
void php_win_draw_text(Int hdc, Int x, Int y, String text, Int fontSize, Int rgbColor, Int bold) {
    SetTextColor((HDC)hdc, (COLORREF)rgbColor);
    SetBkMode((HDC)hdc, TRANSPARENT);
    HFONT hFont = CreateFont((int)fontSize, 0, 0, 0,
        bold ? FW_BOLD : FW_NORMAL, FALSE, FALSE, FALSE,
        DEFAULT_CHARSET, OUT_DEFAULT_PRECIS, CLIP_DEFAULT_PRECIS,
        DEFAULT_QUALITY, DEFAULT_PITCH | FF_SWISS, "Arial");
    HFONT oldFont = (HFONT)SelectObject((HDC)hdc, hFont);
    TextOutA((HDC)hdc, (int)x, (int)y, text.data(), (int)strlen(text.data()));
    SelectObject((HDC)hdc, oldFont);
    DeleteObject(hFont);
}

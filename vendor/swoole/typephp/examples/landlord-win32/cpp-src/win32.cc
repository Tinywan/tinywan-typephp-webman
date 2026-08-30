/**
 * Landlord Win32 API Layer
 *
 * C++ is intentionally limited to Win32 windowing and primitive drawing.
 * The game rules and AI live in PHP.
 */

#include <phpx.h>
#include <windows.h>
#include <cstdio>
#include <cstring>
#include <cwchar>

using namespace php;

static bool g_quitRequested = false;

struct PaintFrame {
    HDC memDC;
    HBITMAP bitmap;
    HBITMAP oldBitmap;
};

static HDC frame_hdc(Int frameHandle) {
    PaintFrame* frame = (PaintFrame*)frameHandle;
    return frame ? frame->memDC : NULL;
}

LRESULT CALLBACK LandlordWndProc(HWND hWnd, UINT msg, WPARAM wParam, LPARAM lParam) {
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

Int php_win_create_window(String title, Int width, Int height) {
    SetConsoleOutputCP(65001);

    WNDCLASSW wc;
    ZeroMemory(&wc, sizeof(wc));
    wc.style = CS_HREDRAW | CS_VREDRAW;
    wc.lpfnWndProc = LandlordWndProc;
    wc.hInstance = GetModuleHandle(NULL);
    wc.hCursor = LoadCursor(NULL, IDC_ARROW);
    wc.hbrBackground = (HBRUSH)(COLOR_WINDOW + 1);
    wc.lpszClassName = L"LandlordWindow";
    RegisterClassW(&wc);

    int wtitle_len = MultiByteToWideChar(CP_UTF8, 0, title.data(), -1, NULL, 0);
    wchar_t* wtitle = new wchar_t[wtitle_len];
    MultiByteToWideChar(CP_UTF8, 0, title.data(), -1, wtitle, wtitle_len);

    HWND hWnd = CreateWindowExW(
        0, L"LandlordWindow", wtitle,
        WS_OVERLAPPEDWINDOW & ~WS_THICKFRAME & ~WS_MAXIMIZEBOX,
        CW_USEDEFAULT, CW_USEDEFAULT,
        (int)width, (int)height,
        NULL, NULL, GetModuleHandle(NULL), NULL
    );
    delete[] wtitle;
    return (Int)hWnd;
}

void php_win_show_window(Int hWnd, Int cmdShow) {
    ShowWindow((HWND)hWnd, (int)cmdShow);
}

Bool php_win_quit_requested() {
    return g_quitRequested;
}

void php_win_post_quit(Int exitCode) {
    PostQuitMessage((int)exitCode);
}

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

Int php_win_get_tick_count() {
    return (Int)GetTickCount();
}

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

Int php_win_begin_paint(Int hWnd) {
    HDC hdc = GetDC((HWND)hWnd);
    RECT rc;
    GetClientRect((HWND)hWnd, &rc);

    HDC memDC = CreateCompatibleDC(hdc);
    HBITMAP memBitmap = CreateCompatibleBitmap(hdc, rc.right, rc.bottom);
    HBITMAP oldBitmap = (HBITMAP)SelectObject(memDC, memBitmap);

    ReleaseDC((HWND)hWnd, hdc);

    PaintFrame* frame = new PaintFrame;
    frame->memDC = memDC;
    frame->bitmap = memBitmap;
    frame->oldBitmap = oldBitmap;
    return (Int)frame;
}

void php_win_end_paint(Int hWnd, Int hdcHandle) {
    PaintFrame* frame = (PaintFrame*)hdcHandle;
    if (frame == NULL || frame->memDC == NULL) {
        return;
    }
    HDC memDC = frame->memDC;
    RECT rc;
    GetClientRect((HWND)hWnd, &rc);

    HDC hdc = GetDC((HWND)hWnd);
    BitBlt(hdc, 0, 0, rc.right, rc.bottom, memDC, 0, 0, SRCCOPY);
    ReleaseDC((HWND)hWnd, hdc);

    SelectObject(memDC, frame->oldBitmap);
    DeleteObject(frame->bitmap);
    DeleteDC(memDC);
    delete frame;
}

void php_win_fill_rect(Int hdc, Int x, Int y, Int w, Int h, Int rgbColor) {
    HDC targetDC = frame_hdc(hdc);
    HBRUSH brush = CreateSolidBrush((COLORREF)rgbColor);
    RECT r = {(int)x, (int)y, (int)(x + w), (int)(y + h)};
    FillRect(targetDC, &r, brush);
    DeleteObject(brush);
}

void php_win_fill_ellipse(Int hdc, Int x, Int y, Int w, Int h, Int rgbColor) {
    HDC targetDC = frame_hdc(hdc);
    HBRUSH brush = CreateSolidBrush((COLORREF)rgbColor);
    HBRUSH oldBrush = (HBRUSH)SelectObject(targetDC, brush);
    HPEN pen = CreatePen(PS_SOLID, 1, (COLORREF)rgbColor);
    HPEN oldPen = (HPEN)SelectObject(targetDC, pen);
    Ellipse(targetDC, (int)x, (int)y, (int)(x + w), (int)(y + h));
    SelectObject(targetDC, oldPen);
    SelectObject(targetDC, oldBrush);
    DeleteObject(pen);
    DeleteObject(brush);
}

void php_win_draw_line(Int hdc, Int x1, Int y1, Int x2, Int y2, Int rgbColor) {
    HDC targetDC = frame_hdc(hdc);
    HPEN pen = CreatePen(PS_SOLID, 1, (COLORREF)rgbColor);
    HPEN oldPen = (HPEN)SelectObject(targetDC, pen);
    MoveToEx(targetDC, (int)x1, (int)y1, NULL);
    LineTo(targetDC, (int)x2, (int)y2);
    SelectObject(targetDC, oldPen);
    DeleteObject(pen);
}

void php_win_draw_text(Int hdc, Int x, Int y, String text, Int fontSize, Int rgbColor, Int bold) {
    HDC targetDC = frame_hdc(hdc);
    SetTextColor(targetDC, (COLORREF)rgbColor);
    SetBkMode(targetDC, TRANSPARENT);

    int wtext_len = MultiByteToWideChar(CP_UTF8, 0, text.data(), -1, NULL, 0);
    wchar_t* wtext = new wchar_t[wtext_len];
    MultiByteToWideChar(CP_UTF8, 0, text.data(), -1, wtext, wtext_len);

    HFONT hFont = CreateFontW((int)fontSize, 0, 0, 0,
        bold ? FW_BOLD : FW_NORMAL, FALSE, FALSE, FALSE,
        DEFAULT_CHARSET, OUT_DEFAULT_PRECIS, CLIP_DEFAULT_PRECIS,
        DEFAULT_QUALITY, DEFAULT_PITCH | FF_SWISS, L"Microsoft YaHei UI");
    HFONT oldFont = (HFONT)SelectObject(targetDC, hFont);
    TextOutW(targetDC, (int)x, (int)y, wtext, (int)wcslen(wtext));
    SelectObject(targetDC, oldFont);
    DeleteObject(hFont);
    delete[] wtext;
}

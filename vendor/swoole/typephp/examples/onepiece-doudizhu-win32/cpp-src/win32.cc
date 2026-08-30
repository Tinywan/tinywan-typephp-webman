/**
 * One Piece Dou Dizhu - Win32 API Layer (TypePHP example)
 *
 * C++ only wraps Win32 windowing + GDI drawing primitives.
 * ALL game logic and ALL rendering decisions live in PHP
 * (see php-src/doudizhu/GameController.php via the WinDrawContext shim).
 */

#include <phpx.h>
#include <windows.h>
#include <cstdio>
#include <cstring>
#include <cwchar>
#include <map>

using namespace php;

// ============================================================
// Window & Message
// ============================================================

static bool g_quitRequested = false;

// Per memory-DC frame state: the off-screen bitmap plus the DC's previous
// bitmap (so we can restore it before deleting the off-screen bitmap).
struct FrameState {
    HBITMAP bitmap;
    HBITMAP oldBitmap;
};
static std::map<HDC, FrameState> g_frames;

// ------------------------------------------------------------------
// Anti-hook: some Chinese security suites (e.g. Tencent PC Manager's
// tsbx.dll) inline-hook the user32 exports (CreateWindowExW /
// SetWindowTextW) in every process, rewriting window titles to garbage.
// To get a reliable title we resolve the ORIGINAL exports from a freshly
// loaded copy of user32.dll and call those instead of the hooked IAT
// entries.  All other Win32 calls are unaffected and stay normal.
// ------------------------------------------------------------------

typedef HWND (WINAPI *RealCreateWindowExW)(DWORD, LPCWSTR, LPCWSTR, DWORD, int, int, int, int, HWND, HMENU, HINSTANCE, LPVOID);
typedef BOOL  (WINAPI *RealSetWindowTextW)(HWND, LPCWSTR);
typedef int   (WINAPI *RealGetWindowTextW)(HWND, LPWSTR, int);

static HMODULE g_realUser32 = NULL;
static RealCreateWindowExW g_realCreateWindowExW = NULL;
static RealSetWindowTextW   g_realSetWindowTextW   = NULL;
static RealGetWindowTextW   g_realGetWindowTextW   = NULL;

static void resolve_real_user32(void) {
    if (g_realCreateWindowExW != NULL) {
        return;
    }
    g_realUser32 = LoadLibraryW(L"C:\\Windows\\System32\\user32.dll");
    if (g_realUser32 == NULL) {
        g_realUser32 = GetModuleHandleW(L"user32.dll");
    }
    if (g_realUser32 != NULL) {
        g_realCreateWindowExW = (RealCreateWindowExW)GetProcAddress(g_realUser32, "CreateWindowExW");
        g_realSetWindowTextW   = (RealSetWindowTextW)GetProcAddress(g_realUser32, "SetWindowTextW");
        g_realGetWindowTextW   = (RealGetWindowTextW)GetProcAddress(g_realUser32, "GetWindowTextW");
    }
    // Fallbacks to the (possibly hooked) IAT entries if resolution failed.
    if (g_realCreateWindowExW == NULL) g_realCreateWindowExW = CreateWindowExW;
    if (g_realSetWindowTextW   == NULL) g_realSetWindowTextW   = SetWindowTextW;
    if (g_realGetWindowTextW   == NULL) g_realGetWindowTextW   = GetWindowTextW;
}

LRESULT CALLBACK DdzWndProc(HWND hWnd, UINT msg, WPARAM wParam, LPARAM lParam) {
    switch (msg) {
        case WM_CLOSE:
        case WM_DESTROY:
            g_quitRequested = true;
            PostQuitMessage(0);
            return 0;
        case WM_PAINT:
            // We render the whole client area every frame via GetDC, so just
            // validate the paint region to avoid an endless WM_PAINT loop.
            ValidateRect(hWnd, NULL);
            return 0;
    }
    // NOTE: must call DefWindowProcW (not the DefWindowProc macro). On this
    // machine Tencent PC Manager (tsbx.dll) inline-hooks DefWindowProcA and
    // corrupts UTF-16 window titles (they come back as garbage). The W
    // variant is not hooked.
    return DefWindowProcW(hWnd, msg, wParam, lParam);
}

Int php_win_create_window(String title, Int width, Int height) {
    SetConsoleOutputCP(65001);
    resolve_real_user32();

    WNDCLASSW wc;
    ZeroMemory(&wc, sizeof(wc));
    wc.style = CS_HREDRAW | CS_VREDRAW;
    wc.lpfnWndProc = DdzWndProc;
    wc.hInstance = GetModuleHandle(NULL);
    wc.hCursor = LoadCursor(NULL, IDC_ARROW);
    wc.hbrBackground = (HBRUSH)(COLOR_WINDOW + 1);
    wc.lpszClassName = L"DdzWindow";
    RegisterClassW(&wc);

    // UTF-8 (PHP string) -> UTF-16 window title.
    int wtitle_len = MultiByteToWideChar(CP_UTF8, 0, title.data(), -1, NULL, 0);
    wchar_t* wtitle = new wchar_t[wtitle_len];
    MultiByteToWideChar(CP_UTF8, 0, title.data(), -1, wtitle, wtitle_len);

    // The requested width/height are treated as the CLIENT area, so every
    // control the PHP layout draws is fully visible. The outer window frame
    // (title bar + borders) is added on top via AdjustWindowRect.
    RECT rc = {0, 0, (int)width, (int)height};
    DWORD style = WS_OVERLAPPEDWINDOW;  // resizable + maximizable
    AdjustWindowRect(&rc, style, FALSE);
    int winW = rc.right - rc.left;
    int winH = rc.bottom - rc.top;

    // Use the ORIGINAL (un-hooked) CreateWindowExW so security-suite hooks
    // cannot corrupt the window title. Fall back to the normal API if the
    // real one could not be resolved.
    HWND hWnd = g_realCreateWindowExW(
        0, L"DdzWindow", wtitle,
        style,
        CW_USEDEFAULT, CW_USEDEFAULT,
        winW, winH,
        NULL, NULL, GetModuleHandle(NULL), NULL
    );

    // Belt & braces: re-assert the title through the original SetWindowTextW
    // (some hooks corrupt the title during CreateWindowExW itself).
    if (hWnd != NULL && g_realSetWindowTextW != NULL) {
        g_realSetWindowTextW(hWnd, wtitle);
    }

    delete[] wtitle;
    return (Int)hWnd;
}

/** Return the current client-area size as [width, height]. */
Array php_win_get_client_size(Int hWnd) {
    RECT rc;
    GetClientRect((HWND)hWnd, &rc);
    Array result;
    result.append((Int)rc.right);
    result.append((Int)rc.bottom);
    return result;
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

/**
 * Drain one queued message and translate it into a typed array:
 *   [type, a, b, c]
 *   type 0 = unhandled (still dispatched, ignored by PHP)
 *   type 1 = mouse down   : a=x, b=y
 *   type 2 = mouse up     : a=x, b=y
 *   type 3 = mouse move   : a=x, b=y, c=leftHeld(0/1)
 *   type 4 = key down     : a=vk
 * Returns empty array when no message is pending.
 */
Array php_win_peek_message() {
    MSG msg;
    ZeroMemory(&msg, sizeof(msg));
    if (PeekMessage(&msg, NULL, 0, 0, PM_REMOVE)) {
        Array result;
        int type = 0;
        int a = 0, b = 0, c = 0;
        switch ((UINT)msg.message) {
            case WM_LBUTTONDOWN:
                type = 1;
                a = (int)(msg.lParam & 0xFFFF);
                b = (int)((msg.lParam >> 16) & 0xFFFF);
                break;
            case WM_LBUTTONUP:
                type = 2;
                a = (int)(msg.lParam & 0xFFFF);
                b = (int)((msg.lParam >> 16) & 0xFFFF);
                break;
            case WM_MOUSEMOVE:
                type = 3;
                a = (int)(msg.lParam & 0xFFFF);
                b = (int)((msg.lParam >> 16) & 0xFFFF);
                c = (msg.wParam & MK_LBUTTON) ? 1 : 0;
                break;
            case WM_KEYDOWN:
                type = 4;
                a = (int)msg.wParam;
                break;
            default:
                type = 0;
                a = (int)msg.message;
                b = (int)msg.wParam;
                c = (int)msg.lParam;
                break;
        }
        TranslateMessage(&msg);
        DispatchMessage(&msg);
        result.append((Int)type);
        result.append((Int)a);
        result.append((Int)b);
        result.append((Int)c);
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

void php_win_message_beep(Int type) {
    MessageBeep((UINT)type);
}

// ============================================================
// Double-buffered frame
// ============================================================

// Begin a frame. Returns the memory-DC handle (an Int) used by all draw calls.
Int php_win_begin_paint(Int hWnd) {
    HDC hdc = GetDC((HWND)hWnd);
    RECT rc;
    GetClientRect((HWND)hWnd, &rc);

    HDC memDC = CreateCompatibleDC(hdc);
    HBITMAP memBitmap = CreateCompatibleBitmap(hdc, rc.right, rc.bottom);
    HBITMAP oldBitmap = (HBITMAP)SelectObject(memDC, memBitmap);
    FrameState state;
    state.bitmap = memBitmap;
    state.oldBitmap = oldBitmap;
    g_frames[memDC] = state;

    ReleaseDC((HWND)hWnd, hdc);
    return (Int)memDC;
}

void php_win_end_paint(Int hWnd, Int hdcHandle) {
    HDC memDC = (HDC)hdcHandle;
    RECT rc;
    GetClientRect((HWND)hWnd, &rc);

    HDC hdc = GetDC((HWND)hWnd);
    BitBlt(hdc, 0, 0, rc.right, rc.bottom, memDC, 0, 0, SRCCOPY);
    ReleaseDC((HWND)hWnd, hdc);

    auto it = g_frames.find(memDC);
    if (it != g_frames.end()) {
        SelectObject(memDC, it->second.oldBitmap);
        DeleteObject(it->second.bitmap);
        g_frames.erase(it);
    }
    DeleteDC(memDC);
}

// ============================================================
// GDI primitives
// ============================================================

void php_win_fill_rect(Int hdc, Int x, Int y, Int w, Int h, Int rgbColor) {
    HBRUSH brush = CreateSolidBrush((COLORREF)rgbColor);
    RECT r = {(int)x, (int)y, (int)(x + w), (int)(y + h)};
    FillRect((HDC)hdc, &r, brush);
    DeleteObject(brush);
}

void php_win_draw_block(Int hdc, Int x, Int y, Int size, Int rgbColor) {
    COLORREF color = (COLORREF)rgbColor;
    HBRUSH brush = CreateSolidBrush(color);
    RECT r = {(int)x + 1, (int)y + 1, (int)(x + size - 1), (int)(y + size - 1)};
    FillRect((HDC)hdc, &r, brush);
    DeleteObject(brush);
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

void php_win_draw_line(Int hdc, Int x1, Int y1, Int x2, Int y2, Int rgbColor) {
    HPEN pen = CreatePen(PS_SOLID, 1, (COLORREF)rgbColor);
    HPEN oldPen = (HPEN)SelectObject((HDC)hdc, pen);
    MoveToEx((HDC)hdc, (int)x1, (int)y1, NULL);
    LineTo((HDC)hdc, (int)x2, (int)y2);
    SelectObject((HDC)hdc, oldPen);
    DeleteObject(pen);
}

// Ellipse using CENTER coordinate semantics (matches libui's fillEllipse).
void php_win_fill_ellipse(Int hdc, Int cx, Int cy, Int w, Int h, Int rgbColor) {
    HBRUSH brush = CreateSolidBrush((COLORREF)rgbColor);
    HBRUSH oldBrush = (HBRUSH)SelectObject((HDC)hdc, brush);
    HPEN pen = CreatePen(PS_SOLID, 1, (COLORREF)rgbColor);
    HPEN oldPen = (HPEN)SelectObject((HDC)hdc, pen);
    Ellipse((HDC)hdc,
        (int)(cx - w / 2), (int)(cy - h / 2),
        (int)(cx + w / 2), (int)(cy + h / 2));
    SelectObject((HDC)hdc, oldPen);
    SelectObject((HDC)hdc, oldBrush);
    DeleteObject(pen);
    DeleteObject(brush);
}

void php_win_fill_rounded_rect(Int hdc, Int x, Int y, Int w, Int h, Int radius, Int rgbColor) {
    HBRUSH brush = CreateSolidBrush((COLORREF)rgbColor);
    HPEN nullPen = (HPEN)GetStockObject(NULL_PEN);
    HBRUSH oldBrush = (HBRUSH)SelectObject((HDC)hdc, brush);
    HPEN oldPen = (HPEN)SelectObject((HDC)hdc, nullPen);
    RoundRect((HDC)hdc, (int)x, (int)y, (int)(x + w), (int)(y + h),
        (int)radius * 2, (int)radius * 2);
    SelectObject((HDC)hdc, oldPen);
    SelectObject((HDC)hdc, oldBrush);
    DeleteObject(brush);
}

void php_win_stroke_rounded_rect(Int hdc, Int x, Int y, Int w, Int h, Int radius, Int rgbColor, Int thickness) {
    HPEN pen = CreatePen(PS_SOLID, (int)thickness, (COLORREF)rgbColor);
    HBRUSH nullBrush = (HBRUSH)GetStockObject(NULL_BRUSH);
    HPEN oldPen = (HPEN)SelectObject((HDC)hdc, pen);
    HBRUSH oldBrush = (HBRUSH)SelectObject((HDC)hdc, nullBrush);
    RoundRect((HDC)hdc, (int)x, (int)y, (int)(x + w), (int)(y + h),
        (int)radius * 2, (int)radius * 2);
    SelectObject((HDC)hdc, oldBrush);
    SelectObject((HDC)hdc, oldPen);
    DeleteObject(pen);
}

// Plain ASCII text (kept for parity / simple labels).
void php_win_draw_text(Int hdc, Int x, Int y, String text, Int fontSize, Int rgbColor, Int bold) {
    SetTextColor((HDC)hdc, (COLORREF)rgbColor);
    SetBkMode((HDC)hdc, TRANSPARENT);
    HFONT hFont = CreateFontA((int)fontSize, 0, 0, 0,
        bold ? FW_BOLD : FW_NORMAL, FALSE, FALSE, FALSE,
        DEFAULT_CHARSET, OUT_DEFAULT_PRECIS, CLIP_DEFAULT_PRECIS,
        DEFAULT_QUALITY, DEFAULT_PITCH | FF_SWISS, "Arial");
    HFONT oldFont = (HFONT)SelectObject((HDC)hdc, hFont);
    TextOutA((HDC)hdc, (int)x, (int)y, text.data(), (int)strlen(text.data()));
    SelectObject((HDC)hdc, oldFont);
    DeleteObject(hFont);
}

// UTF-8 text with left/center/right alignment inside an optional width box.
//   align: 0 = left, 1 = center, 2 = right
void php_win_draw_text_ex(Int hdc, Int x, Int y, String text, Int fontSize, Int rgbColor, Int bold, Int width, Int align) {
    SetTextColor((HDC)hdc, (COLORREF)rgbColor);
    SetBkMode((HDC)hdc, TRANSPARENT);

    int wtext_len = MultiByteToWideChar(CP_UTF8, 0, text.data(), -1, NULL, 0);
    wchar_t* wtext = new wchar_t[wtext_len];
    MultiByteToWideChar(CP_UTF8, 0, text.data(), -1, wtext, wtext_len);

    HFONT hFont = CreateFontW((int)fontSize, 0, 0, 0,
        bold ? FW_BOLD : FW_NORMAL, FALSE, FALSE, FALSE,
        DEFAULT_CHARSET, OUT_DEFAULT_PRECIS, CLIP_DEFAULT_PRECIS,
        DEFAULT_QUALITY, DEFAULT_PITCH | FF_SWISS, L"Microsoft YaHei");
    HFONT oldFont = (HFONT)SelectObject((HDC)hdc, hFont);

    int drawX = (int)x;
    if (width > 0) {
        SIZE sz;
        GetTextExtentPoint32W((HDC)hdc, wtext, (int)wcslen(wtext), &sz);
        if (align == 1) {
            drawX = (int)x + ((int)width - sz.cx) / 2;
        } else if (align == 2) {
            drawX = (int)x + (int)width - sz.cx;
        }
    }
    TextOutW((HDC)hdc, drawX, (int)y, wtext, (int)wcslen(wtext));

    SelectObject((HDC)hdc, oldFont);
    DeleteObject(hFont);
    delete[] wtext;
}

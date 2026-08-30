#include <phpx.h>
#include <windows.h>

using namespace php;

/**
 * Windows API wrapper functions
 * Note: Function names must be prefixed with php_ to be callable from PHP
 */

// Show message box (with UTF-8 support)
Int php_messagebox(Int hWnd, String text, String caption, Int uType) {
    // Convert UTF-8 to UTF-16 for Windows API
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

// Get module handle
Int php_get_module_handle(String moduleName) {
    HMODULE hModule = GetModuleHandle(moduleName.length() == 0 ? NULL : moduleName.data());
    return (Int)hModule;
}

// Create window (simplified version)
Int php_create_window(String className, String windowName, Int style, Int x, Int y, Int width, Int height) {
    HWND hWnd = CreateWindowEx(
        0,                                  // extended style
        className.data(),                   // class name
        windowName.data(),                  // window title
        (DWORD)style,                       // window style
        (int)x, (int)y,                     // position
        (int)width, (int)height,            // size
        NULL,                               // parent window
        NULL,                               // menu
        GetModuleHandle(NULL),              // instance handle
        NULL                                // extra parameters
    );
    return (Int)hWnd;
}

// Show window
Bool php_show_window(Int hWnd, Int cmdShow) {
    return ShowWindow((HWND)hWnd, cmdShow);
}

// Update window
Bool php_update_window(Int hWnd) {
    return UpdateWindow((HWND)hWnd);
}

// Exit message loop
void php_post_quit_message(Int exitCode) {
    PostQuitMessage((int)exitCode);
}

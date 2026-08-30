<?php

/**
 * Win32 Complete Window Example
 * Create a real Windows window with message loop
 */

// Windows constant definitions
define('WS_OVERLAPPEDWINDOW', 0x00CF0000);
define('CW_USEDEFAULT', 0x80000000);
define('SW_SHOW', 5);
define('WM_DESTROY', 0x0002);
define('MB_OK', 0x00000000);

#[NativeFunction]
function RegisterClass(array $lpWndClass): int {}

#[NativeFunction]
function CreateWindowEx(
    int $dwExStyle,
    string $lpClassName,
    string $lpWindowName,
    int $dwStyle,
    int $x,
    int $y,
    int $nWidth,
    int $nHeight,
    int $hWndParent,
    int $hMenu,
    int $hInstance,
    int $lpParam
): int {}

#[NativeFunction]
function ShowWindow(int $hWnd, int $nCmdShow): bool {}

#[NativeFunction]
function UpdateWindow(int $hWnd): bool {}

#[NativeFunction]
function GetMessage(array &$lpMsg, int $hWnd, int $wMsgFilterMin, int $wMsgFilterMax): int {}

#[NativeFunction]
function TranslateMessage(array $lpMsg): int {}

#[NativeFunction]
function DispatchMessage(array $lpMsg): int {}

#[NativeFunction]
function DefWindowProc(int $hWnd, int $Msg, int $wParam, int $lParam): int {}

#[NativeFunction]
function PostQuitMessage(int $nExitCode): void {}

#[NativeFunction]
function MessageBox(int $hWnd, string $lpText, string $lpCaption, int $uType): int {}

#[NativeFunction]
function GetModuleHandle(string $lpModuleName): int {}

// Window procedure function (simplified version, actually needs C++ implementation)
function WindowProc(int $hWnd, int $Msg, int $wParam, int $lParam): int
{
    if ($Msg === WM_DESTROY) {
        PostQuitMessage(0);
        return 0;
    }
    
    return DefWindowProc($hWnd, $Msg, $wParam, $lParam);
}

function main()
{
    echo "Win32 Hello World Program starting...\n";
    
    // Show message box (simplest way)
    $result = MessageBox(
        0, 
        "Hello from PHP Compiler!\n\nWelcome to use PHPX compiler to create Windows applications.", 
        "Hello World", 
        MB_OK
    );
    
    echo "Message box closed, return value: " . $result . "\n";
    echo "Program exited normally.\n";
}

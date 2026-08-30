<?php

/**
 * Win32 Hello World - GUI Version (No Console)
 * This example demonstrates a pure GUI application without console window
 * 
 * Compile with: php ../../cli.php build --mode=bin --no-console hello-gui.php
 */

function main()
{
    // Set timezone to China (UTC+8)
    date_default_timezone_set('Asia/Shanghai');
    
    // Show welcome message box
    messagebox(0, 
        "欢迎使用 PHP Compiler！\n\n" .
        "这是一个纯图形界面的 Windows 程序。\n" .
        "没有控制台窗口，只显示 GUI。\n\n" .
        "当前时间: " . date('Y-m-d H:i:s'), 
        "Win32 Hello World", 
        0);
    
    // Show goodbye message
    messagebox(0, 
        "程序即将退出。\n\n" .
        "感谢使用 PHPX 编译器！", 
        "再见", 
        0);
}

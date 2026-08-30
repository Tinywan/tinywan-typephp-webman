<?php

/**
 * Win32 Hello World - Console Version (With Console)
 * This example demonstrates a console application with message boxes
 * 
 * Compile with: php ../../cli.php build --mode=bin hello-console.php
 */

function main()
{
    // Set timezone to China (UTC+8)
    date_default_timezone_set('Asia/Shanghai');
    
    echo "========================================\n";
    echo "  Win32 Hello World 程序（控制台版）\n";
    echo "========================================\n\n";
    
    echo "当前时间: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Show message box
    echo "显示消息框...\n";
    $result = messagebox(0, 
        "Hello from PHP Compiler!\n\n" .
        "这是一个使用 PHPX 编译器创建的 Windows 程序。\n\n" .
        "当前时间: " . date('Y-m-d H:i:s'), 
        "Hello World", 
        0);
    
    echo "消息框返回值: " . $result . "\n\n";
    
    echo "程序结束。按任意键退出...\n";
}

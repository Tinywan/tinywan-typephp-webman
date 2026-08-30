# Windows 中文支持指南

## 问题

在 Windows 控制台显示中文时会出现乱码：
```
Win32 Hello World 绋嬪簭
鏄剧ず娑堟伅妗?..
```

## 原因

1. **Windows 控制台默认编码**：GBK（代码页 936）
2. **源文件编码**：UTF-8
3. **编码不匹配**：导致乱码

## 解决方案

### ✅ 方案 1：程序内设置 UTF-8（推荐）

在 PHP 代码开始时设置控制台为 UTF-8：

```php
function main()
{
    // Set console to UTF-8 for proper Chinese character display
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('chcp 65001 > nul 2>&1');
    }
    
    echo "显示中文消息...\n";
}
```

**优点：**
- ✅ 自动设置，用户无需手动操作
- ✅ 跨平台兼容（只在 Windows 上执行）
- ✅ 简单可靠

### 方案 2：手动设置控制台

运行程序前手动设置：

```powershell
chcp 65001
.\win32_hello.exe
```

**缺点：**
- ❌ 每次运行都需要手动设置
- ❌ 用户体验不好

### 方案 3：C++ 层使用宽字符 API

对于 Windows API（如 MessageBox），使用宽字符版本：

```cpp
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
```

**关键点：**
- 使用 `MultiByteToWideChar` 将 UTF-8 转换为 UTF-16
- 使用 `MessageBoxW`（宽字符版本）而不是 `MessageBoxA`
- 记得释放内存（`delete[]`）

## 完整示例

### PHP 层（hello-win.php）

```php
<?php

function main()
{
    // 设置控制台为 UTF-8
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('chcp 65001 > nul 2>&1');
    }
    
    // 设置时区为中国（UTC+8）
    date_default_timezone_set('Asia/Shanghai');
    
    echo "========================================\n";
    echo "  Win32 Hello World 程序\n";
    echo "========================================\n\n";
    
    echo "显示消息框...\n";
    $result = messagebox(0, 
        "Hello from PHP Compiler!\n\n" .
        "这是一个使用 PHPX 编译器创建的 Windows 程序。\n\n" .
        "当前时间: " . date('Y-m-d H:i:s'), 
        "Hello World", 0);
    echo "消息框返回值: " . $result . "\n\n";
    
    echo "程序结束。按任意键退出...\n";
}
```

### C++ 层（winapi.cc）

```cpp
#include <phpx.h>
#include <windows.h>

using namespace php;

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
```

## 编译和测试

```powershell
# 清理并重新编译
Remove-Item -Recurse -Force build
php bin\compiler.php examples\win32-hello\project.yml

# 运行（会自动设置 UTF-8）
.\build\win32_hello.exe
```

## 预期输出

```
========================================
  Win32 Hello World 程序
========================================

显示消息框...
[消息框正确显示中文]
消息框返回值: 1

提示：要创建完整窗口，需要实现窗口过程函数和消息循环。
这需要在 C++ 层实现 WNDCLASS 注册和消息泵。

程序结束。按任意键退出...
```

## 常见问题

### Q1: 为什么消息框还需要特殊处理？

**A:** Windows API 的 `MessageBoxA` 使用 ANSI 编码（GBK），而 `MessageBoxW` 使用 Unicode（UTF-16）。我们的字符串是 UTF-8，所以需要转换。

### Q2: 可以不转换直接用吗？

**A:** 不可以。直接使用会导致：
- 控制台输出：乱码
- 消息框：乱码或空白

### Q3: 其他 Windows API 也需要转换吗？

**A:** 是的，所有接受字符串的 Windows API 都应该使用宽字符版本（带 W 后缀）：
- `CreateWindowExW` 而不是 `CreateWindowExA`
- `SetWindowTextW` 而不是 `SetWindowTextA`
- 等等...

### Q4: Linux/macOS 需要这样处理吗？

**A:** 不需要。Linux/macOS 原生支持 UTF-8，可以直接使用。

## 最佳实践

1. ✅ **始终在程序开始时设置 UTF-8**
2. ✅ **设置正确的时区**（`date_default_timezone_set('Asia/Shanghai')`）
3. ✅ **Windows API 使用宽字符版本**（W 后缀）
4. ✅ **UTF-8 ↔ UTF-16 转换后记得释放内存**
5. ✅ **源文件保存为 UTF-8 编码（无 BOM）**
6. ❌ **避免混用 ANSI 和 Unicode API**

## 参考资源

- [Windows Unicode Documentation](https://docs.microsoft.com/en-us/windows/win32/intl/unicode-in-the-windows-api)
- [MultiByteToWideChar Function](https://docs.microsoft.com/en-us/windows/win32/api/stringapiset/nf-stringapiset-multibytetowidechar)
- [Code Pages](https://docs.microsoft.com/en-us/windows/win32/intl/code-pages)

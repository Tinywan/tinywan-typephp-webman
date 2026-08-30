# Windows GUI 程序指南

## 概述

本示例展示了如何使用 PHPX 编译器创建纯图形界面的 Windows 程序，不显示控制台窗口。

## 关键特性

### 1. 隐藏控制台窗口

通过添加 `--no-console` 编译参数，程序将以 Windows 子系统模式运行，不会显示黑色的控制台终端窗口。

**使用方法：**
```bash
php cli.php build --mode=bin --no-console your-app.php
```

**实现位置：** 
- 选项定义：`src/Php/Constants.php`
- 参数读取：`src/Php/Translator.php`
- 链接配置：`src/Php/CompilerBase.php`

```php
// 对于 bin 模式且指定了 --no-console，使用 Windows 子系统（不显示控制台窗口）
if ($this->buildMode === 'bin' && $this->noConsole) {
    $cmd .= ' /SUBSYSTEM:WINDOWS';
}
```

### 2. 中文支持

#### C++ 层（消息框）

在 C++ 中使用 `MultiByteToWideChar` 将 UTF-8 转换为 UTF-16，然后调用 `MessageBoxW`：

```cpp
// Convert UTF-8 to UTF-16 for Windows API
int wtext_len = MultiByteToWideChar(CP_UTF8, 0, text.data(), -1, NULL, 0);
wchar_t* wtext = new wchar_t[wtext_len];
MultiByteToWideChar(CP_UTF8, 0, text.data(), -1, wtext, wtext_len);

int result = MessageBoxW((HWND)hWnd, wtext, wcaption, (UINT)uType);

delete[] wtext;
delete[] wcaption;
```

#### PHP 层（时区设置）

```php
// Set timezone to China (UTC+8)
date_default_timezone_set('Asia/Shanghai');
```

### 3. 输出方式

由于没有控制台窗口，所有输出必须通过 GUI 元素显示：

- **消息框**：使用 `messagebox()` 或 `MessageBox()` 函数
- **自定义窗口**：需要实现完整的窗口类和消息循环
- **日志文件**：可以写入文件进行调试

## 编译和运行

### 带控制台窗口（默认）

```bash
# 进入示例目录
cd examples/win32-hello

# 编译为带控制台的程序（默认）
php ../../cli.php build --mode=bin hello-win.php

# 运行程序（显示控制台和消息框）
.\hello-win.exe
```

### 不带控制台窗口（GUI 模式）

```bash
# 使用 --no-console 参数编译纯 GUI 程序
php ../../cli.php build --mode=bin --no-console hello-win.php

# 运行程序（只显示消息框，无控制台）
.\hello-win.exe
```

## 注意事项

### ⚠️ 重要限制

1. **没有标准输入输出**
   - `echo`、`print`、`printf` 等控制台输出函数无效
   - `stdin`、`stdout`、`stderr` 不可用
   - 所有输出必须通过 GUI 元素

2. **调试困难**
   - 无法在控制台查看调试信息
   - 建议使用：
     - 消息框显示调试信息
     - 写入日志文件
     - 使用 Visual Studio 调试器附加进程

3. **入口点要求**
   - 使用 `/SUBSYSTEM:WINDOWS` 后，默认入口点是 `WinMain` 而不是 `main`
   - PHPX 编译器会自动处理这个转换

### ✅ 最佳实践

1. **使用消息框进行用户交互**
   ```php
   messagebox(0, "操作成功！", "提示", 0);
   ```

2. **错误处理**
   ```php
   try {
       // 你的代码
   } catch (\Exception $e) {
       messagebox(0, "错误: " . $e->getMessage(), "错误", 16); // MB_ICONERROR
   }
   ```

3. **日志记录**
   ```php
   file_put_contents('app.log', date('Y-m-d H:i:s') . ": 消息\n", FILE_APPEND);
   ```

## 两种模式对比

| 特性 | 控制台模式 (默认) | 窗口模式 (`--no-console`) |
|------|------------------|-------------------------|
| 编译命令 | `php cli.php build app.php` | `php cli.php build --no-console app.php` |
| 链接选项 | `/SUBSYSTEM:CONSOLE` | `/SUBSYSTEM:WINDOWS` |
| 控制台窗口 | ✅ 显示 | ❌ 隐藏 |
| echo/print | ✅ 可用 | ❌ 不可用 |
| stdin/stdout | ✅ 可用 | ❌ 不可用 |
| 消息框 | ✅ 可用 | ✅ 可用 |
| 适用场景 | 命令行工具、调试 | GUI 应用程序 |
| 用户体验 | 黑色终端窗口 | 纯图形界面 |

## 切换模式

### 开发时（带控制台，便于调试）

```bash
# 默认编译，显示控制台
php cli.php build --mode=bin app.php
```

### 发布时（不带控制台，纯 GUI）

```bash
# 使用 --no-console 参数
php cli.php build --mode=bin --no-console app.php
```

### 在 project.yml 中配置

```yaml
build:
  mode: bin
  no_console: true  # 隐藏控制台窗口
```

## 常见问题

### Q: 为什么我的 echo 不显示？

A: 因为使用了 `/SUBSYSTEM:WINDOWS`，控制台被隐藏了。请改用消息框或其他 GUI 元素。

### Q: 如何调试没有控制台的程序？

A: 
1. 使用消息框显示变量值
2. 写入日志文件
3. 使用 Visual Studio 的"附加到进程"功能
4. 临时切换回控制台模式进行调试

### Q: 可以同时显示控制台和窗口吗？

A: 技术上可以，但不推荐。通常的做法是：
- 开发时使用控制台模式便于调试
- 发布时使用窗口模式提供更好的用户体验

### Q: 中文显示乱码怎么办？

A: 确保：
1. C++ 文件使用 UTF-8 编码保存
2. 使用 `MultiByteToWideChar` 转换编码
3. 使用 `MessageBoxW` 而不是 `MessageBoxA`
4. 在 PHP 中设置正确的时区

## 示例代码

### 简单的消息框程序

```php
<?php

function main()
{
    date_default_timezone_set('Asia/Shanghai');
    
    // 欢迎消息
    messagebox(0, 
        "欢迎使用！\n\n" .
        "当前时间: " . date('Y-m-d H:i:s'), 
        "Hello", 
        0);
}
```

### 带错误处理的程序

```php
<?php

function main()
{
    date_default_timezone_set('Asia/Shanghai');
    
    try {
        // 你的业务逻辑
        $result = someOperation();
        
        messagebox(0, "操作成功！结果: " . $result, "成功", 64); // MB_ICONINFORMATION
    } catch (\Exception $e) {
        messagebox(0, "发生错误:\n" . $e->getMessage(), "错误", 16); // MB_ICONERROR
    }
}
```

## 相关资源

- [Windows Subsystem 文档](https://docs.microsoft.com/en-us/cpp/build/reference/subsystem-specify-subsystem)
- [MessageBoxW API](https://docs.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-messageboxw)
- [MultiByteToWideChar](https://docs.microsoft.com/en-us/windows/win32/api/stringapiset/nf-stringapiset-multibytetowidechar)

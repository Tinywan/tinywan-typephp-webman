# Win32 Hello World 示例

这个示例展示了如何使用 PHPX 编译器创建 Windows 程序，包括两种模式：

## 📁 示例文件

### 1. hello-console.php - 控制台版本（默认）

**特点：**
- ✅ 显示黑色控制台窗口
- ✅ 可以使用 `echo`、`print` 等控制台输出
- ✅ 可以显示消息框
- ✅ 适合命令行工具和调试

**编译命令：**
```bash
php ../../cli.php build --mode=bin hello-console.php
```

**运行效果：**
- 显示控制台窗口
- 输出文本信息到控制台
- 弹出消息框

---

### 2. hello-gui.php - GUI 版本（无控制台）

**特点：**
- ❌ 不显示控制台窗口
- ❌ `echo`、`print` 等控制台输出无效
- ✅ 只显示消息框等 GUI 元素
- ✅ 适合纯图形界面应用程序

**编译命令：**
```bash
php ../../cli.php build --mode=bin --no-console hello-gui.php
```

**运行效果：**
- 没有控制台窗口
- 直接弹出消息框
- 纯图形界面体验

---

### 3. debug-test.php - 调试测试程序

**特点：**
- ✅ 包含详细的日志记录
- ✅ 异常处理
- ✅ 逐步验证功能
- ✅ 用于排查 "Debug Assertion Failed" 问题

**编译命令：**
```bash
php ../../cli.php build --mode=bin --no-console debug-test.php
```

**使用方法：**
1. 编译并运行程序
2. 查看生成的 `debug-test.log` 文件
3. 根据日志定位问题

---

### 4. asan-test.php - AddressSanitizer 测试程序

**特点：**
- ✅ 演示 AddressSanitizer 的使用
- ✅ 包含各种内存操作测试
- ✅ 检测内存错误和泄漏

**编译命令：**
```bash
# Windows
php ../../cli.php build --mode=bin --sanitize=address --no-console asan-test.php

# Linux/macOS
php ../../cli.php build --mode=bin --sanitize=address,undefined asan-test.php
```

**使用方法：**
1. 编译带 sanitizer 的版本
2. 运行程序
3. 查看 `asan-test.log` 和控制台输出
4. 如果有内存错误，AddressSanitizer 会报告详细信息

---

### 5. debug-mode-test.php - 调试模式测试程序

**特点：**
- ✅ 演示 `--debug-info` 参数的使用
- ✅ 禁用优化，便于调试
- ✅ 生成完整的调试信息
- ✅ 支持 GDB、LLDB、Visual Studio 调试

**编译命令：**
```bash
# 启用调试模式（自动禁用优化 + 生成调试信息）
php ../../cli.php build --mode=bin --debug-info --no-console debug-mode-test.php

# 结合 AddressSanitizer
php ../../cli.php build --mode=bin --debug-info --sanitize=address --no-console debug-mode-test.php
```

**使用方法：**
1. 编译带调试信息的版本
2. 使用调试器加载程序
3. 设置断点、查看变量、单步执行
4. 查看 `debug-mode-test.log` 了解运行流程

---

## 🔧 核心功能

### C++ 函数导出

本示例展示了如何将 C++ 函数导出为 PHP 函数：

1. **函数命名规范**：必须以 `php_` 为前缀
2. **类型要求**：只能使用 PHPX 类型（Int, String, Bool 等）
3. **Stub 声明**：必须在 `.stub.php` 文件中声明函数签名

**示例：**

C++ 实现 (`cpp-src/winapi.cc`)：
```cpp
Int php_messagebox(Int hWnd, String text, String caption, Int uType) {
    // 实现代码
}
```

PHP Stub 声明 (`cpp-src/winapi.stub.php`)：
```php
function messagebox(int $hWnd, string $text, string $caption, int $uType): int {}
```

### 中文支持

#### C++ 层（消息框）

使用 `MultiByteToWideChar` 将 UTF-8 转换为 UTF-16：

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

---

## 📖 详细文档

- [WINDOWS_GUI_GUIDE.md](./WINDOWS_GUI_GUIDE.md) - Windows GUI 程序完整指南
- [CHINESE_SUPPORT_GUIDE.md](./CHINESE_SUPPORT_GUIDE.md) - 中文支持详细说明
- [CPP_FUNCTION_EXPORT_GUIDE.md](./CPP_FUNCTION_EXPORT_GUIDE.md) - C++ 函数导出规范

---

## 🚀 快速开始

### 编译控制台版本

```bash
# 进入示例目录
cd examples/win32-hello

# 编译
php ../../cli.php build --mode=bin hello-console.php

# 运行
.\hello-console.exe
```

### 编译 GUI 版本

```bash
# 进入示例目录
cd examples/win32-hello

# 编译（使用 --no-console 参数）
php ../../cli.php build --mode=bin --no-console hello-gui.php

# 运行
.\hello-gui.exe
```

---

## 💡 使用场景对比

| 场景 | 推荐模式 | 原因 |
|------|---------|------|
| 命令行工具 | 控制台模式 | 需要文本输出和输入 |
| 后台服务 | 控制台模式 | 需要日志输出 |
| 开发调试 | 控制台模式 | 便于查看调试信息 |
| 桌面应用 | GUI 模式 | 提供更好的用户体验 |
| 游戏 | GUI 模式 | 全屏或窗口化运行 |
| 工具软件 | GUI 模式 | 图形界面更友好 |

---

## ⚙️ 编译选项说明

### --no-console 参数

- **作用**：隐藏控制台窗口，创建纯 GUI 应用程序
- **适用平台**：仅 Windows
- **链接选项**：添加 `/SUBSYSTEM:WINDOWS`
- **注意事项**：
  - 控制台输出函数（echo、print 等）将无效
  - 需要使用消息框或其他 GUI 元素进行输出
  - 调试时建议使用控制台模式

### 其他常用选项

```bash
# 优化级别
php cli.php build -O2 app.php

# 指定输出文件名
php cli.php build -o myapp app.php

# 启用调试信息
php cli.php build --debug-info app.php

# 并行编译
php cli.php build -j 8 app.php
```

---

## 🐛 常见问题

### Q: 为什么我的 echo 不显示？

A: 如果使用了 `--no-console` 参数，控制台被隐藏了。请改用消息框：
```php
messagebox(0, "你的消息", "标题", 0);
```

### Q: 如何调试没有控制台的程序？

A: 
1. 使用消息框显示变量值
2. 写入日志文件：`file_put_contents('app.log', $msg, FILE_APPEND)`
3. 临时移除 `--no-console` 参数进行调试
4. 使用 [debug-test.php](./debug-test.php) 示例程序
5. 查看详细的 [DEBUG_GUIDE.md](./DEBUG_GUIDE.md)

### Q: 出现 "Debug Assertion Failed" 错误怎么办？

A: 这通常是由于 CRT 库冲突导致的。解决方法：

1. **重新编译**（编译器已自动添加 `/NODEFAULTLIB` 选项）
   ```bash
   php ../../cli.php build --mode=bin --no-console your-app.php
   ```

2. **清理构建目录**
   ```bash
   Remove-Item -Recurse -Force build/
   php ../../cli.php build --mode=bin --no-console your-app.php
   ```

3. **使用调试测试程序**
   ```bash
   php ../../cli.php build --mode=bin --no-console debug-test.php
   .\debug-test.exe
   # 查看 debug-test.log 文件
   ```

4. **查看详细调试指南**
   - 参考 [DEBUG_GUIDE.md](./DEBUG_GUIDE.md)
   - 使用 Visual Studio 调试器
   - 使用 WinDbg

### Q: 中文显示乱码怎么办？

A: 确保：
1. 源文件使用 UTF-8 编码保存
2. C++ 中使用 `MultiByteToWideChar` 转换编码
3. 使用 `MessageBoxW` 而不是 `MessageBoxA`
4. 在 PHP 中设置正确的时区

### Q: 可以同时显示控制台和窗口吗？

A: 技术上可以，但不推荐。通常的做法是：
- 开发时使用控制台模式便于调试
- 发布时使用 GUI 模式提供更好的用户体验

---

## 📝 项目结构

```
win32-hello/
├── hello-console.php      # 控制台版本示例
├── hello-gui.php          # GUI 版本示例
├── debug-test.php         # 调试测试程序（带日志）
├── debug-mode-test.php    # 调试模式测试程序（--debug-info）
├── asan-test.php          # AddressSanitizer 测试程序
├── main.php               # 另一个示例文件
├── window.php             # 窗口创建示例
├── cpp-src/
│   ├── winapi.cc          # C++ 实现文件
│   └── winapi.stub.php    # PHP Stub 声明
├── WINDOWS_GUI_GUIDE.md   # GUI 程序指南
├── CHINESE_SUPPORT_GUIDE.md  # 中文支持指南
├── CPP_FUNCTION_EXPORT_GUIDE.md  # C++ 函数导出指南
├── DEBUG_GUIDE.md         # 调试指南（重要！）
├── DEBUG_MODE_GUIDE.md    # 调试模式使用指南
├── SANITIZER_GUIDE.md     # AddressSanitizer 使用指南
├── MSVC_WARNINGS_SUPPRESSION.md  # MSVC 警告屏蔽说明
└── README.md              # 本文件
```

---

## 🔗 相关资源

- [PHPX 编译器文档](https://github.com/swoole/phpx)
- [Windows API 文档](https://docs.microsoft.com/en-us/windows/win32/api/)
- [MessageBoxW API](https://docs.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-messageboxw)
- [MultiByteToWideChar](https://docs.microsoft.com/en-us/windows/win32/api/stringapiset/nf-stringapiset-multibytetowidechar)

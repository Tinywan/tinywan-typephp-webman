# 俄罗斯方块游戏 - 完整使用指南

## 📋 系统要求

- **PHP 版本**: >= 8.4.0
- **操作系统**: Windows 10/11
- **编译器**: MSVC (Microsoft Visual C++)
- **依赖**: PHPX 编译器框架

## 🎮 游戏介绍

这是一个使用 PHP 和 C++ 混合编程的俄罗斯方块游戏示例，展示了：
- PHP 编写高层游戏逻辑
- C++ 提供底层图形 API 和性能关键代码
- 通过 Box 机制在 PHP 和 C++ 之间传递对象

## 📁 项目结构

```
tetris/
├── main.php                 # 完整版游戏主程序
├── test-simple.php          # 简化测试版（用于快速验证）
├── project.yml              # 项目配置文件
├── README.md                # 项目说明
├── USAGE_GUIDE.md           # 本使用指南
├── php-src/
│   └── tetris.stub.php     # C++ 函数的 PHP 声明
└── cpp-src/
    └── tetris.cc           # C++ 实现（游戏引擎 + 图形渲染）
```

## 🔧 编译方法

### 方法一：编译完整版游戏

```bash
cd examples/tetris
php ../../bin/tpc.php build main.php
```

或使用 project.yml：

```bash
cd examples/tetris
php ../../bin/tpc.php build .
```

### 方法二：编译简化测试版

```bash
cd examples/tetris
php ../../bin/tpc.php build test-simple.php
```

### 编译选项

```bash
# 启用优化
php ../../bin/tpc.php build -O2 main.php

# 启用调试信息
php ../../bin/tpc.php build --debug-info main.php

# 指定输出文件名
php ../../bin/tpc.php build -o tetris-game main.php

# 并行编译（加速）
php ../../bin/tpc.php build -j 8 main.php
```

## 🚀 运行游戏

编译成功后，会在 `build/` 目录生成可执行文件：

```bash
# 运行完整版游戏
.\build\tetris.exe

# 或运行测试版
.\build\test-simple.exe
```

## 🎯 游戏控制

### 键盘操作

| 按键 | 功能 |
|------|------|
| ← (左箭头) | 向左移动方块 |
| → (右箭头) | 向右移动方块 |
| ↑ (上箭头) | 旋转方块 |
| ↓ (下箭头) | 加速下落 |
| 空格键 | 直接落下（硬降） |

### 游戏规则

1. **基本玩法**
   - 不同形状的方块从顶部随机出现
   - 玩家可以移动和旋转方块
   - 方块落地后固定，新方块继续出现

2. **消除规则**
   - 当一行被完全填满时，该行消除
   - 一次消除多行有额外奖励分数
   - 消除后上方的方块会自动下落

3. **计分系统**
   - 消除 1 行：100 分
   - 消除 2 行：400 分（2×2×100）
   - 消除 3 行：900 分（3×3×100）
   - 消除 4 行：1600 分（4×4×100）

4. **难度递增**
   - 初始下落间隔：500 毫秒
   - 每获得 500 分，速度提升一级
   - 最快下落间隔：100 毫秒

5. **游戏结束**
   - 当新方块无法放置时，游戏结束
   - 显示最终得分
   - 可以选择重新开始或退出

## 🏗️ 技术架构

### 三层架构设计

#### 1. C++ 层 (cpp-src/tetris.cc)

**TetrisBox 类** - 继承自 `Box`
```cpp
class TetrisBox : public Box {
public:
    int board[BOARD_HEIGHT][BOARD_WIDTH];  // 游戏面板
    int currentShape[4][4];                // 当前方块
    int currentX, currentY;                // 当前位置
    int currentType;                       // 方块类型
    int score;                             // 分数
    bool gameOver;                         // 游戏状态
    
    // 核心算法
    void spawnNewPiece();                  // 生成新方块
    bool isValidPosition();                // 碰撞检测
    void rotate();                         // 旋转
    bool moveDown/moveLeft/moveRight();   // 移动
    void lockPiece();                      // 固定方块
    void clearLines();                     // 消除行
};
```

**导出的 C++ 函数**（以 `php_` 前缀命名）：
- `php_tetris_new()` - 创建游戏实例
- `php_tetris_reset()` - 重置游戏
- `php_tetris_get_score()` - 获取分数
- `php_tetris_is_game_over()` - 检查游戏结束
- `php_tetris_rotate/move_*()` - 方块控制
- `php_tetris_render()` - 图形渲染
- `php_tetris_handle_key()` - 键盘处理

#### 2. Stub 层 (php-src/tetris.stub.php)

声明所有 C++ 函数供 PHP 调用：
```php
function tetris_new(): mixed {}
function tetris_get_score(mixed $game): int {}
function tetris_move_down(mixed $game): bool {}
// ... 其他函数声明
```

#### 3. PHP 层 (main.php)

**TetrisGame 类** - 游戏主控制器
```php
class TetrisGame {
    private mixed $game;        // C++ Box 对象
    private int $hWnd;          // 窗口句柄
    private int $lastDropTime;  // 上次下落时间
    private int $dropInterval;  // 下落间隔
    
    public function initWindow()    // 初始化窗口
    public function run()           // 游戏主循环
    private function handleKeyPress()  // 处理输入
    private function handleGameOver()  // 处理游戏结束
}
```

### Box 对象传递机制

```
C++ 层                          PHP 层
┌─────────────┐                
│ TetrisBox   │  new TetrisBox()  
│  extends Box│ ──────────────► mixed $game
│             │ ◄──────────────  传递给 C++ 函数
└─────────────┘   toBox<TetrisBox>()
```

**关键点**：
1. C++ 类必须继承自 `Box`
2. 使用 `{new ClassName()}` 返回给 PHP
3. PHP 使用 `mixed` 类型接收
4. C++ 函数中使用 `box.toBox<ClassName>()` 转换回来

### Windows GUI 编程

**窗口创建流程**：
```php
// 1. 注册窗口类（C++ 中完成）
// 2. 创建窗口
$hWnd = tetris_create_window("俄罗斯方块");

// 3. 显示窗口
tetris_show_window($hWnd, SW_SHOW);

// 4. 消息循环
while (PeekMessage($msg, $hWnd, 0, 0, 1)) {
    // 处理消息
}

// 5. 渲染画面
tetris_render($game, $hWnd);
```

**GDI 绘图**：
- 使用 `FillRect` 绘制方块
- 使用 `Rectangle` 绘制边框
- 使用 `CreateSolidBrush` 设置颜色
- 支持 UTF-8 中文显示（通过 `MultiByteToWideChar` 转换）

## 🐛 常见问题

### Q1: 编译时提示 PHP 版本过低

**错误信息**：
```
Composer detected issues in your platform: Your Composer dependencies 
require a PHP version ">= 8.4.0". You are running 8.1.27.
```

**解决方案**：
1. 升级 PHP 到 8.4+ 版本
2. 或修改 `composer.json` 中的版本要求（不推荐）

### Q2: 编译成功但运行时没有窗口

**可能原因**：
- 使用了 `--no-console` 参数但没有正确创建窗口
- 窗口创建失败

**解决方案**：
1. 检查 `tetris_create_window()` 返回值是否为 0
2. 使用消息框调试：`tetris_messagebox(0, "Debug", "Info", 0)`
3. 查看是否有错误日志

### Q3: 中文显示乱码

**解决方案**：
确保：
1. 源文件使用 UTF-8 编码保存
2. C++ 中使用 `MultiByteToWideChar` 转换
3. 使用 `MessageBoxW` 而不是 `MessageBoxA`

### Q4: 游戏运行卡顿

**优化建议**：
1. 减少渲染频率（目前约 60 FPS）
2. 使用 `-O2` 优化级别编译
3. 检查是否有内存泄漏

### Q5: 如何调试游戏逻辑？

**调试方法**：
```php
// 1. 使用 echo 输出（控制台模式）
echo "Score: " . tetris_get_score($game) . "\n";

// 2. 写入日志文件
file_put_contents('game.log', $message, FILE_APPEND);

// 3. 使用消息框
tetris_messagebox(0, $message, "Debug", MB_OK);

// 4. 使用调试模式编译
php ../../bin/tpc.php build --debug-info main.php
```

## 📊 性能分析

### 帧率控制
```php
Sleep(16);  // 约 60 FPS (1000ms / 60 ≈ 16ms)
```

### 自动下落计时
```php
$currentTime = GetTickCount();
if ($currentTime - $this->lastDropTime > $this->dropInterval) {
    tetris_move_down($this->game);
    $this->lastDropTime = $currentTime;
}
```

### 速度调整
```php
// 根据分数动态调整下落速度
$this->dropInterval = max(100, 500 - intdiv($score, 500) * 50);
```

## 🔬 扩展开发

### 添加新功能示例

#### 1. 添加暂停功能

**C++ 层** (`tetris.cc`)：
```cpp
Bool php_tetris_is_paused(var box) {
    auto tetris = box.toBox<TetrisBox>();
    return tetris->paused;
}

void php_tetris_toggle_pause(var box) {
    auto tetris = box.toBox<TetrisBox>();
    tetris->paused = !tetris->paused;
}
```

**Stub 层** (`tetris.stub.php`)：
```php
function tetris_is_paused(mixed $game): bool {}
function tetris_toggle_pause(mixed $game): void {}
```

**PHP 层** (`main.php`)：
```php
case VK_P:
    tetris_toggle_pause($this->game);
    break;
```

#### 2. 添加音效

可以使用 Windows API 的 `PlaySound` 函数：

```cpp
#include <mmsystem.h>

void php_play_sound(String wavFile) {
    PlaySound(wavFile.data(), NULL, SND_FILENAME | SND_ASYNC);
}
```

#### 3. 保存最高分

```php
function saveHighScore(int $score): void {
    file_put_contents('highscore.txt', $score);
}

function loadHighScore(): int {
    if (file_exists('highscore.txt')) {
        return (int)file_get_contents('highscore.txt');
    }
    return 0;
}
```

## 📚 学习资源

### 参考示例
- `examples/win32-hello` - Windows GUI 基础
- `examples/prime` - Box 对象封装
- `docs/MIXED_CPP_PHP.md` - C++ 和 PHP 混合编程

### 外部资源
- [Windows API 文档](https://docs.microsoft.com/windows/win32/)
- [GDI 绘图教程](https://docs.microsoft.com/windows/win32/gdi/)
- [PHPX 编译器文档](https://github.com/swoole/phpx)

## 🎓 教学要点

这个项目适合学习：

1. **混合编程架构**
   - 如何在 PHP 和 C++ 之间分工
   - 对象传递机制（Box）
   - 函数导出规范

2. **游戏开发基础**
   - 游戏循环设计
   - 事件驱动编程
   - 状态管理

3. **Windows 编程**
   - Win32 API 使用
   - 消息循环处理
   - GDI 图形绘制

4. **性能优化**
   - 计算密集型任务交给 C++
   - PHP 负责高层逻辑
   - 合理的帧率控制

## 📝 许可证

本项目遵循与 PHPX 编译器相同的许可证。

## 🤝 贡献

欢迎提交改进建议和 Bug 报告！

---

**祝游戏愉快！** 🎮

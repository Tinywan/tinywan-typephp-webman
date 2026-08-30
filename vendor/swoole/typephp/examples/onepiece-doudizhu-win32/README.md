# 海贼王 · 斗地主（Win32 / TypePHP 示例）

将 `HelgeSverre-libui-sdk/examples/onepiece-doudizhu.php`（libui 版）移植为
**TypePHP（tpc.exe）AOT 编译的原生 Win32 程序**，参考 `landlord-win32` 的
「纯 PHP 逻辑 + C++ 薄封装 Win32 绑定」架构。

## 特性

- 完整保留原作玩法：三大势力（海军 / 七武海 / 四皇）、9 名角色技能、
  叫地主 → 出牌 → 结算全流程，内置 AI 对手与托管。
- 界面由 Win32 GDI 自绘（海洋渐变背景、势力配色卡牌、对手面板、
  可拖拽手牌、底部操作按钮）。
- 游戏逻辑 100% 在 PHP 中实现（`php-src/doudizhu/`），与 libui 版共用同一套
  领域模型；C++（`cpp-src/win32.cc`）仅封装窗口、消息循环与 GDI 绘制原语。
- 单文件 exe，无外部 PHP 依赖，不依赖 libui / mbstring / miniaudio。

## 目录结构

```
onepiece-doudizhu-win32/
├── main.php                    入口：Win32 消息循环驱动 GameController
├── project.yml                 tpc.exe 构建配置（mode: bin）
├── build.bat                   一键编译脚本（需 VS 2022 x64 工具链环境）
├── cpp-src/
│   └── win32.cc                Win32 窗口 + GDI 绘制原语（C++ 薄封装）
└── php-src/
    ├── win32.stub.php          win_* 原生函数声明（stub）
    └── doudizhu/               游戏域模型 + 渲染 shim
        ├── Card / Deck / Combo / MoveGenerator / Game / Ai / Skill /
        │   Character / Faction / PlayerState   纯逻辑（与 libui 版同源）
        ├── GameController.php  对局编排 + Win32 输入/渲染适配
        ├── Render.php          libui DrawContext 兼容 shim → GDI
        └── Sound.php           音效管理器（TypePHP 版为空实现，保留 API）
```

## 编译

在 **x64 Native Tools Command Prompt for VS 2022**（或已加载
`vcvars64.bat` 的终端）中执行：

```bat
build.bat
```

脚本会：
1. 校验 `cl.exe` 可用（不在 VS 环境中会提示）。
2. 从 TypePHP 根目录调用 `tpc.exe project.yml`（tpc 打包后按 CWD 解析
   `vendor/autoload.php`，必须从根目录运行）。
3. 将产物 `onepiece_doudizhu.exe` 复制回示例目录。

等价手动命令：

```bat
set PHP_HOME=D:\git\php\tpc_v1095_windows_x86_64
set PHPX_HOME=%PHP_HOME%\phpx
set PATH=%PHP_HOME%;%PATH%
cd /d D:\git\php\tpc_v1095_windows_x86_64
tpc.exe examples\onepiece-doudizhu-win32\project.yml --no-progress
```

## 运行

```bat
onepiece_doudizhu.exe
```

- 拖拽手牌选牌，底部按钮：出牌 / 不出 / 提示 / 技能 / 托管。
- 右上角按钮切换音效（当前为占位实现）。
- 关闭窗口或 `Esc` 退出。

## 移植要点

1. **渲染 shim（Render.php）**：原 `GameController` 的绘制代码直接使用
   libui 的 `DrawContext / Brush / Color / FontDescriptor / DrawTextAlign /
   TextWeight`，这些符号在 `php-src/doudizhu/Render.php` 中同命名空间重新声明，
   `WinDrawContext` 把每个调用翻译为 `win_fill_rect / win_fill_ellipse /
   win_fill_rounded_rect / win_stroke_rounded_rect / win_draw_text_ex`。
   渐变近似为纯色填充；颜色统一 `0xRRGGBB → COLORREF(0xBBGGRR)` 转换。
2. **输入驱动**：libui 的 `AreaDelegate::mouse/key` 改为 `main.php` 主循环
   轮询 `win_peek_message()`，把 `WM_LBUTTONDOWN/UP/MOUSEMOVE/KEYDOWN`
   转换为 `GameController::onMouse()/onKey()` 调用。
3. **定时器**：`Loop::delay` 改为 `GameController::tick()`，由主循环每帧
   调用，驱动 AI 走子 / 叫分 / 托管（基于 `win_get_tick_count()`）。
4. **TypePHP 兼容性修正**（相对 libui 版源码）：
   - 顶层游离代码（`define('FONT', …)`）包装进 `ensureDdzFont()`，由
     `main()` 调用——TPC 要求所有执行代码位于函数内。
   - `readonly` 属性 / `&$ref` 引用遍历等改为普通属性 / 下标赋值（TPC 语法限制）。
   - `mb_strlen / mb_substr` 改为 `ddz_utf8_len / ddz_utf8_substr`
     （PCRE 实现，TPC 运行时未链接 mbstring）。
   - `Brush::linearGradient()` 签名改为与 libui 一致的
     `(x0,y0,x1,y1, stops)` 形式，避免 TPC 变参类型推断失败。
5. **双缓冲位图管理（win32.cc）**：修复 `GetStockObject(BITMAP)` 用法错误
   （`BITMAP` 是类型不是常量），改为保存/恢复 DC 原 bitmap 后再删除离屏位图。
6. **窗口尺寸（win32.cc）**：`win_create_window` 把传入宽高当作**客户区**尺寸
   （`AdjustWindowRect` 自动补上标题栏/边框），保证底部按钮完整可见；窗口
   样式为 `WS_OVERLAPPEDWINDOW`，支持拖拽缩放与最大化。新增
   `win_get_client_size()` 返回当前客户区尺寸，`render()`/`drawButtons()`
   每帧按实际尺寸自适应布局（绘制与点击命中均基于当前尺寸记录的矩形）。
7. **运行时兼容性**：`MoveGenerator::all()` 的 `$cnt` 闭包改为
   `count($byRank[$r] ?? [])`——原实现 `count($byRank[$r])` 在顺子/连对
   枚举缺失 rank 时对 null 调用 `count()`，TypePHP 运行时（PHP 8 严格类型）
   抛 `TypeError: count(): Argument #1 must be of type Countable|array`。
8. **窗口标题乱码（win32.cc）**：本机腾讯电脑管家（`tsbx.dll`）会 inline-hook
   进程内 user32 的 `DefWindowProcA` / `CreateWindowExW` / `SetWindowTextW`，
   导致窗口标题被改写为乱码（"wm<崑s " 之类）。修复：
   - `DdzWndProc` 末尾显式调用 **`DefWindowProcW`**（而非 `DefWindowProc` 宏，
     后者在非 UNICODE 编译下展开为被 hook 的 `DefWindowProcA`）。
   - `php_win_create_window` 通过 `LoadLibraryW("C:\\Windows\\System32\\user32.dll")`
     + `GetProcAddress` 解析**原始** `CreateWindowExW` / `SetWindowTextW` 调用，
     创建后再用原始 `SetWindowTextW` 重设一次标题（双保险）。
   - 对照组验证：Explorer / Edge / SmartGit 等其它进程窗口标题读取正常，
     仅本进程自定义 WndProc 窗口受影响，证明是进程内 hook 而非系统/代码问题。
9. **托管节奏（GameController.php）**：Win32 移植版里 `defer()` 与 `delay()`
   都由主循环 `tick()` 驱动、机制相同。原代码在托管/AI 走子/叫分处同时挂
   `defer`（立即执行）+ `delay`（兜底），导致动作在下一帧瞬间完成，玩家
   来不及点「取消托管」。修复：
   - 去掉托管分支的 `defer`，只保留 **1500ms** 延迟（充足取消窗口）。
   - `scheduleAi()` 去掉 `defer`，AI 出牌节奏改为 **800ms**。
   - `scheduleBidStep()` 去掉 `defer`，AI 叫分节奏改为 **700ms**。
   - `toggleAutoPlay()` 关闭托管时调用 `cancelTimers()` 立即取消挂起的
     托管定时器；`autoPlayStep()` 原有 `!$this->autoPlay` 保护兜底。
   - `toggleAutoPlay()` 关闭托管且轮到玩家时，重新调用
     `setActionsForHumanTurn()` 重算按钮状态——托管时「出牌/不出/提示/技能」
     按 `!autoPlay` 被禁用，恢复后必须重算才能重新可点。

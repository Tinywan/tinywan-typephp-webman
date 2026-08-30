<?php

/**
 * 海贼王 · 斗地主 —— TypePHP / Win32 入口。
 *
 * 本文件替代原 libui 版 onepiece-doudizhu.php 的入口：不再依赖 libui 事件循环，
 * 而是用 Win32 消息循环驱动 GameController（见 php-src/doudizhu/GameController.php）。
 * 所有游戏逻辑（发牌 / 叫地主 / 出牌 / AI / 技能 / 渲染）均在 PHP 中实现，
 * C++ 仅负责窗口、GDI 绘制原语与输入（cpp-src/win32.cc）。
 *
 * 编译：在 examples/onepiece-doudizhu-win32 目录执行
 *         tpc.exe project.yml
 * 运行：生成的 .exe（无外部 PHP 依赖，纯 Win32 + GDI）。
 *
 * 注意：TypePHP 的 bin 模式会自动以 main() 作为程序入口，无需手动调用。
 */

// Win32 常量
const SW_SHOW = 5;

// win_peek_message() 返回的消息类型
const MSG_OTHER = 0;
const MSG_MOUSE_DOWN = 1;
const MSG_MOUSE_UP = 2;
const MSG_MOUSE_MOVE = 3;
const MSG_KEY_DOWN = 4;

use Yangweijie\Ui2\Games\OnePieceDoudizhu\GameController;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\Sound;

function main(): void
{
    \date_default_timezone_set('Asia/Shanghai');
    \Yangweijie\Ui2\Games\OnePieceDoudizhu\ensureDdzFont();

    $hWnd = win_create_window('海贼王 · 斗地主', GameController::WIN_W, GameController::WIN_H);
    if ($hWnd == 0) {
        echo "窗口创建失败！\n";

        return;
    }
    win_show_window($hWnd, SW_SHOW);

    $ctrl = new GameController();
    $ctrl->hWnd = $hWnd;
    $ctrl->newGame();

    echo "海贼王 · 斗地主 已启动（Win32 / TypePHP）\n";
    echo "提示：拖拽手牌选牌，底部按钮出牌/不出/提示/技能/托管，右上角切换音效。\n";

    while (true) {
        // 1) 处理所有待处理消息
        while (true) {
            $m = win_peek_message();
            if (\count($m) === 0) {
                break;
            }
            $type = $m[0] ?? MSG_OTHER;
            if ($type === MSG_MOUSE_DOWN) {
                $ctrl->onMouse((object) ['x' => $m[1], 'y' => $m[2], 'down' => 1, 'up' => 0, 'held' => 0]);
            } elseif ($type === MSG_MOUSE_UP) {
                $ctrl->onMouse((object) ['x' => $m[1], 'y' => $m[2], 'down' => 0, 'up' => 1, 'held' => 0]);
            } elseif ($type === MSG_MOUSE_MOVE) {
                $ctrl->onMouse((object) ['x' => $m[1], 'y' => $m[2], 'down' => 0, 'up' => 0, 'held' => $m[3]]);
            } elseif ($type === MSG_KEY_DOWN) {
                $ctrl->onKey((int) $m[1]);
            }
            // MSG_OTHER (例如 WM_PAINT) 已在 WndProc 中 ValidateRect，此处忽略
        }

        if (win_quit_requested()) {
            break;
        }

        // 2) 触发到期的定时器（AI 走子 / 叫分 / 托管自动出牌）
        $ctrl->tick();

        // 3) 渲染当前帧（双缓冲，整窗重绘）
        $ctrl->render();

        // 4) 简单节流到 ~60 FPS
        \usleep(16000);
    }

    Sound::instance()->unload();
    echo "游戏结束，bye!\n";
}

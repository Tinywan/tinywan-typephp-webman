<?php

final class AndroidHelloApplication
{
    private const BUTTON_PRIMARY = 1;
    private const BUTTON_SECONDARY = 2;

    private static int $nameInput = 0;
    private static int $statusLabel = 0;
    private static int $countButton = 0;
    private static int $resetButton = 0;
    private static int $clickCount = 0;

    public static function build(): void
    {
        ui_create_window('TypePHP Android', 390, 844);
        ui_add_image('typephp_icon', 105, 36, 180, 96);
        ui_add_label('TypePHP Native App', 24, 142, 342, 52, 29, true);
        ui_add_label(
            "界面结构、应用状态和事件逻辑均由 TypePHP 实现。\n" .
            'Java 仅提供 Activity 和原生 View 桥接。',
            30,
            202,
            330,
            74,
            15,
            false,
        );
        self::$nameInput = ui_add_text_input('请输入名字', 30, 300, 330, 56);
        self::$statusLabel = ui_add_label('', 30, 378, 330, 74, 18, true);
        self::$countButton = ui_add_button(
            '使用 TypePHP 点击计数',
            30,
            476,
            330,
            58,
            self::BUTTON_PRIMARY,
        );
        self::$resetButton = ui_add_button(
            '重置计数',
            105,
            552,
            180,
            52,
            self::BUTTON_SECONDARY,
        );
        self::renderStatus(false);
    }

    public static function handleEvent(int $controlId): void
    {
        if ($controlId === self::$resetButton) {
            self::$clickCount = 0;
            self::renderStatus(true);
            return;
        }

        if ($controlId !== self::$countButton) {
            return;
        }

        self::$clickCount++;
        self::renderStatus(false);
    }

    private static function renderStatus(bool $wasReset): void
    {
        $name = ui_get_control_text(self::$nameInput);
        if ($name === '') {
            $name = 'Android';
        }

        if ($wasReset) {
            ui_set_control_text(self::$statusLabel, $name . '，计数已重置');
            return;
        }

        if (self::$clickCount === 0) {
            ui_set_control_text(self::$statusLabel, '输入名字，然后开始计数');
            return;
        }

        ui_set_control_text(
            self::$statusLabel,
            '你好，' . $name . '！已点击 ' . self::$clickCount . ' 次',
        );
    }
}

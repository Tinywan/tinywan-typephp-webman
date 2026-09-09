<?php

final class HelloApplication
{
    private const BUTTON_PRIMARY = 1;
    private const BUTTON_SECONDARY = 2;
    private const BUTTON_TERTIARY = 3;

    private static int $titleLabel = 0;
    private static int $descriptionLabel = 0;
    private static int $statusLabel = 0;
    private static int $countButton = 0;
    private static int $resetButton = 0;
    private static int $languageButton = 0;
    private static int $clickCount = 0;
    private static bool $isChinese = false;

    public static function build(string $platformApi): void
    {
        if ($platformApi === 'UIKit') {
            self::buildPhoneLayout();
        } else {
            self::buildDesktopLayout();
        }
        self::renderText();
    }

    public static function handleEvent(int $controlId): void
    {
        if ($controlId === self::$languageButton) {
            self::$isChinese = !self::$isChinese;
            self::renderText();
            return;
        }

        if ($controlId === self::$resetButton) {
            self::$clickCount = 0;
            self::renderStatus();
            return;
        }

        if ($controlId !== self::$countButton) {
            return;
        }

        self::$clickCount++;
        self::renderStatus();
    }

    private static function buildPhoneLayout(): void
    {
        ui_create_window('TypePHP Native Hello', 390, 844);
        self::$titleLabel = ui_add_label('', 24, 672, 342, 76, 30, true);
        self::$descriptionLabel = ui_add_label('', 30, 544, 330, 104, 16, false);
        self::$statusLabel = ui_add_label('', 30, 454, 330, 58, 18, true);
        self::$countButton = ui_add_button('', 30, 354, 330, 58, self::BUTTON_PRIMARY);
        self::$resetButton = ui_add_button('', 30, 280, 158, 52, self::BUTTON_SECONDARY);
        self::$languageButton = ui_add_button('', 202, 280, 158, 52, self::BUTTON_TERTIARY);
    }

    private static function buildDesktopLayout(): void
    {
        ui_create_window('TypePHP Native Hello', 640, 420);
        self::$titleLabel = ui_add_label('', 44, 336, 552, 40, 28, true);
        self::$descriptionLabel = ui_add_label('', 64, 222, 512, 82, 15, false);
        self::$statusLabel = ui_add_label('', 64, 164, 512, 30, 16, true);
        self::$countButton = ui_add_button('', 160, 92, 320, 46, self::BUTTON_PRIMARY);
        self::$resetButton = ui_add_button('', 160, 34, 150, 40, self::BUTTON_SECONDARY);
        self::$languageButton = ui_add_button('', 330, 34, 150, 40, self::BUTTON_TERTIARY);
    }

    private static function renderText(): void
    {
        if (self::$isChinese) {
            ui_set_control_text(self::$titleLabel, 'TypePHP 原生应用');
            ui_set_control_text(
                self::$descriptionLabel,
                "界面逻辑和应用状态均由 TypePHP 实现。\nObjective-C++ 仅提供轻量的原生 UI 桥接。",
            );
            ui_set_control_text(self::$countButton, '点击计数');
            ui_set_control_text(self::$resetButton, '重置');
            ui_set_control_text(self::$languageButton, 'English');
        } else {
            ui_set_control_text(self::$titleLabel, 'TypePHP Native App');
            ui_set_control_text(
                self::$descriptionLabel,
                "Application logic and state are implemented in TypePHP.\n" .
                'Objective-C++ provides only a thin native UI bridge.',
            );
            ui_set_control_text(self::$countButton, 'Count with TypePHP');
            ui_set_control_text(self::$resetButton, 'Reset');
            ui_set_control_text(self::$languageButton, '中文');
        }
        self::renderStatus();
    }

    private static function renderStatus(): void
    {
        if (self::$isChinese) {
            ui_set_control_text(self::$statusLabel, '已点击 ' . self::$clickCount . ' 次');
            return;
        }

        $suffix = self::$clickCount === 1 ? 'click' : 'clicks';
        ui_set_control_text(self::$statusLabel, self::$clickCount . " $suffix");
    }
}

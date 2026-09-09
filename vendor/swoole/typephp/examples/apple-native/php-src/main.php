<?php

function main(): void
{
    ui_app_init('TypePHP macOS Hello');
    HelloApplication::build('AppKit');
    ui_show_window();
    while (true) {
        $controlId = ui_next_event();
        if ($controlId < 0) {
            break;
        }
        HelloApplication::handleEvent($controlId);
    }
}

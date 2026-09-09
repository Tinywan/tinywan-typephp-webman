<?php

/** Called by the thin UIKit application delegate after iOS finishes launching. */
function typephp_application_did_launch(): void
{
    HelloApplication::build('UIKit');
    ui_show_window();
}

/** Called by the UIKit target-action bridge for every activated control. */
function typephp_application_control_activated(int $controlId): void
{
    HelloApplication::handleEvent($controlId);
}

function main(): void
{
    ui_app_run('TypePHP iOS Hello');
}

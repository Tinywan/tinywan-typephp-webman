<?php

/** Called by the thin Android Activity after its native library is loaded. */
function typephp_application_did_launch(): void
{
    AndroidHelloApplication::build();
    ui_show_window();
}

/** Called by the generic Android View bridge for every activated control. */
function typephp_application_control_activated(int $controlId): void
{
    AndroidHelloApplication::handleEvent($controlId);
}

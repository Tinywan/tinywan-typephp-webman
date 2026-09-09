<?php

/** Windows API smoke-test declarations implemented by winapi.cc. */
function windows_current_process_id(): int {}

function windows_has_module_handle(): bool {}

function windows_logical_processor_count(): int {}

function windows_php_is_zts(): bool {}

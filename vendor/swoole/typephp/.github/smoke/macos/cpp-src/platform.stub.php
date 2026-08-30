<?php

/** macOS platform declarations implemented by platform.cc. */
function macos_current_process_id(): int {}

function macos_logical_processor_count(): int {}

function macos_has_mach_host_port(): bool {}

function macos_native_is_arm64(): bool {}

function macos_native_php_is_zts(): bool {}

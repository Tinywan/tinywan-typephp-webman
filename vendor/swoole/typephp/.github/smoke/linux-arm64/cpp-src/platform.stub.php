<?php

/** Linux ARM64 platform declarations implemented by platform.cc. */
function linux_current_process_id(): int {}

function linux_online_processor_count(): int {}

function linux_uname_machine_is_arm64(): bool {}

function linux_native_is_arm64(): bool {}

function linux_native_php_is_zts(): bool {}

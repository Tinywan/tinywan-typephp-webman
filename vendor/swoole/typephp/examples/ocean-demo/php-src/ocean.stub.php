<?php

function ocean_init(string $title, int $width, int $height): bool {}
function ocean_shutdown(): void {}
function ocean_should_close(): bool {}
function ocean_poll_events(): void {}
function ocean_key_pressed(int $key): bool {}
function ocean_mouse_delta_x(): float {}
function ocean_mouse_delta_y(): float {}
function ocean_get_time(): float {}
function ocean_sleep(int $milliseconds): void {}
function ocean_confirm_exit(): bool {}
function ocean_set_boat(float $x, float $z, float $yaw, float $speed): void {}
function ocean_set_camera(float $yaw, float $pitch, float $distance): void {}
function ocean_set_environment(float $dayTime, int $weather, float $weatherMix, float $rainAmount): void {}
function ocean_begin_chunk(int $chunkX, int $chunkZ): void {}
function ocean_add_marker(float $x, float $z, int $type, float $size): void {}
function ocean_commit_chunk(int $chunkX, int $chunkZ): void {}
function ocean_remove_chunk(int $chunkX, int $chunkZ): void {}
function ocean_render_frame(): void {}

<?php

function craft_init(string $title, int $width, int $height, string $texturePath): bool {}
function craft_set_sky_texture(string $texturePath): bool {}
function craft_shutdown(): void {}
function craft_should_close(): bool {}
function craft_poll_events(): void {}
function craft_begin_world(): void {}
function craft_set_block(int $x, int $y, int $z, int $type): void {}
function craft_build_mesh(): void {}
function craft_begin_chunk(int $chunkX, int $chunkZ): void {}
function craft_set_chunk_block(int $x, int $y, int $z, int $type): void {}
function craft_commit_chunk(int $chunkX, int $chunkZ): void {}
function craft_remove_chunk(int $chunkX, int $chunkZ): void {}
function craft_render_loading(int $done, int $total): void {}
function craft_render_frame(): void {}
function craft_sleep(int $milliseconds): void {}
function craft_key_pressed(int $key): bool {}
function craft_mouse_delta_x(): float {}
function craft_mouse_delta_y(): float {}
function craft_set_camera(float $x, float $y, float $z, float $yaw, float $pitch): void {}
function craft_get_time(): float {}
function craft_confirm_exit(): bool {}

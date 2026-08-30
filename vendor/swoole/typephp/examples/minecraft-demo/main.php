<?php

declare(strict_types=1);

const BLOCK_GRASS = 1;
const BLOCK_SAND = 2;
const BLOCK_STONE = 3;
const BLOCK_WOOD = 5;
const BLOCK_DIRT = 7;
const BLOCK_SNOW = 9;
const BLOCK_COBBLE = 11;
const BLOCK_LEAVES = 15;
const BLOCK_CLOUD = 16;
const BLOCK_TALL_GRASS = 17;
const BLOCK_YELLOW_FLOWER = 18;
const BLOCK_RED_FLOWER = 19;
const BLOCK_PURPLE_FLOWER = 20;
const BLOCK_SUN_FLOWER = 21;
const BLOCK_WHITE_FLOWER = 22;
const BLOCK_BLUE_FLOWER = 23;
const BLOCK_WATER = 64;

const KEY_W = 0x57;
const KEY_A = 0x41;
const KEY_S = 0x53;
const KEY_D = 0x44;
const KEY_SPACE = 0x20;
const KEY_SHIFT = 0x10;
const KEY_ESCAPE = 0x1B;

const WORLD_RADIUS = 4096;
const CHUNK_SIZE = 16;
const RENDER_CHUNK_RADIUS = 4;
const KEEP_CHUNK_RADIUS = 5;
const CHUNKS_PER_FRAME = 1;
const WATER_LEVEL = 4;
const MAX_Y = 34;
const CLOUD_MIN_Y = 29;
const CLOUD_MAX_Y = 31;

function demo_noise2(int $x, int $z, float $scale): float
{
    $a = sin($x * $scale + $z * $scale * 0.71);
    $b = cos($x * $scale * 1.37 - $z * $scale * 0.83);
    $c = sin(($x + $z) * $scale * 0.53 + cos($z * $scale));
    return ($a + $b + $c) / 3.0;
}

function demo_hash2(int $x, int $z): float
{
    $n = sin($x * 127.1 + $z * 311.7) * 43758.5453123;
    return $n - floor($n);
}

function world_is_river(int $x, int $z): bool
{
    $spawnLake = (($x - 7) * ($x - 7) + ($z - 7) * ($z - 7)) <= 28;
    if ($spawnLake) {
        return true;
    }
    $center = sin($z * 0.22) * 5.0 + sin($z * 0.07) * 2.0;
    $width = 3.4 + cos($z * 0.13) * 1.0;
    return abs($x - $center) <= $width;
}

function world_height_at(int $x, int $z): int
{
    $continent = demo_noise2($x, $z, 0.018) * 7.0;
    $ridge = abs(demo_noise2($x - 451, $z + 173, 0.042)) * 8.5;
    $rolling = demo_noise2($x + 917, $z - 431, 0.092) * 3.2;
    $detail = demo_noise2(-$x, $z + 331, 0.23) * 1.4;
    $height = 7 + (int) round($continent + $ridge + $rolling + $detail);
    if ($height < 2) {
        $height = 2;
    } elseif ($height > 24) {
        $height = 24;
    }
    if (world_is_river($x, $z)) {
        $height -= 5;
        if ($height < 1) {
            $height = 1;
        } elseif ($height > WATER_LEVEL - 1) {
            $height = WATER_LEVEL - 1;
        }
    }
    return $height;
}

function world_is_steep(int $x, int $z, int $height): bool
{
    $n1 = world_height_at($x + 1, $z);
    $n2 = world_height_at($x - 1, $z);
    $n3 = world_height_at($x, $z + 1);
    $n4 = world_height_at($x, $z - 1);
    return $height - min($n1, $n2, $n3, $n4) >= 2;
}

function world_has_tree(int $x, int $z): bool
{
    if (world_is_river($x, $z)) {
        return false;
    }
    if (($x % 13) !== 0 || ($z % 13) !== 0) {
        return false;
    }
    return demo_hash2($x, $z) > 0.72;
}

function world_plant_type_at(int $x, int $z): int
{
    if (world_is_river($x, $z)) {
        return 0;
    }
    $flowerNoise = demo_noise2($x + 93, -$z - 17, 0.17);
    if ($flowerNoise > 0.46) {
        $pick = (int) floor(demo_hash2($x, $z) * 6.0);
        if ($pick === 0) {
            return BLOCK_YELLOW_FLOWER;
        }
        if ($pick === 1) {
            return BLOCK_RED_FLOWER;
        }
        if ($pick === 2) {
            return BLOCK_PURPLE_FLOWER;
        }
        if ($pick === 3) {
            return BLOCK_SUN_FLOWER;
        }
        if ($pick === 4) {
            return BLOCK_WHITE_FLOWER;
        }
        return BLOCK_BLUE_FLOWER;
    }
    $grassNoise = demo_noise2(-$x, $z, 0.31);
    if ($grassNoise > 0.24 || demo_hash2($x + 11, $z - 19) > 0.70) {
        return BLOCK_TALL_GRASS;
    }
    return 0;
}

function world_has_cloud(int $x, int $y, int $z): bool
{
    if ($y < CLOUD_MIN_Y || $y > CLOUD_MAX_Y) {
        return false;
    }
    $layer = $y - CLOUD_MIN_Y;
    $shape = demo_noise2($x + 701, $z - 223, 0.045);
    $detail = demo_noise2($x * 2 + 17, $z * 2 - 31, 0.12) * 0.25;
    $threshold = $layer === 1 ? 0.46 : 0.58;
    return $shape + $detail > $threshold;
}

function block_type_at(int $x, int $y, int $z): int
{
    if ($y < 0 || $y > MAX_Y) {
        return 0;
    }

    if (world_has_cloud($x, $y, $z)) {
        return BLOCK_CLOUD;
    }

    $height = world_height_at($x, $z);
    $river = world_is_river($x, $z);

    if ($river && $y === WATER_LEVEL) {
        return BLOCK_WATER;
    }

    if ($y <= $height) {
        if ($y === $height) {
            if ($river || $height <= WATER_LEVEL) {
                return BLOCK_SAND;
            }
            if ($height >= 20) {
                return BLOCK_SNOW;
            }
            if (world_is_steep($x, $z, $height)) {
                return BLOCK_STONE;
            }
            return BLOCK_GRASS;
        }
        if ($height >= 17 && $y >= $height - 3) {
            return BLOCK_STONE;
        }
        return $y >= $height - 2 ? BLOCK_DIRT : BLOCK_STONE;
    }

    if ($y === $height + 1) {
        return world_plant_type_at($x, $z);
    }

    for ($tx = $x - 3; $tx <= $x + 3; $tx++) {
        for ($tz = $z - 3; $tz <= $z + 3; $tz++) {
            if (!world_has_tree($tx, $tz)) {
                continue;
            }
            $base = world_height_at($tx, $tz) + 1;
            if ($x === $tx && $z === $tz && $y >= $base && $y <= $base + 5) {
                return BLOCK_WOOD;
            }
            $dx = $x - $tx;
            $dz = $z - $tz;
            $dy = $y - ($base + 4);
            if ($y >= $base + 2 && $y <= $base + 6 && ($dx * $dx + $dz * $dz + $dy * $dy) <= 10) {
                return BLOCK_LEAVES;
            }
        }
    }

    return 0;
}

function floor_chunk(int $value): int
{
    if ($value >= 0) {
        return intdiv($value, CHUNK_SIZE);
    }
    return -intdiv(-$value + CHUNK_SIZE - 1, CHUNK_SIZE);
}

function chunk_key(int $chunkX, int $chunkZ): string
{
    return (string) $chunkX . ':' . (string) $chunkZ;
}

function sort_pending_chunks(array $pendingChunks, int $centerChunkX, int $centerChunkZ): array
{
    uasort($pendingChunks, static function (array $a, array $b) use ($centerChunkX, $centerChunkZ): int {
        $adx = (int) $a[0] - $centerChunkX;
        $adz = (int) $a[1] - $centerChunkZ;
        $bdx = (int) $b[0] - $centerChunkX;
        $bdz = (int) $b[1] - $centerChunkZ;
        $da = $adx * $adx + $adz * $adz;
        $db = $bdx * $bdx + $bdz * $bdz;
        return $da <=> $db;
    });
    return $pendingChunks;
}

function generate_chunk(int $chunkX, int $chunkZ): int
{
    $startX = $chunkX * CHUNK_SIZE;
    $startZ = $chunkZ * CHUNK_SIZE;
    $endX = $startX + CHUNK_SIZE - 1;
    $endZ = $startZ + CHUNK_SIZE - 1;
    $count = 0;

    craft_begin_chunk($chunkX, $chunkZ);
    for ($x = $startX; $x <= $endX; $x++) {
        if ($x < -WORLD_RADIUS || $x > WORLD_RADIUS) {
            continue;
        }
        for ($z = $startZ; $z <= $endZ; $z++) {
            if ($z < -WORLD_RADIUS || $z > WORLD_RADIUS) {
                continue;
            }
            for ($y = 0; $y <= MAX_Y; $y++) {
                $type = block_type_at($x, $y, $z);
                if ($type !== 0) {
                    craft_set_chunk_block($x, $y, $z, $type);
                    $count++;
                }
            }
        }
    }
    craft_commit_chunk($chunkX, $chunkZ);
    return $count;
}

function enqueue_visible_chunks(int $centerChunkX, int $centerChunkZ, array $loadedChunks, array $pendingChunks): array
{
    $needed = [];
    $queued = 0;

    for ($cx = $centerChunkX - RENDER_CHUNK_RADIUS; $cx <= $centerChunkX + RENDER_CHUNK_RADIUS; $cx++) {
        for ($cz = $centerChunkZ - RENDER_CHUNK_RADIUS; $cz <= $centerChunkZ + RENDER_CHUNK_RADIUS; $cz++) {
            $chunkKey = chunk_key($cx, $cz);
            $needed[$chunkKey] = 1;
            if (!isset($loadedChunks[$chunkKey]) && !isset($pendingChunks[$chunkKey])) {
                $pendingChunks[$chunkKey] = [$cx, $cz];
                $queued++;
            }
        }
    }

    return $queued > 0 ? sort_pending_chunks($pendingChunks, $centerChunkX, $centerChunkZ) : $pendingChunks;
}

function unload_far_chunks(int $centerChunkX, int $centerChunkZ, array $loadedChunks): array
{
    $removed = 0;
    foreach ($loadedChunks as $loadedKey => $chunk) {
        $dx = abs((int) $chunk[0] - $centerChunkX);
        $dz = abs((int) $chunk[1] - $centerChunkZ);
        if ($dx > KEEP_CHUNK_RADIUS || $dz > KEEP_CHUNK_RADIUS) {
            craft_remove_chunk((int) $chunk[0], (int) $chunk[1]);
            unset($loadedChunks[$loadedKey]);
            $removed++;
        }
    }
    return $loadedChunks;
}

function process_chunk_queue(array $loadedChunks, array $pendingChunks, int $maxChunks): array
{
    $loaded = 0;
    foreach ($pendingChunks as $pendingKey => $chunk) {
        generate_chunk((int) $chunk[0], (int) $chunk[1]);
        $loadedChunks[$pendingKey] = [(int) $chunk[0], (int) $chunk[1]];
        unset($pendingChunks[$pendingKey]);
        $loaded++;
        if ($loaded >= $maxChunks) {
            break;
        }
    }
    return [$loadedChunks, $pendingChunks];
}

function main(): void
{
    if (!craft_init('TypePHP Minecraft Demo - Craft/OpenGL', 1280, 720, 'examples/minecraft-demo/textures/texture.png')) {
        echo "OpenGL 初始化失败\n";
        return;
    }

    craft_set_sky_texture('examples/minecraft-demo/textures/sky.png');

    $x = 8.0;
    $z = 18.0;
    $y = (float) world_height_at((int) $x, (int) $z) + 2.4;
    $yaw = -2.55;
    $pitch = 0.35;
    $last = craft_get_time();
    $escapeWasDown = false;
    $centerChunkX = floor_chunk((int) floor($x));
    $centerChunkZ = floor_chunk((int) floor($z));
    $loadedChunks = [];
    $pendingChunks = [];
    craft_begin_world();
    $pendingChunks = enqueue_visible_chunks($centerChunkX, $centerChunkZ, $loadedChunks, $pendingChunks);
    $initialTotal = count($pendingChunks);
    $initialDone = 0;
    craft_render_loading($initialDone, $initialTotal);
    while (count($pendingChunks) > 0) {
        craft_poll_events();
        $before = count($pendingChunks);
        $queueState = process_chunk_queue($loadedChunks, $pendingChunks, 1);
        $loadedChunks = $queueState[0];
        $pendingChunks = $queueState[1];
        $initialDone += $before - count($pendingChunks);
        craft_render_loading($initialDone, $initialTotal);
    }

    while (!craft_should_close()) {
        $now = craft_get_time();
        $dt = max(0.001, min(0.05, $now - $last));
        $last = $now;

        craft_poll_events();
        $escapeDown = craft_key_pressed(KEY_ESCAPE);
        if ($escapeDown && !$escapeWasDown) {
            if (craft_confirm_exit()) {
                break;
            }
            $last = craft_get_time();
        }
        $escapeWasDown = $escapeDown;

        $yaw -= craft_mouse_delta_x() * 0.0022;
        $pitch += craft_mouse_delta_y() * 0.0022;
        $pitch = max(-1.45, min(1.45, $pitch));

        $speed = craft_key_pressed(KEY_SHIFT) ? 14.0 : 7.0;
        $forwardX = -sin($yaw);
        $forwardZ = -cos($yaw);
        $rightX = cos($yaw);
        $rightZ = -sin($yaw);

        if (craft_key_pressed(KEY_W)) {
            $x += $forwardX * $speed * $dt;
            $z += $forwardZ * $speed * $dt;
        }
        if (craft_key_pressed(KEY_S)) {
            $x -= $forwardX * $speed * $dt;
            $z -= $forwardZ * $speed * $dt;
        }
        if (craft_key_pressed(KEY_D)) {
            $x += $rightX * $speed * $dt;
            $z += $rightZ * $speed * $dt;
        }
        if (craft_key_pressed(KEY_A)) {
            $x -= $rightX * $speed * $dt;
            $z -= $rightZ * $speed * $dt;
        }
        if (craft_key_pressed(KEY_SPACE)) {
            $y += $speed * $dt;
        }
        if (craft_key_pressed(KEY_SHIFT)) {
            $y -= $speed * $dt * 0.6;
        }

        $currentChunkX = floor_chunk((int) floor($x));
        $currentChunkZ = floor_chunk((int) floor($z));
        if ($currentChunkX !== $centerChunkX || $currentChunkZ !== $centerChunkZ) {
            $centerChunkX = $currentChunkX;
            $centerChunkZ = $currentChunkZ;
            $pendingChunks = enqueue_visible_chunks($centerChunkX, $centerChunkZ, $loadedChunks, $pendingChunks);
            $loadedChunks = unload_far_chunks($centerChunkX, $centerChunkZ, $loadedChunks);
            $last = craft_get_time();
        }
        $queueState = process_chunk_queue($loadedChunks, $pendingChunks, CHUNKS_PER_FRAME);
        $loadedChunks = $queueState[0];
        $pendingChunks = $queueState[1];

        craft_set_camera($x, $y, $z, $yaw, $pitch);
        craft_render_frame();
        craft_sleep(1);
    }

    craft_shutdown();
}

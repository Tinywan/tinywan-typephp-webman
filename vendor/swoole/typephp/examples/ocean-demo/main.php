<?php

declare(strict_types=1);

const KEY_W = 0x57;
const KEY_A = 0x41;
const KEY_S = 0x53;
const KEY_D = 0x44;
const KEY_ESCAPE = 0x1B;

const WEATHER_SUNNY = 0;
const WEATHER_CLOUDY = 1;
const WEATHER_RAIN = 2;
const WORLD_CHUNK_SIZE = 96;
const RENDER_CHUNK_RADIUS = 4;
const KEEP_CHUNK_RADIUS = 5;
const CHUNKS_PER_FRAME = 1;

function weather_name(int $weather): string
{
    if ($weather === WEATHER_CLOUDY) {
        return 'cloudy';
    }
    if ($weather === WEATHER_RAIN) {
        return 'rainy';
    }
    return 'sunny';
}

function choose_next_weather(int $current): int
{
    $roll = mt_rand(0, 99);
    if ($current === WEATHER_SUNNY) {
        return $roll < 58 ? WEATHER_CLOUDY : ($roll < 78 ? WEATHER_RAIN : WEATHER_SUNNY);
    }
    if ($current === WEATHER_CLOUDY) {
        return $roll < 42 ? WEATHER_SUNNY : ($roll < 76 ? WEATHER_RAIN : WEATHER_CLOUDY);
    }
    return $roll < 52 ? WEATHER_CLOUDY : ($roll < 76 ? WEATHER_SUNNY : WEATHER_RAIN);
}

function floor_chunk(float $value): int
{
    $v = (int) floor($value);
    if ($v >= 0) {
        return intdiv($v, WORLD_CHUNK_SIZE);
    }
    return -intdiv(-$v + WORLD_CHUNK_SIZE - 1, WORLD_CHUNK_SIZE);
}

function chunk_key(int $chunkX, int $chunkZ): string
{
    return (string) $chunkX . ':' . (string) $chunkZ;
}

function hash01(int $x, int $z, int $salt): float
{
    $n = sin($x * 127.1 + $z * 311.7 + $salt * 74.7) * 43758.5453123;
    return $n - floor($n);
}

function sort_pending_chunks(array $pendingChunks, int $centerChunkX, int $centerChunkZ): array
{
    uasort($pendingChunks, static function (array $a, array $b) use ($centerChunkX, $centerChunkZ): int {
        $adx = (int) $a[0] - $centerChunkX;
        $adz = (int) $a[1] - $centerChunkZ;
        $bdx = (int) $b[0] - $centerChunkX;
        $bdz = (int) $b[1] - $centerChunkZ;
        return ($adx * $adx + $adz * $adz) <=> ($bdx * $bdx + $bdz * $bdz);
    });
    return $pendingChunks;
}

function enqueue_visible_chunks(int $centerChunkX, int $centerChunkZ, array $loadedChunks, array $pendingChunks): array
{
    $queued = 0;
    for ($cx = $centerChunkX - RENDER_CHUNK_RADIUS; $cx <= $centerChunkX + RENDER_CHUNK_RADIUS; $cx++) {
        for ($cz = $centerChunkZ - RENDER_CHUNK_RADIUS; $cz <= $centerChunkZ + RENDER_CHUNK_RADIUS; $cz++) {
            $key = chunk_key($cx, $cz);
            if (!isset($loadedChunks[$key]) && !isset($pendingChunks[$key])) {
                $pendingChunks[$key] = [$cx, $cz];
                $queued++;
            }
        }
    }
    return $queued > 0 ? sort_pending_chunks($pendingChunks, $centerChunkX, $centerChunkZ) : $pendingChunks;
}

function generate_world_chunk(int $chunkX, int $chunkZ): void
{
    ocean_begin_chunk($chunkX, $chunkZ);
    $baseX = $chunkX * WORLD_CHUNK_SIZE;
    $baseZ = $chunkZ * WORLD_CHUNK_SIZE;
    $chunkRoll = hash01($chunkX, $chunkZ, 101);
    if (($chunkX === 0 && $chunkZ === -2) || $chunkRoll > 0.86) {
        $x = $baseX + WORLD_CHUNK_SIZE * (0.35 + hash01($chunkX, $chunkZ, 102) * 0.30);
        $z = $baseZ + WORLD_CHUNK_SIZE * (0.30 + hash01($chunkX, $chunkZ, 103) * 0.38);
        ocean_add_marker($x, $z, 3, 8.0 + hash01($chunkX, $chunkZ, 104) * 5.0);
    }
    if (($chunkX === -1 && $chunkZ === -1) || $chunkRoll < 0.08) {
        $x = $baseX + WORLD_CHUNK_SIZE * (0.42 + hash01($chunkX, $chunkZ, 105) * 0.24);
        $z = $baseZ + WORLD_CHUNK_SIZE * (0.36 + hash01($chunkX, $chunkZ, 106) * 0.30);
        ocean_add_marker($x, $z, 4, 5.5 + hash01($chunkX, $chunkZ, 107) * 2.4);
    }
    for ($i = 0; $i < 5; $i++) {
        $chance = hash01($chunkX * 17 + $i, $chunkZ * 19 - $i, 3);
        if ($chance < 0.48) {
            continue;
        }
        $x = $baseX + hash01($chunkX, $chunkZ, 20 + $i) * WORLD_CHUNK_SIZE;
        $z = $baseZ + hash01($chunkX, $chunkZ, 40 + $i) * WORLD_CHUNK_SIZE;
        $typeRoll = hash01($chunkX, $chunkZ, 60 + $i);
        $type = $typeRoll > 0.78 ? 2 : ($typeRoll > 0.52 ? 1 : 0);
        $size = 0.8 + hash01($chunkX, $chunkZ, 80 + $i) * 1.8;
        ocean_add_marker($x, $z, $type, $size);
    }
    ocean_commit_chunk($chunkX, $chunkZ);
}

function process_chunk_queue(array $loadedChunks, array $pendingChunks, int $maxChunks): array
{
    $loaded = 0;
    foreach ($pendingChunks as $key => $chunk) {
        generate_world_chunk((int) $chunk[0], (int) $chunk[1]);
        $loadedChunks[$key] = [(int) $chunk[0], (int) $chunk[1]];
        unset($pendingChunks[$key]);
        $loaded++;
        if ($loaded >= $maxChunks) {
            break;
        }
    }
    return [$loadedChunks, $pendingChunks];
}

function unload_far_chunks(int $centerChunkX, int $centerChunkZ, array $loadedChunks): array
{
    foreach ($loadedChunks as $key => $chunk) {
        $dx = abs((int) $chunk[0] - $centerChunkX);
        $dz = abs((int) $chunk[1] - $centerChunkZ);
        if ($dx > KEEP_CHUNK_RADIUS || $dz > KEEP_CHUNK_RADIUS) {
            ocean_remove_chunk((int) $chunk[0], (int) $chunk[1]);
            unset($loadedChunks[$key]);
        }
    }
    return $loadedChunks;
}

function main(): void
{
    if (!ocean_init('TypePHP Ocean Demo - OpenGL', 1280, 720)) {
        echo "OpenGL init failed\n";
        return;
    }

    mt_srand((int) (ocean_get_time() * 1000000.0));

    $x = 0.0;
    $z = 0.0;
    $yaw = 0.0;
    $velocityX = 0.0;
    $velocityZ = 0.0;
    $cameraYaw = 0.0;
    $cameraPitch = 0.24;
    $cameraDistance = 24.0;
    $last = ocean_get_time();
    $escapeWasDown = false;

    $dayTime = 0.28;
    $weather = WEATHER_SUNNY;
    $targetWeather = WEATHER_CLOUDY;
    $weatherMix = 0.0;
    $nextWeatherAt = $last + 18.0;
    $centerChunkX = floor_chunk($x);
    $centerChunkZ = floor_chunk($z);
    $loadedChunks = [];
    $pendingChunks = enqueue_visible_chunks($centerChunkX, $centerChunkZ, [], []);
    while (count($pendingChunks) > 0) {
        $queueState = process_chunk_queue($loadedChunks, $pendingChunks, 4);
        $loadedChunks = $queueState[0];
        $pendingChunks = $queueState[1];
        ocean_poll_events();
    }

    while (!ocean_should_close()) {
        $now = ocean_get_time();
        $dt = max(0.001, min(0.05, $now - $last));
        $last = $now;

        ocean_poll_events();
        $escapeDown = ocean_key_pressed(KEY_ESCAPE);
        if ($escapeDown && !$escapeWasDown) {
            if (ocean_confirm_exit()) {
                break;
            }
            $last = ocean_get_time();
        }
        $escapeWasDown = $escapeDown;

        $cameraYaw -= ocean_mouse_delta_x() * 0.0025;
        $cameraPitch += ocean_mouse_delta_y() * 0.0020;
        $cameraPitch = max(-0.20, min(0.85, $cameraPitch));

        $inputX = 0.0;
        $inputZ = 0.0;
        if (ocean_key_pressed(KEY_W)) {
            $inputZ -= 1.0;
        }
        if (ocean_key_pressed(KEY_S)) {
            $inputZ += 1.0;
        }
        if (ocean_key_pressed(KEY_A)) {
            $inputX -= 1.0;
        }
        if (ocean_key_pressed(KEY_D)) {
            $inputX += 1.0;
        }

        $len = sqrt($inputX * $inputX + $inputZ * $inputZ);
        if ($len > 0.0) {
            $inputX /= $len;
            $inputZ /= $len;
        }

        $accel = 18.0;
        $drag = pow(0.08, $dt);
        $forwardX = -sin($cameraYaw);
        $forwardZ = -cos($cameraYaw);
        $rightX = cos($cameraYaw);
        $rightZ = -sin($cameraYaw);
        $worldInputX = $rightX * $inputX + $forwardX * -$inputZ;
        $worldInputZ = $rightZ * $inputX + $forwardZ * -$inputZ;
        if ($len > 0.0) {
            $yaw = atan2($worldInputX, -$worldInputZ);
        }
        $velocityX = ($velocityX + $worldInputX * $accel * $dt) * $drag;
        $velocityZ = ($velocityZ + $worldInputZ * $accel * $dt) * $drag;
        $x += $velocityX * $dt;
        $z += $velocityZ * $dt;
        $speed = sqrt($velocityX * $velocityX + $velocityZ * $velocityZ);

        $currentChunkX = floor_chunk($x);
        $currentChunkZ = floor_chunk($z);
        if ($currentChunkX !== $centerChunkX || $currentChunkZ !== $centerChunkZ) {
            $centerChunkX = $currentChunkX;
            $centerChunkZ = $currentChunkZ;
            $pendingChunks = enqueue_visible_chunks($centerChunkX, $centerChunkZ, $loadedChunks, $pendingChunks);
            $loadedChunks = unload_far_chunks($centerChunkX, $centerChunkZ, $loadedChunks);
        }
        $queueState = process_chunk_queue($loadedChunks, $pendingChunks, CHUNKS_PER_FRAME);
        $loadedChunks = $queueState[0];
        $pendingChunks = $queueState[1];

        $dayTime += $dt / 96.0;
        if ($dayTime >= 1.0) {
            $dayTime -= 1.0;
        }

        if ($now >= $nextWeatherAt && $targetWeather === $weather) {
            $targetWeather = choose_next_weather($weather);
            $weatherMix = 0.0;
            $nextWeatherAt = $now + 16.0 + mt_rand(0, 16);
            echo 'Weather changed: ' . weather_name($targetWeather) . PHP_EOL;
        }
        if ($targetWeather !== $weather) {
            $weatherMix = min(1.0, $weatherMix + $dt / 7.0);
            if ($weatherMix >= 1.0) {
                $weather = $targetWeather;
                $weatherMix = 0.0;
            }
        }

        $rainAmount = $weather === WEATHER_RAIN ? 1.0 : 0.0;
        if ($targetWeather === WEATHER_RAIN) {
            $rainAmount = max($rainAmount, $weatherMix);
        } elseif ($weather === WEATHER_RAIN) {
            $rainAmount = 1.0 - $weatherMix;
        }

        ocean_set_boat($x, $z, $yaw, $speed);
        ocean_set_camera($cameraYaw, $cameraPitch, $cameraDistance);
        ocean_set_environment($dayTime, $targetWeather, $weatherMix, max(0.0, min(1.0, $rainAmount)));
        ocean_render_frame();
        ocean_sleep(1);
    }

    ocean_shutdown();
}

<?php

declare(strict_types=1);

const DEMO_BLOCK_GRASS = 1;
const DEMO_BLOCK_SAND = 2;
const DEMO_BLOCK_STONE = 3;
const DEMO_BLOCK_WOOD = 5;
const DEMO_BLOCK_DIRT = 7;
const DEMO_BLOCK_SNOW = 9;
const DEMO_BLOCK_COBBLE = 11;
const DEMO_BLOCK_LEAF = 15;
const DEMO_BLOCK_WATER = 64;
const DEMO_WATER_LEVEL = 4;
const DEMO_MAX_Y = 22;

function demo_world_height_at(int $x, int $z): int
{
    $large = demo_noise2($x, $z, 0.045) * 3.2;
    $medium = demo_noise2($x + 917, $z - 431, 0.13) * 1.6;
    $small = demo_noise2(-$x, $z + 331, 0.31) * 0.8;
    $height = 6 + (int) round($large + $medium + $small);
    if ($height < 1) {
        $height = 1;
    } elseif ($height > 12) {
        $height = 12;
    }
    if (demo_world_is_river($x, $z)) {
        $height -= 2;
        if ($height < 1) {
            $height = 1;
        } elseif ($height > DEMO_WATER_LEVEL - 1) {
            $height = DEMO_WATER_LEVEL - 1;
        }
    }
    return $height;
}

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

function demo_world_is_river(int $x, int $z): bool
{
    $spawnLake = (($x - 7) * ($x - 7) + ($z - 7) * ($z - 7)) <= 28;
    if ($spawnLake) {
        return true;
    }
    $center = sin($z * 0.22) * 5.0 + sin($z * 0.07) * 2.0;
    $width = 3.4 + cos($z * 0.13) * 1.0;
    return abs($x - $center) <= $width;
}

function demo_world_has_tree(int $x, int $z): bool
{
    if (demo_world_is_river($x, $z)) {
        return false;
    }
    if (($x % 6) !== 0 || ($z % 6) !== 0) {
        return false;
    }
    return demo_hash2($x, $z) > 0.58;
}

function demo_world_block_type_at(int $x, int $y, int $z): int
{
    if ($y < 0 || $y > DEMO_MAX_Y) {
        return -1;
    }

    $height = demo_world_height_at($x, $z);
    $river = demo_world_is_river($x, $z);
    if ($river && $y === DEMO_WATER_LEVEL) {
        return DEMO_BLOCK_WATER;
    }
    if ($y <= $height) {
        if ($y === $height) {
            if ($river || $height <= DEMO_WATER_LEVEL) {
                return DEMO_BLOCK_SAND;
            }
            if ($height >= 10) {
                return DEMO_BLOCK_SNOW;
            }
            if (demo_world_is_steep($x, $z, $height)) {
                return DEMO_BLOCK_STONE;
            }
            return DEMO_BLOCK_GRASS;
        }
        if ($height >= 9 && $y >= $height - 2) {
            return DEMO_BLOCK_STONE;
        }
        return $y >= $height - 2 ? DEMO_BLOCK_DIRT : DEMO_BLOCK_STONE;
    }

    for ($tx = $x - 3; $tx <= $x + 3; $tx++) {
        for ($tz = $z - 3; $tz <= $z + 3; $tz++) {
            if (!demo_world_has_tree($tx, $tz)) {
                continue;
            }
            $base = demo_world_height_at($tx, $tz) + 1;
            if ($x === $tx && $z === $tz && $y >= $base && $y <= $base + 5) {
                return DEMO_BLOCK_WOOD;
            }
            $dx = $x - $tx;
            $dz = $z - $tz;
            $dy = $y - ($base + 4);
            if ($y >= $base + 2 && $y <= $base + 6 && ($dx * $dx + $dz * $dz + $dy * $dy) <= 10) {
                return DEMO_BLOCK_LEAF;
            }
        }
    }

    return -1;
}

function demo_world_is_steep(int $x, int $z, int $height): bool
{
    $neighbors = [
        demo_world_height_at($x + 1, $z),
        demo_world_height_at($x - 1, $z),
        demo_world_height_at($x, $z + 1),
        demo_world_height_at($x, $z - 1),
    ];
    return $height - min($neighbors) >= 2;
}

/**
 * @return list<array{x:int,y:int,z:int,type:int}>
 */
function demo_world_generate(int $radius = 18): array
{
    $blocks = [];
    for ($x = -$radius; $x <= $radius; $x++) {
        for ($z = -$radius; $z <= $radius; $z++) {
            for ($y = 0; $y <= DEMO_MAX_Y; $y++) {
                $type = demo_world_block_type_at($x, $y, $z);
                if ($type >= 0) {
                    $blocks[] = ['x' => $x, 'y' => $y, 'z' => $z, 'type' => $type];
                }
            }
        }
    }
    return $blocks;
}

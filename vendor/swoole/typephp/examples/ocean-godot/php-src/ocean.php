<?php

declare(strict_types=1);

const OCEAN_WEATHER_SUNNY = 0;
const OCEAN_WEATHER_CLOUDY = 1;
const OCEAN_WEATHER_RAIN = 2;

function ocean_hash01(int $x, int $z, int $salt): float
{
    $n = sin($x * 127.1 + $z * 311.7 + $salt * 74.7) * 43758.5453123;
    return $n - floor($n);
}

function ocean_island_count(): int
{
    return 18;
}

function ocean_island_x(int $index): float
{
    $angle = $index * 2.39996323 + ocean_hash01($index, 11, 31) * 0.62;
    $ring = 190.0 + ($index % 5) * 145.0 + ocean_hash01($index, 13, 37) * 120.0;
    return cos($angle) * $ring;
}

function ocean_island_z(int $index): float
{
    $angle = $index * 2.39996323 + ocean_hash01($index, 11, 31) * 0.62;
    $ring = 190.0 + ($index % 5) * 145.0 + ocean_hash01($index, 13, 37) * 120.0;
    return sin($angle) * $ring;
}

function ocean_island_radius(int $index): float
{
    return 36.0 + ocean_hash01($index, 3, 11) * 58.0;
}

function ocean_island_height(int $index): float
{
    return 10.0 + ocean_hash01($index, 7, 13) * 26.0;
}

function ocean_island_seed(int $index): float
{
    return 17.0 + $index * 31.0;
}

function ocean_marker_count(): int
{
    return 12;
}

function ocean_marker_x(int $index): float
{
    $ring = 160.0 + ($index % 4) * 150.0;
    $angle = $index * 2.39996323 + ocean_hash01($index, 1, 5) * 0.35;
    return cos($angle) * $ring;
}

function ocean_marker_z(int $index): float
{
    $ring = 160.0 + ($index % 4) * 150.0;
    $angle = $index * 2.39996323 + ocean_hash01($index, 1, 5) * 0.35;
    return sin($angle) * $ring;
}

function ocean_marker_type(int $index): int
{
    $roll = ocean_hash01($index, 9, 17);
    if ($roll > 0.78) {
        return 3;
    }
    if ($roll > 0.48) {
        return 2;
    }
    return 1;
}

function ocean_marker_size(int $index): float
{
    return 1.6 + ocean_hash01($index, 4, 23) * 5.8;
}

function ocean_choose_next_weather(int $current, float $roll): int
{
    if ($current === OCEAN_WEATHER_SUNNY) {
        return $roll < 0.58 ? OCEAN_WEATHER_CLOUDY : OCEAN_WEATHER_RAIN;
    }
    if ($current === OCEAN_WEATHER_CLOUDY) {
        return $roll < 0.42 ? OCEAN_WEATHER_SUNNY : OCEAN_WEATHER_RAIN;
    }
    return $roll < 0.58 ? OCEAN_WEATHER_CLOUDY : OCEAN_WEATHER_SUNNY;
}

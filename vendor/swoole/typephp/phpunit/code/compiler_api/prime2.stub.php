<?php

/** @import-library */

function exported_defaults(
    string $text = 'hello',
    array $options = ['mode' => 'fast'],
    mixed $value = null,
    int $count = 0,
    bool $enabled = false
): array {}

function exported_variadic(string ...$values): array {}

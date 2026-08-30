<?php

class PersistentSymbolCacheItem
{
    public int $value = 1;
}

function exercise_persistent_symbol_cache(
    DateTime $date,
    PersistentSymbolCacheItem $item,
): string {
    ob_start();
    echo $date->format('Y-m-d'), $item->value;
    return ob_get_clean();
}

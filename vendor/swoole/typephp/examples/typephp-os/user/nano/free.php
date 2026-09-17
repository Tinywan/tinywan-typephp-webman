<?php

function writeMiB(int $bytes): void
{
    $tenths = (int) (($bytes * 10) / 1048576);
    $fraction = $tenths % 10;
    $whole = (int) (($tenths - $fraction) / 10);
    echo $whole, '.', $fraction, ' MiB';
}

function writePool(string $name, int $total, int $free): void
{
    $used = $total - $free;
    echo $name, ': total=';
    writeMiB($total);
    echo ', used=';
    writeMiB($used);
    echo ', free=';
    writeMiB($free);
    echo "\n";
}

function main(int $argc, array $argv): void
{
    $total = os_memory_value(0);
    $reserved = os_memory_value(1);
    $zendTotal = os_memory_value(2);
    $zendFree = os_memory_value(3);
    $pageTotal = os_memory_value(4);
    $pageFree = os_memory_value(5);
    $cache = os_memory_value(6);
    if ($total < 0 || $reserved < 0 || $zendTotal < 0 || $zendFree < 0
        || $pageTotal < 0 || $pageFree < 0 || $cache < 0) {
        echo "free: memory information unavailable\n";
        return;
    }

    $available = $zendFree + $pageFree;
    echo "TypePHP-OS memory\n";
    writePool('RAM', $total, $available);
    writePool('Kernel Zend arena', $zendTotal, $zendFree);
    writePool('Physical page pool', $pageTotal, $pageFree);
    echo 'Kernel reservation: ';
    writeMiB($reserved);
    echo "\nATA block cache: ";
    writeMiB($cache);
    echo "\nSwap: 0.0 MiB\n";
    echo "Note: page-pool usage includes this TypePHP command and its Nano runtime.\n";
}

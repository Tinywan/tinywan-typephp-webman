<?php

function writeLine(string $text, int $color): void
{
    kernel_write($text, $color);
    kernel_put_char(10, $color);
}

function writeNumber(int $number, int $color): void
{
    if ($number === 0) {
        kernel_put_char(48, $color);
        return;
    }

    $digits = std::vector(Type::Int);
    while ($number > 0) {
        $digit = $number % 10;
        $digits[] = $digit;
        $number = (int) (($number - $digit) / 10);
    }

    for ($index = count($digits) - 1; $index >= 0; $index--) {
        kernel_put_char(48 + $digits[$index], $color);
    }
}

function isPrime(int $number): bool
{
    if ($number < 2) {
        return false;
    }
    for ($divisor = 2; $divisor * $divisor <= $number; $divisor++) {
        if ($number % $divisor === 0) {
            return false;
        }
    }
    return true;
}

function runFeatureSelfCheck(): void
{
    kernel_clear(0);
    writeLine('TypePHP OS POC', 10);

    kernel_write('Int bits: ', 15);
    writeNumber(kernel_word_bits(), 15);
    kernel_put_char(10, 15);

    kernel_write('RAM MiB: ', 15);
    writeNumber(kernel_memory_megabytes(), 15);
    kernel_put_char(10, 15);

    kernel_write('Zend MiB: ', 15);
    writeNumber(kernel_chunk_smoke_test(), 15);
    kernel_put_char(10, 15);

    $checks = ['Zend string/array: OK', 'Kernel is!'];
    foreach ($checks as $check) {
        writeLine($check, 10);
    }
}

function runMathSelfCheck(): void
{
    $root = sqrt(2.0);
    $power = pow(9.0, 0.5);
    $identity = sin(0.5) * sin(0.5) + cos(0.5) * cos(0.5);
    if ($root > 1.414 && $root < 1.415
        && $power > 2.999 && $power < 3.001
        && $identity > 0.999 && $identity < 1.001) {
        writeLine('OpenLibm math: OK', 10);
        return;
    }
    writeLine('OpenLibm math: FAILED', 12);
}

function runFilesystemSelfCheck(): void
{
    $device = new AtaBlockDevice();
    if (!$device->available()) {
        writeLine('FAT16 disk: unavailable', 12);
        return;
    }

    $volume = new Fat16Volume($device);
    if (!$volume->mount()) {
        writeLine('FAT16 mount: ' . $volume->lastError(), 12);
        return;
    }
    if (!$volume->hasRootEntry('DATA')) {
        $volume->makeRootDirectory('DATA');
    }
    $expected = 'Hello from TypePHP FAT16!';
    if (!$volume->writeRootFile('HELLO.TXT', $expected)) {
        writeLine('FAT16 write: FAILED', 12);
        return;
    }
    $actual = $volume->readRootFile('HELLO.TXT');
    if ($actual !== $expected) {
        writeLine('FAT16 read: FAILED', 12);
        return;
    }
    writeLine('FAT16 file: ' . $actual, 10);
    writeLine('FAT16 root: ' . $volume->rootListing(), 10);
    $hitsBefore = $device->cacheHits();
    if ($volume->readRootFile('HELLO.TXT') !== $expected
        || $device->cacheHits() <= $hitsBefore) {
        writeLine('FAT16 sector cache: FAILED', 12);
        return;
    }
    writeLine('FAT16 sector cache: OK', 10);

    /* Exercise the unchanged PHP standard extension and plain file-stream
     * implementation through the POSIX-to-TypePHP bridge. */
    $filesystem = new KernelFileSystem();
    if (!$filesystem->initialize()) {
        writeLine('PHP file stream: mount FAILED', 12);
        return;
    }
    if (!kernel_fs_install($filesystem)) {
        writeLine('PHP file stream: install FAILED', 12);
        return;
    }
    $streamExpected = 'PHP stream through TypePHP FAT16';
    $written = file_put_contents('/STREAM.TXT', $streamExpected);
    $streamActual = file_get_contents('/STREAM.TXT');
    if ($written !== strlen($streamExpected) || $streamActual !== $streamExpected) {
        writeLine('PHP file stream: FAILED', 12);
        return;
    }
    if (!is_dir('/DOCS') && !mkdir('/DOCS')) {
        writeLine('PHP directory: FAILED', 12);
        return;
    }
    $entries = scandir('/');
    if ($entries === false) {
        writeLine('PHP directory scan: FAILED', 12);
        return;
    }
    echo 'PHP file stream: ', $streamActual, "\n";
    echo 'PHP directory scan: ', implode(', ', $entries), "\n";

}

function runPrimeDemo(int $limit): void
{
    $primes = std::vector(Type::Int);
    for ($candidate = 0; $candidate <= $limit; $candidate++) {
        if (isPrime($candidate)) {
            $primes[] = $candidate;
        }
    }

    kernel_write('Calculate primes: 0-', 11);
    writeNumber($limit, 11);
    kernel_put_char(10, 11);

    kernel_write('Prime count: ', 15);
    writeNumber(count($primes), 15);
    kernel_put_char(10, 15);

    kernel_write('Prime list: ', 15);
    for ($index = 0; $index < count($primes); $index++) {
        if ($index !== 0) {
            kernel_write(', ', 15);
        }
        writeNumber($primes[$index], 15);
    }
    kernel_put_char(10, 15);
}

function main(): void
{
    runFeatureSelfCheck();
    runMathSelfCheck();
    runFilesystemSelfCheck();
    runPrimeDemo(100);
    kernel_process_start();
}

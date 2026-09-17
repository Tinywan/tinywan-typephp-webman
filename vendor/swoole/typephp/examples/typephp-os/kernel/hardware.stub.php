<?php

#[NativeFunction]
function kernel_clear(int $color): void {}

#[NativeFunction]
function kernel_put_char(int $ascii, int $color): void {}

#[NativeFunction]
function kernel_write(string $text, int $color): void {}

#[NativeFunction]
function kernel_word_bits(): int { return 0; }

#[NativeFunction]
function kernel_memory_megabytes(): int { return 0; }

#[NativeFunction]
function kernel_chunk_smoke_test(): int { return 0; }

#[NativeFunction]
function kernel_halt(): void {}

#[NativeFunction]
function kernel_disk_available(): bool { return false; }

#[NativeFunction]
function kernel_disk_read_sector(int $lba): string { return ''; }

#[NativeFunction]
function kernel_disk_write_sector(int $lba, string $data): bool { return false; }

#[NativeFunction]
function kernel_disk_flush(): bool { return false; }

#[NativeFunction]
function kernel_disk_cache_hits(): int { return 0; }

#[NativeFunction]
function kernel_disk_cache_misses(): int { return 0; }

/** Load the embedded ELF shell and transfer control to its Ring-3 entry. */
#[NativeFunction]
function kernel_process_start(): void {}

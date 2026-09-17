typedef unsigned int uint32_t;
typedef unsigned long uint64_t;

extern void typephp_os_memory_init(void *address, unsigned long size);
extern void *memset(void *destination, int value, unsigned long size);

enum {
    MULTIBOOT_INFO_MEMORY_MAP = 1u << 6,
};

static const uint64_t CHUNK_SIZE = 2ul * 1024ul * 1024ul;
static const uint64_t PAGE_SIZE = 4096ul;
/* Keep the payload, bootstrap data, and early host arena away from the two
 * physical allocators. User programs occupy a separate high virtual region
 * in their own CR3, so they no longer overlap this identity-mapped range. */
static const uint64_t KERNEL_RESERVED_END = 64ul * 1024ul * 1024ul;
static const uint64_t IDENTITY_MAP_END = 1024ul * 1024ul * 1024ul;

typedef struct __attribute__((packed)) {
    uint32_t size;
    uint64_t address;
    uint64_t length;
    uint32_t type;
} multiboot_memory_entry;

typedef struct __attribute__((packed)) {
    uint32_t flags;
    uint32_t memory_lower;
    uint32_t memory_upper;
    uint32_t boot_device;
    uint32_t command_line;
    uint32_t modules_count;
    uint32_t modules_address;
    uint32_t symbols[4];
    uint32_t memory_map_length;
    uint32_t memory_map_address;
} multiboot_info;

static uint64_t usable_memory_bytes;
static uint64_t chunk_cursor;
static uint64_t chunk_end;
static uint64_t page_cursor;
static uint64_t page_begin;
static uint64_t page_end;
static uint64_t free_page_head;
static uint64_t total_pages;
static uint64_t available_pages;

static uint64_t align_up(uint64_t value, uint64_t alignment)
{
    return (value + alignment - 1) & ~(alignment - 1);
}

void physical_memory_init(uint64_t multiboot_info_address)
{
    const multiboot_info *info =
        (const multiboot_info *) (unsigned long) multiboot_info_address;
    usable_memory_bytes = 0;
    chunk_cursor = 0;
    chunk_end = 0;
    page_cursor = 0;
    page_begin = 0;
    page_end = 0;
    free_page_head = 0;
    total_pages = 0;
    available_pages = 0;

    if ((info->flags & MULTIBOOT_INFO_MEMORY_MAP) == 0) {
        return;
    }

    uint64_t position = info->memory_map_address;
    const uint64_t end = position + info->memory_map_length;
    while (position + sizeof(multiboot_memory_entry) <= end) {
        const multiboot_memory_entry *entry =
            (const multiboot_memory_entry *) (unsigned long) position;
        if (entry->size < sizeof(multiboot_memory_entry) - sizeof(uint32_t)) {
            break;
        }

        if (entry->type == 1) {
            usable_memory_bytes += entry->length;
            uint64_t region_end = entry->address + entry->length;
            if (region_end > IDENTITY_MAP_END) {
                region_end = IDENTITY_MAP_END;
            }
            uint64_t region_start = entry->address;
            if (region_start < KERNEL_RESERVED_END) {
                region_start = KERNEL_RESERVED_END;
            }
            region_start = align_up(region_start, CHUNK_SIZE);
            if (region_start + CHUNK_SIZE <= region_end
                && region_end - region_start > chunk_end - chunk_cursor) {
                chunk_cursor = region_start;
                chunk_end = region_end;
            }
        }
        position += (uint64_t) entry->size + sizeof(entry->size);
    }

    if (chunk_cursor != 0 && chunk_end > chunk_cursor) {
        const uint64_t region_size = chunk_end - chunk_cursor;
        uint64_t zend_size = (region_size / 2) & ~(CHUNK_SIZE - 1);
        if (zend_size < 16ul * 1024ul * 1024ul
            && region_size >= 32ul * 1024ul * 1024ul) {
            zend_size = 16ul * 1024ul * 1024ul;
        }
        typephp_os_memory_init(
            (void *) (unsigned long) chunk_cursor,
            (unsigned long) zend_size);
        page_cursor = align_up(chunk_cursor + zend_size, PAGE_SIZE);
        page_begin = page_cursor;
        page_end = chunk_end & ~(PAGE_SIZE - 1);
        if (page_end > page_cursor) {
            total_pages = (page_end - page_cursor) / PAGE_SIZE;
            available_pages = total_pages;
        }
    }
}

uint64_t physical_page_allocate(void)
{
    uint64_t page;
    if (free_page_head != 0) {
        page = free_page_head;
        free_page_head = *(uint64_t *) (unsigned long) page;
    } else {
        if (page_cursor == 0 || page_cursor + PAGE_SIZE > page_end) {
            return 0;
        }
        page = page_cursor;
        page_cursor += PAGE_SIZE;
    }
    if (available_pages != 0) {
        --available_pages;
    }
    memset((void *) (unsigned long) page, 0, PAGE_SIZE);
    return page;
}

void physical_page_free(uint64_t page)
{
    if ((page & (PAGE_SIZE - 1)) != 0 || page < page_begin
        || page >= page_cursor || page >= page_end) {
        return;
    }
    *(uint64_t *) (unsigned long) page = free_page_head;
    free_page_head = page;
    ++available_pages;
}

uint64_t physical_page_available(void)
{
    return available_pages;
}

uint64_t physical_page_total(void)
{
    return total_pages;
}

uint64_t physical_memory_bytes(void)
{
    return usable_memory_bytes;
}

uint64_t physical_kernel_reserved_bytes(void)
{
    return KERNEL_RESERVED_END;
}

uint64_t physical_memory_megabytes(void)
{
    return usable_memory_bytes >> 20;
}

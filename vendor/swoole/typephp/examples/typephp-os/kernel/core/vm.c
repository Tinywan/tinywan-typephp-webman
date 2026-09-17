/*
   +----------------------------------------------------------------------+
   | TypePHP OS                                                          |
   +----------------------------------------------------------------------+
   | Per-process x86-64 page tables backed by the physical-page pool.    |
   | SPDX-License-Identifier: BSD-3-Clause                               |
   +----------------------------------------------------------------------+
*/

#include "vm.h"

#include <string.h>

enum {
    PAGE_SIZE = 4096,
    PAGE_PRESENT = 1,
    PAGE_WRITE = 2,
    PAGE_USER = 4,
    PAGE_CACHE_DISABLE = 16,
    PAGE_HUGE = 128,
    PAGE_OWNED = 512,
    USER_FIRST_PDE = 0,
    USER_LAST_PDE = 127,
    MMIO_PDP_INDEX = 4,
};

#define PAGE_NX (UINT64_C(1) << 63)
#define PAGE_ADDRESS UINT64_C(0x000ffffffffff000)
#define USER_ADDRESS_BEGIN UINT64_C(0x40000000)
#define USER_ADDRESS_END UINT64_C(0x50000000)

extern uint64_t physical_page_allocate(void);
extern void physical_page_free(uint64_t page);

static uint64_t mmio_pd_page;
static uint64_t mmio_physical_base;

#define MMIO_VIRTUAL_BASE UINT64_C(0x100000000)
#define HUGE_PAGE_SIZE UINT64_C(0x200000)

static uint64_t *page_table(uint64_t physical)
{
    return (uint64_t *) (uintptr_t) (physical & PAGE_ADDRESS);
}

static uint64_t align_down(uint64_t value)
{
    return value & ~(uint64_t) (PAGE_SIZE - 1);
}

static int align_range(uint64_t address, uint64_t size, uint64_t *begin, uint64_t *end)
{
    if (size == 0 || address > UINT64_MAX - size
        || address + size > UINT64_MAX - (PAGE_SIZE - 1)) {
        return 0;
    }
    *begin = align_down(address);
    *end = (address + size + PAGE_SIZE - 1) & ~(uint64_t) (PAGE_SIZE - 1);
    return *end > *begin;
}

static uint64_t *page_directory(uint64_t address_space)
{
    uint64_t *pml4 = page_table(address_space);
    if ((pml4[0] & PAGE_PRESENT) == 0) {
        return 0;
    }
    uint64_t *pdp = page_table(pml4[0]);
    if ((pdp[1] & PAGE_PRESENT) == 0) {
        return 0;
    }
    return page_table(pdp[1]);
}

uint64_t typephp_vm_create(void)
{
    const uint64_t pml4_page = physical_page_allocate();
    const uint64_t pdp_page = physical_page_allocate();
    const uint64_t kernel_pd_page = physical_page_allocate();
    const uint64_t user_pd_page = physical_page_allocate();
    if (pml4_page == 0 || pdp_page == 0
        || kernel_pd_page == 0 || user_pd_page == 0) {
        if (user_pd_page != 0) physical_page_free(user_pd_page);
        if (kernel_pd_page != 0) physical_page_free(kernel_pd_page);
        if (pdp_page != 0) physical_page_free(pdp_page);
        if (pml4_page != 0) physical_page_free(pml4_page);
        return 0;
    }

    uint64_t *pml4 = page_table(pml4_page);
    uint64_t *pdp = page_table(pdp_page);
    uint64_t *kernel_pd = page_table(kernel_pd_page);
    pml4[0] = pdp_page | PAGE_PRESENT | PAGE_WRITE | PAGE_USER;
    pdp[0] = kernel_pd_page | PAGE_PRESENT | PAGE_WRITE;
    pdp[1] = user_pd_page | PAGE_PRESENT | PAGE_WRITE | PAGE_USER;
    if (mmio_pd_page != 0) {
        pdp[MMIO_PDP_INDEX] = mmio_pd_page | PAGE_PRESENT | PAGE_WRITE;
    }
    for (uint64_t index = 0; index < 512; ++index) {
        kernel_pd[index] = index * UINT64_C(0x200000)
            | PAGE_PRESENT | PAGE_WRITE | PAGE_HUGE;
    }
    return pml4_page;
}

void *typephp_vm_map_mmio(uint64_t physical_address, uint64_t size)
{
    const uint64_t physical_base = physical_address & ~(HUGE_PAGE_SIZE - 1);
    const uint64_t offset = physical_address - physical_base;
    if (size == 0 || size > HUGE_PAGE_SIZE || offset > HUGE_PAGE_SIZE - size) {
        return 0;
    }
    if (mmio_pd_page == 0) {
        mmio_pd_page = physical_page_allocate();
        if (mmio_pd_page == 0) {
            return 0;
        }
        memset(page_table(mmio_pd_page), 0, PAGE_SIZE);
        mmio_physical_base = physical_base;
        page_table(mmio_pd_page)[0] = physical_base | PAGE_PRESENT
            | PAGE_WRITE | PAGE_CACHE_DISABLE | PAGE_HUGE;
    } else if (mmio_physical_base != physical_base) {
        return 0;
    }

    const uint64_t address_space = typephp_vm_current();
    uint64_t *pml4 = page_table(address_space);
    if ((pml4[0] & PAGE_PRESENT) == 0) {
        return 0;
    }
    uint64_t *pdp = page_table(pml4[0]);
    pdp[MMIO_PDP_INDEX] = mmio_pd_page | PAGE_PRESENT | PAGE_WRITE;
    typephp_vm_activate(address_space);
    return (void *) (uintptr_t) (MMIO_VIRTUAL_BASE + offset);
}

void typephp_vm_activate(uint64_t address_space)
{
    __asm__ volatile("mov %0, %%cr3" : : "r"(address_space) : "memory");
}

uint64_t typephp_vm_current(void)
{
    uint64_t address_space;
    __asm__ volatile("mov %%cr3, %0" : "=r"(address_space));
    return address_space & PAGE_ADDRESS;
}

int typephp_vm_map_user(
    uint64_t address_space, uint64_t address, uint64_t size, unsigned int flags)
{
    uint64_t begin;
    uint64_t end;
    uint64_t *pd = page_directory(address_space);
    if (pd == 0 || !align_range(address, size, &begin, &end)
        || begin < USER_ADDRESS_BEGIN || end > USER_ADDRESS_END) {
        return 0;
    }

    for (uint64_t cursor = begin; cursor < end; cursor += PAGE_SIZE) {
        const uint64_t pd_index = (cursor >> 21) & 0x1ffu;
        const uint64_t pt_index = (cursor >> 12) & 0x1ffu;
        uint64_t *pt;
        if ((pd[pd_index] & PAGE_PRESENT) == 0) {
            const uint64_t pt_page = physical_page_allocate();
            if (pt_page == 0) {
                return 0;
            }
            pd[pd_index] = pt_page | PAGE_PRESENT | PAGE_WRITE | PAGE_USER;
        } else if ((pd[pd_index] & PAGE_HUGE) != 0) {
            return 0;
        }
        pt = page_table(pd[pd_index]);
        if ((pt[pt_index] & PAGE_OWNED) == 0) {
            const uint64_t frame = physical_page_allocate();
            if (frame == 0) {
                return 0;
            }
            pt[pt_index] = frame | PAGE_PRESENT | PAGE_USER | PAGE_OWNED | PAGE_NX;
        }
        if ((flags & TYPEPHP_VM_USER_NONE) != 0) {
            pt[pt_index] &= ~(uint64_t) PAGE_PRESENT;
        } else {
            pt[pt_index] |= PAGE_PRESENT;
        }
        if ((flags & TYPEPHP_VM_USER_WRITE) != 0) {
            pt[pt_index] |= PAGE_WRITE;
        }
        if ((flags & TYPEPHP_VM_USER_EXECUTE) != 0) {
            pt[pt_index] &= ~PAGE_NX;
        }
        if (typephp_vm_current() == address_space) {
            __asm__ volatile("invlpg (%0)" : : "r"((void *) (uintptr_t) cursor) : "memory");
        }
    }
    return 1;
}

int typephp_vm_user_range_free(
    uint64_t address_space, uint64_t address, uint64_t size)
{
    uint64_t begin;
    uint64_t end;
    uint64_t *pd = page_directory(address_space);
    if (pd == 0 || !align_range(address, size, &begin, &end)
        || begin < USER_ADDRESS_BEGIN || end > USER_ADDRESS_END) {
        return 0;
    }
    for (uint64_t cursor = begin; cursor < end; cursor += PAGE_SIZE) {
        const uint64_t pd_index = (cursor >> 21) & 0x1ffu;
        const uint64_t pt_index = (cursor >> 12) & 0x1ffu;
        if ((pd[pd_index] & PAGE_PRESENT) == 0) {
            continue;
        }
        if ((pd[pd_index] & PAGE_HUGE) != 0
            || (page_table(pd[pd_index])[pt_index] & PAGE_OWNED) != 0) {
            return 0;
        }
    }
    return 1;
}

int typephp_vm_unmap_user(
    uint64_t address_space, uint64_t address, uint64_t size)
{
    uint64_t begin;
    uint64_t end;
    uint64_t *pd = page_directory(address_space);
    if (pd == 0 || !align_range(address, size, &begin, &end)
        || begin < USER_ADDRESS_BEGIN || end > USER_ADDRESS_END) {
        return 0;
    }
    for (uint64_t cursor = begin; cursor < end; cursor += PAGE_SIZE) {
        const uint64_t pd_index = (cursor >> 21) & 0x1ffu;
        const uint64_t pt_index = (cursor >> 12) & 0x1ffu;
        if ((pd[pd_index] & PAGE_PRESENT) == 0 || (pd[pd_index] & PAGE_HUGE) != 0) {
            continue;
        }
        uint64_t *pt = page_table(pd[pd_index]);
        if ((pt[pt_index] & PAGE_OWNED) != 0) {
            physical_page_free(pt[pt_index] & PAGE_ADDRESS);
            pt[pt_index] = 0;
            if (typephp_vm_current() == address_space) {
                __asm__ volatile("invlpg (%0)" : : "r"((void *) (uintptr_t) cursor) : "memory");
            }
        }
    }
    return 1;
}

int typephp_vm_protect_user(
    uint64_t address_space, uint64_t address, uint64_t size, unsigned int flags)
{
    uint64_t begin;
    uint64_t end;
    uint64_t *pd = page_directory(address_space);
    if (pd == 0 || !align_range(address, size, &begin, &end)
        || begin < USER_ADDRESS_BEGIN || end > USER_ADDRESS_END) {
        return 0;
    }
    for (uint64_t cursor = begin; cursor < end; cursor += PAGE_SIZE) {
        const uint64_t pd_index = (cursor >> 21) & 0x1ffu;
        const uint64_t pt_index = (cursor >> 12) & 0x1ffu;
        if ((pd[pd_index] & PAGE_PRESENT) == 0 || (pd[pd_index] & PAGE_HUGE) != 0
            || (page_table(pd[pd_index])[pt_index] & PAGE_OWNED) == 0) {
            return 0;
        }
    }
    for (uint64_t cursor = begin; cursor < end; cursor += PAGE_SIZE) {
        uint64_t *pt = page_table(pd[(cursor >> 21) & 0x1ffu]);
        uint64_t *entry = &pt[(cursor >> 12) & 0x1ffu];
        *entry &= ~(uint64_t) (PAGE_PRESENT | PAGE_WRITE);
        *entry |= PAGE_NX;
        if ((flags & TYPEPHP_VM_USER_NONE) == 0) *entry |= PAGE_PRESENT;
        if ((flags & TYPEPHP_VM_USER_WRITE) != 0) *entry |= PAGE_WRITE;
        if ((flags & TYPEPHP_VM_USER_EXECUTE) != 0) *entry &= ~PAGE_NX;
        if (typephp_vm_current() == address_space) {
            __asm__ volatile("invlpg (%0)" : : "r"((void *) (uintptr_t) cursor) : "memory");
        }
    }
    return 1;
}

int typephp_vm_user_range(
    uint64_t address_space, uint64_t address, uint64_t size, int writable)
{
    uint64_t begin;
    uint64_t end;
    uint64_t *pd = page_directory(address_space);
    if (size == 0) {
        return 1;
    }
    if (pd == 0 || !align_range(address, size, &begin, &end)
        || begin < USER_ADDRESS_BEGIN || end > USER_ADDRESS_END) {
        return 0;
    }
    for (uint64_t cursor = begin; cursor < end; cursor += PAGE_SIZE) {
        const uint64_t pd_index = (cursor >> 21) & 0x1ffu;
        const uint64_t pt_index = (cursor >> 12) & 0x1ffu;
        if ((pd[pd_index] & (PAGE_PRESENT | PAGE_USER)) != (PAGE_PRESENT | PAGE_USER)
            || (pd[pd_index] & PAGE_HUGE) != 0) {
            return 0;
        }
        uint64_t *pt = page_table(pd[pd_index]);
        if ((pt[pt_index] & (PAGE_PRESENT | PAGE_USER)) != (PAGE_PRESENT | PAGE_USER)
            || (writable && (pt[pt_index] & PAGE_WRITE) == 0)) {
            return 0;
        }
    }
    return 1;
}

void typephp_vm_destroy(uint64_t address_space)
{
    uint64_t *pml4 = page_table(address_space);
    uint64_t *pdp = page_table(pml4[0]);
    uint64_t *pd = page_table(pdp[1]);
    for (uint64_t pd_index = USER_FIRST_PDE; pd_index <= USER_LAST_PDE; ++pd_index) {
        if ((pd[pd_index] & PAGE_PRESENT) == 0 || (pd[pd_index] & PAGE_HUGE) != 0) {
            continue;
        }
        uint64_t *pt = page_table(pd[pd_index]);
        for (uint64_t index = 0; index < 512; ++index) {
            if ((pt[index] & PAGE_OWNED) != 0) {
                physical_page_free(pt[index] & PAGE_ADDRESS);
            }
        }
        physical_page_free(pd[pd_index] & PAGE_ADDRESS);
    }
    physical_page_free(pdp[1] & PAGE_ADDRESS);
    physical_page_free(pdp[0] & PAGE_ADDRESS);
    physical_page_free(pml4[0] & PAGE_ADDRESS);
    physical_page_free(address_space);
}

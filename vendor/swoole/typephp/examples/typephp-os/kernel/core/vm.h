#ifndef TYPEPHP_OS_VM_H
#define TYPEPHP_OS_VM_H

#include <stddef.h>
#include <stdint.h>

enum {
    TYPEPHP_VM_USER_WRITE = 1u << 0,
    TYPEPHP_VM_USER_EXECUTE = 1u << 1,
    TYPEPHP_VM_USER_NONE = 1u << 2,
};

uint64_t typephp_vm_create(void);
void typephp_vm_destroy(uint64_t address_space);
void typephp_vm_activate(uint64_t address_space);
uint64_t typephp_vm_current(void);
void *typephp_vm_map_mmio(uint64_t physical_address, uint64_t size);
int typephp_vm_map_user(
    uint64_t address_space, uint64_t address, uint64_t size, unsigned int flags);
int typephp_vm_user_range(
    uint64_t address_space, uint64_t address, uint64_t size, int writable);
int typephp_vm_user_range_free(
    uint64_t address_space, uint64_t address, uint64_t size);
int typephp_vm_unmap_user(
    uint64_t address_space, uint64_t address, uint64_t size);
int typephp_vm_protect_user(
    uint64_t address_space, uint64_t address, uint64_t size, unsigned int flags);

#endif

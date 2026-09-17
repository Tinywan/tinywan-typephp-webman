BUILD := build
OBJECT_DIR := $(BUILD)/objects
BOOTSTRAP_OBJECT := $(OBJECT_DIR)/bootstrap.o
KERNEL64_ENTRY_OBJECT := $(OBJECT_DIR)/kernel64-entry.o
PAYLOAD_ELF := $(BUILD)/kernel64.elf
PAYLOAD_BIN := $(BUILD)/kernel64.bin
PAYLOAD_OBJECT := $(OBJECT_DIR)/kernel64-payload.o
KERNEL := $(BUILD)/typephp-os.elf
DISK := $(BUILD)/typephp-os.img
USER_COMMANDS := ls cd date uname pwd cat echo write touch mkdir rm rmdir mv fault vmfault wrfault memtest systest nettest builtins
USER_PROGRAMS := sh $(USER_COMMANDS)
USER_SH_ELF := $(BUILD)/sh.elf
USER_COMMAND_ELFS := $(addprefix $(BUILD)/,$(addsuffix .elf,$(USER_COMMANDS)))
NANO_USER_ELF := $(BUILD)/tnhello.elf
TYPEPHP_FREE_ELF := $(BUILD)/free.elf
TYPEPHP_HTTPS_ELF := $(BUILD)/https.elf
CA_BUNDLE ?= /etc/ssl/certs/ca-certificates.crt
TYPEPHP_OS_LIBRARY := $(BUILD)/libtypephp-os.a
TYPEPHP_OS_LIBRARY_OBJECT_DIR := $(BUILD)/libtypephp-os
COMPILER_RT_LIBRARY := $(BUILD)/libcompiler-rt-builtins.a
COMPILER_RT_OBJECT_DIR := $(BUILD)/compiler-rt-builtins
PHP_NANO_DIR := $(or $(realpath ../../vendor/swoole/php-nano),$(realpath ../../../php-nano))
PHPX_DIR := $(or $(realpath ../../vendor/swoole/phpx),$(realpath ../../../phpx))
OPENLIBM_SOURCES := $(shell sed -n 's|^\(.*\.c\)$$|thirdparty/openlibm/\1|p' tools/openlibm-files.txt)
COMPILER_RT_SOURCES := $(shell awk '$$2 ~ /\.c$$/ { print "thirdparty/compiler-rt/" $$2 }' tools/compiler-rt-builtins-files.sha256)
COMPILER_RT_OBJECTS := $(patsubst thirdparty/compiler-rt/lib/builtins/%.c,$(COMPILER_RT_OBJECT_DIR)/%.o,$(COMPILER_RT_SOURCES))
TYPEPHP_OS_LIBRARY_C_SOURCES := user/runtime/posix.c user/runtime/dirent.c \
	user/runtime/fcntl.c user/runtime/ioctl.c user/runtime/network.c \
	user/runtime/glibc.c user/runtime/stdio.c user/runtime/start.c \
	kernel/core/abi/error.c kernel/core/abi/string.c \
	user/runtime/memory.c kernel/core/abi/libc.c $(OPENLIBM_SOURCES)
TYPEPHP_OS_LIBRARY_CXX_SOURCES := user/runtime/host.cc kernel/core/abi/cxx.cpp
TYPEPHP_OS_LIBRARY_ASM_SOURCES := user/runtime/crt0.S user/runtime/syscall.S \
	kernel/core/abi/unimplemented.S
TYPEPHP_OS_LIBRARY_OBJECTS := \
	$(patsubst %.c,$(TYPEPHP_OS_LIBRARY_OBJECT_DIR)/%.o,$(TYPEPHP_OS_LIBRARY_C_SOURCES)) \
	$(patsubst %.cc,$(TYPEPHP_OS_LIBRARY_OBJECT_DIR)/%.o,$(filter %.cc,$(TYPEPHP_OS_LIBRARY_CXX_SOURCES))) \
	$(patsubst %.cpp,$(TYPEPHP_OS_LIBRARY_OBJECT_DIR)/%.o,$(filter %.cpp,$(TYPEPHP_OS_LIBRARY_CXX_SOURCES))) \
	$(patsubst %.S,$(TYPEPHP_OS_LIBRARY_OBJECT_DIR)/%.o,$(TYPEPHP_OS_LIBRARY_ASM_SOURCES))
TYPEPHP_OS_LIBRARY_DEPFILES := $(addsuffix .d,$(TYPEPHP_OS_LIBRARY_OBJECTS))
NANO_USER_SOURCES := user/nano/hello.php user/nano/hello.yml user/runtime/linker.ld \
	$(TYPEPHP_OS_LIBRARY) $(COMPILER_RT_LIBRARY)
TYPEPHP_FREE_SOURCES := user/nano/free.php user/nano/memory.stub.php \
	user/nano/free.cc user/nano/free.yml user/runtime/linker.ld \
	$(TYPEPHP_OS_LIBRARY) $(COMPILER_RT_LIBRARY)
TYPEPHP_HTTPS_SOURCES := user/nano/https.php user/nano/https.yml \
	user/runtime/linker.ld $(TYPEPHP_OS_LIBRARY) $(COMPILER_RT_LIBRARY)
TPC := ./vendor/bin/tpc.php

BOOTFLAGS := -m32 -O2 -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=0 -ffreestanding -fno-pic -fno-pie \
	-fno-stack-protector -Wall -Wextra
KERNEL64_BOOTFLAGS := -m64 -O2 -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=0 -ffreestanding -fno-builtin -fno-pic -fno-pie \
	-fno-stack-protector -mno-red-zone -mcmodel=small -Wall -Wextra
USER_CFLAGS := -m64 -O2 -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=0 -ffreestanding -fno-builtin -fno-pic -fno-pie \
	-fno-stack-protector -mno-red-zone -Wall -Wextra -Iuser/include -Ikernel/core/abi
USER_HEADERS := $(shell find user/include -type f)
USER_LDFLAGS := -m elf_x86_64 -nostdlib -static --undefined=_start \
	-T user/runtime/linker.ld
TYPEPHP_OS_RUNTIME_DEFINES := -DTYPEPHP_NATIVE=1 -DPHP_NANO=1 -DPHPX_NANO=1 \
	-D_POSIX_C_SOURCE=200809L -DTYPEPHP_NO_MAIN=1 -DPHPX_NO_EXCEPTION=1 \
	-DPHPX_NO_RTTI=1 -DPHP_NANO_NO_LIBC=1 -DHAVE_SYS_UTSNAME_H=1 \
	-D__NO_CTYPE=1 -DZEND_MM_ERROR=0 -DZEND_MM_CUSTOM=0 -DASSEMBLER=1 \
	-D__BSD_VISIBLE=1
TYPEPHP_OS_RUNTIME_INCLUDES := -Ikernel/core/abi \
	-Ithirdparty/openlibm/include -Ithirdparty/openlibm/src \
	-Ithirdparty/openlibm/amd64 -I$(PHP_NANO_DIR) -I$(PHP_NANO_DIR)/Zend \
	-I$(PHP_NANO_DIR)/include -I$(PHP_NANO_DIR)/main -I$(PHP_NANO_DIR)/TSRM \
	-I$(PHP_NANO_DIR)/ext/date/lib -I$(PHP_NANO_DIR)/ext/pcre/pcre2lib \
	-I$(PHPX_DIR)/include -I$(PHPX_DIR)/thirdparty/wren-gc/include
TYPEPHP_OS_RUNTIME_CFLAGS := -m64 -O2 -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=0 \
	-std=c11 -ffreestanding -fno-builtin \
	-fno-pic -fno-pie -fno-stack-protector -mno-red-zone -mcmodel=small \
	-ffunction-sections -fdata-sections -Wall -Wextra \
	$(TYPEPHP_OS_RUNTIME_DEFINES) $(TYPEPHP_OS_RUNTIME_INCLUDES)
TYPEPHP_OS_RUNTIME_CXXFLAGS := -m64 -O2 -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=0 \
	-std=c++17 -ffreestanding -fno-builtin \
	-fno-pic -fno-pie -fno-stack-protector -mno-red-zone -mcmodel=small \
	-fno-rtti -fno-threadsafe-statics -ffunction-sections -fdata-sections \
	-Wall -Wextra \
	$(TYPEPHP_OS_RUNTIME_DEFINES) $(TYPEPHP_OS_RUNTIME_INCLUDES)
TYPEPHP_OS_RUNTIME_ASMFLAGS := -m64 -ffreestanding -fno-pic -fno-pie \
	-fno-stack-protector -mno-red-zone -mcmodel=small
ARFLAGS := rcs

# Keep symbol information on the host while installing only compact runtime
# ELF files into the FAT16 image. GDB can discover the adjacent .debug file
# through the GNU debug link retained in the stripped executable.
define STRIP_ELF
	objcopy --only-keep-debug $(1) $(1).debug
	strip --strip-all $(1)
	objcopy --add-gnu-debuglink=$(1).debug $(1)
endef

.PHONY: all thirdparty thirdparty-smoke network-thirdparty-smoke compiler-rt-builtins payload objects user-runtime nano-user disk run test clean

all: $(KERNEL) $(DISK)

thirdparty:
	./tools/fetch-thirdparty.sh

thirdparty-smoke: thirdparty compiler-rt-builtins
	./tools/test-thirdparty.sh

network-thirdparty-smoke:
	./tools/build-network-thirdparty.sh

compiler-rt-builtins: $(COMPILER_RT_LIBRARY)

payload: thirdparty objects $(COMPILER_RT_LIBRARY)
	$(TPC) --nano project.yml
	$(call STRIP_ELF,$(PAYLOAD_ELF))

# Startup sources are built explicitly by Make. The 64-bit entry object is
# injected into tpc's final link through project.yml; the 32-bit Multiboot
# object participates only in the packaging link.
objects: $(BOOTSTRAP_OBJECT) $(KERNEL64_ENTRY_OBJECT)

user-runtime: $(TYPEPHP_OS_LIBRARY)

nano-user: $(TYPEPHP_OS_LIBRARY) $(NANO_USER_ELF) $(TYPEPHP_FREE_ELF) $(TYPEPHP_HTTPS_ELF)

disk: $(DISK)

$(DISK): $(USER_SH_ELF) $(USER_COMMAND_ELFS) $(NANO_USER_ELF) $(TYPEPHP_FREE_ELF) $(TYPEPHP_HTTPS_ELF) | $(BUILD)
	truncate -s 32M $@
	mkfs.fat -F 16 -n TYPEPHPOS $@
	mmd -i $@ ::/BIN
	for program in $(USER_PROGRAMS); do \
		mcopy -o -i $@ $(BUILD)/$$program.elf ::/BIN/$$program.elf; \
	done
	mcopy -o -i $@ $(NANO_USER_ELF) ::/BIN/TNHELLO.ELF
	mcopy -o -i $@ $(TYPEPHP_FREE_ELF) ::/BIN/FREE.ELF
	mcopy -o -i $@ $(TYPEPHP_HTTPS_ELF) ::/BIN/HTTPS.ELF
	mcopy -o -i $@ $(CA_BUNDLE) ::/CACERT.PEM

$(BUILD):
	mkdir -p $@

$(OBJECT_DIR):
	mkdir -p $@

$(BOOTSTRAP_OBJECT): boot/boot.S | $(OBJECT_DIR)
	gcc $(BOOTFLAGS) -c $< -o $@

$(KERNEL64_ENTRY_OBJECT): boot/kernel64-entry.S | $(OBJECT_DIR)
	gcc $(KERNEL64_BOOTFLAGS) -c $< -o $@

$(OBJECT_DIR)/%.o: user/cmd/%.c $(USER_HEADERS) kernel/core/abi/typephp_os_syscall.h | $(OBJECT_DIR)
	gcc $(USER_CFLAGS) -c $< -o $@

$(OPENLIBM_SOURCES): | thirdparty

$(COMPILER_RT_SOURCES): | thirdparty

$(COMPILER_RT_OBJECT_DIR)/%.o: thirdparty/compiler-rt/lib/builtins/%.c Makefile
	@mkdir -p $(@D)
	gcc $(USER_CFLAGS) -std=c11 -Ithirdparty/compiler-rt/lib/builtins -c $< -o $@

$(COMPILER_RT_LIBRARY): $(COMPILER_RT_OBJECTS) | $(BUILD)
	$(AR) $(ARFLAGS) $@ $^

$(TYPEPHP_OS_LIBRARY_OBJECT_DIR)/%.o: %.c Makefile
	@mkdir -p $(@D)
	gcc $(TYPEPHP_OS_RUNTIME_CFLAGS) -MMD -MP -MF $@.d -c $< -o $@

$(TYPEPHP_OS_LIBRARY_OBJECT_DIR)/user/runtime/network.o: user/runtime/network.c Makefile $(USER_HEADERS)
	@mkdir -p $(@D)
	gcc -Iuser/include $(TYPEPHP_OS_RUNTIME_CFLAGS) -MMD -MP -MF $@.d -c $< -o $@

$(TYPEPHP_OS_LIBRARY_OBJECT_DIR)/%.o: %.cc Makefile
	@mkdir -p $(@D)
	g++ $(TYPEPHP_OS_RUNTIME_CXXFLAGS) -MMD -MP -MF $@.d -c $< -o $@

$(TYPEPHP_OS_LIBRARY_OBJECT_DIR)/%.o: %.cpp Makefile
	@mkdir -p $(@D)
	g++ $(TYPEPHP_OS_RUNTIME_CXXFLAGS) -MMD -MP -MF $@.d -c $< -o $@

$(TYPEPHP_OS_LIBRARY_OBJECT_DIR)/%.o: %.S Makefile
	@mkdir -p $(@D)
	gcc $(TYPEPHP_OS_RUNTIME_ASMFLAGS) -MMD -MP -MF $@.d -c $< -o $@

$(TYPEPHP_OS_LIBRARY): $(TYPEPHP_OS_LIBRARY_OBJECTS) | $(BUILD)
	$(AR) $(ARFLAGS) $@ $^

$(NANO_USER_ELF): $(NANO_USER_SOURCES) | thirdparty $(BUILD)
	$(TPC) --nano user/nano/hello.yml
	$(call STRIP_ELF,$@)

$(TYPEPHP_FREE_ELF): $(TYPEPHP_FREE_SOURCES) | thirdparty $(BUILD)
	$(TPC) --nano user/nano/free.yml
	$(call STRIP_ELF,$@)

$(TYPEPHP_HTTPS_ELF): $(TYPEPHP_HTTPS_SOURCES) | network-thirdparty-smoke thirdparty $(BUILD)
	$(TPC) --nano user/nano/https.yml
	$(call STRIP_ELF,$@)

$(USER_SH_ELF): $(OBJECT_DIR)/sh.o $(TYPEPHP_OS_LIBRARY) $(COMPILER_RT_LIBRARY) user/runtime/linker.ld Makefile | $(BUILD)
	ld $(USER_LDFLAGS) --defsym USER_BASE=0x40000000 -o $@ \
		$(OBJECT_DIR)/sh.o $(TYPEPHP_OS_LIBRARY) $(COMPILER_RT_LIBRARY)
	$(call STRIP_ELF,$@)

$(USER_COMMAND_ELFS): $(BUILD)/%.elf: $(OBJECT_DIR)/%.o $(TYPEPHP_OS_LIBRARY) $(COMPILER_RT_LIBRARY) user/runtime/linker.ld Makefile | $(BUILD)
	ld $(USER_LDFLAGS) --defsym USER_BASE=0x41000000 -o $@ \
		$(OBJECT_DIR)/$*.o $(TYPEPHP_OS_LIBRARY) $(COMPILER_RT_LIBRARY)
	$(call STRIP_ELF,$@)

$(KERNEL): payload
	objcopy -O binary $(PAYLOAD_ELF) $(PAYLOAD_BIN)
	objcopy -I binary -O elf32-i386 -B i386 \
		--rename-section .data=.payload,alloc,load,readonly,data,contents \
		$(PAYLOAD_BIN) $(PAYLOAD_OBJECT)
	ld -m elf_i386 -T boot/linker.ld -o $@ \
		$(BOOTSTRAP_OBJECT) $(PAYLOAD_OBJECT)
	$(call STRIP_ELF,$@)

run: all
	qemu-system-x86_64 -cpu max -m 512M -kernel $(KERNEL) \
		-drive file=$(DISK),format=raw,if=ide,index=0 \
		-netdev user,id=net0 -device e1000,netdev=net0 \
		-display none -serial stdio -monitor none -no-reboot -no-shutdown

test: $(KERNEL) $(USER_SH_ELF) $(USER_COMMAND_ELFS) $(NANO_USER_ELF) $(TYPEPHP_FREE_ELF) $(TYPEPHP_HTTPS_ELF)
	truncate -s 32M $(DISK)
	mkfs.fat -F 16 -n TYPEPHPOS $(DISK)
	mmd -i $(DISK) ::/BIN
	for program in $(USER_PROGRAMS); do \
		mcopy -o -i $(DISK) $(BUILD)/$$program.elf ::/BIN/$$program.elf; \
	done
	mcopy -o -i $(DISK) $(NANO_USER_ELF) ::/BIN/TNHELLO.ELF
	mcopy -o -i $(DISK) $(TYPEPHP_FREE_ELF) ::/BIN/FREE.ELF
	mcopy -o -i $(DISK) $(TYPEPHP_HTTPS_ELF) ::/BIN/HTTPS.ELF
	mcopy -o -i $(DISK) $(CA_BUNDLE) ::/CACERT.PEM
	# An alias not known while linking the kernel verifies disk-based discovery.
	mcopy -o -i $(DISK) $(BUILD)/echo.elf ::/BIN/hello.elf
	mcopy -o -i $(DISK) tools/invalid-elf.txt ::/BIN/bad.elf
	# Fill one directory cluster (dot entries plus 62 files). The QEMU test
	# creates F62.TXT and therefore exercises directory-chain growth once.
	mmd -i $(DISK) ::/EXPAND
	for index in $$(seq -w 0 61); do \
		mcopy -o -i $(DISK) tools/invalid-elf.txt ::/EXPAND/F$$index.TXT; \
	done
	bash tools/test-qemu.sh $(KERNEL) $(DISK) $(BUILD)/qemu.log

clean:
	rm -rf $(BUILD)

-include $(TYPEPHP_OS_LIBRARY_DEPFILES)

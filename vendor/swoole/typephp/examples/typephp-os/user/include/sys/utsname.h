#ifndef TYPEPHP_OS_USER_SYS_UTSNAME_H
#define TYPEPHP_OS_USER_SYS_UTSNAME_H

#define _UTSNAME_LENGTH 65

/* Keep the Linux new_utsname layout so the syscall remains compatible with
 * the interface expected by a future glibc userspace. POSIX exposes the first
 * five fields; domainname is the Linux extension at the end of the ABI. */
struct utsname {
    char sysname[_UTSNAME_LENGTH];
    char nodename[_UTSNAME_LENGTH];
    char release[_UTSNAME_LENGTH];
    char version[_UTSNAME_LENGTH];
    char machine[_UTSNAME_LENGTH];
    char domainname[_UTSNAME_LENGTH];
};

int uname(struct utsname *value);

#endif

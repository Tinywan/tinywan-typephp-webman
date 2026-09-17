#ifndef TYPEPHP_OS_USER_DIRENT_H
#define TYPEPHP_OS_USER_DIRENT_H

#include <sys/types.h>

/* Linux x86-64 dirent64 layout. getdents64() returns variable-length records;
 * readdir() exposes the same prefix through this conventional fixed-size
 * userspace view. */
struct dirent {
    ino_t d_ino;
    off_t d_off;
    unsigned short d_reclen;
    unsigned char d_type;
    char d_name[256];
};

#define DT_UNKNOWN 0
#define DT_FIFO 1
#define DT_CHR 2
#define DT_DIR 4
#define DT_BLK 6
#define DT_REG 8
#define DT_LNK 10
#define DT_SOCK 12

typedef struct __typephp_dir_stream DIR;

DIR *opendir(const char *path);
DIR *fdopendir(int fd);
struct dirent *readdir(DIR *directory);
int closedir(DIR *directory);
void rewinddir(DIR *directory);
int dirfd(DIR *directory);

#endif

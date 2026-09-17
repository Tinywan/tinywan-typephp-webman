#include <dirent.h>
#include <errno.h>
#include <fcntl.h>
#include <stddef.h>
#include <stdint.h>
#include <sys/stat.h>
#include <sys/syscall.h>
#include <unistd.h>

/* glibc hides this GNU extension when php-nano requests POSIX.1-2008 only. */
long syscall(long number, ...);

#ifndef O_DIRECTORY
#define O_DIRECTORY 65536
#endif

#ifndef O_CLOEXEC
#define O_CLOEXEC 524288
#endif

enum {
    TYPEPHP_DIRECTORY_STREAMS = 4,
    TYPEPHP_DIRECTORY_BUFFER_SIZE = 1024,
};

typedef struct {
    int used;
    int fd;
    size_t offset;
    size_t length;
    unsigned char buffer[TYPEPHP_DIRECTORY_BUFFER_SIZE];
    struct dirent entry;
} typephp_directory_stream;

static typephp_directory_stream directory_streams[TYPEPHP_DIRECTORY_STREAMS];

static typephp_directory_stream *directory_state(DIR *directory)
{
    const uintptr_t address = (uintptr_t) directory;
    const uintptr_t begin = (uintptr_t) directory_streams;
    const uintptr_t end = (uintptr_t) (directory_streams
        + TYPEPHP_DIRECTORY_STREAMS);
    typephp_directory_stream *state;
    if (address < begin || address >= end
        || (address - begin) % sizeof(typephp_directory_stream) != 0) {
        errno = EBADF;
        return NULL;
    }
    state = (typephp_directory_stream *) directory;
    if (!state->used) {
        errno = EBADF;
        return NULL;
    }
    return state;
}

DIR *fdopendir(int fd)
{
    struct stat status;
    size_t index;
    if (fstat(fd, &status) != 0) {
        return NULL;
    }
    if (!S_ISDIR(status.st_mode)) {
        errno = ENOTDIR;
        return NULL;
    }
    for (index = 0; index < TYPEPHP_DIRECTORY_STREAMS; ++index) {
        typephp_directory_stream *state = &directory_streams[index];
        if (!state->used) {
            state->used = 1;
            state->fd = fd;
            state->offset = 0;
            state->length = 0;
            return (DIR *) state;
        }
    }
    errno = EMFILE;
    return NULL;
}

DIR *opendir(const char *path)
{
    int fd = open(path, O_RDONLY | O_DIRECTORY | O_CLOEXEC);
    DIR *directory;
    if (fd < 0) {
        return NULL;
    }
    directory = fdopendir(fd);
    if (directory == NULL) {
        int saved_errno = errno;
        (void) close(fd);
        errno = saved_errno;
    }
    return directory;
}

struct dirent *readdir(DIR *directory)
{
    typephp_directory_stream *state = directory_state(directory);
    struct dirent *record;
    size_t name_capacity;
    size_t name_length = 0;
    if (state == NULL) {
        return NULL;
    }
    if (state->offset == state->length) {
        long length = syscall(SYS_getdents64, state->fd,
            state->buffer, sizeof(state->buffer));
        if (length <= 0) {
            return NULL;
        }
        state->offset = 0;
        state->length = (size_t) length;
    }
    record = (struct dirent *) (state->buffer + state->offset);
    if (record->d_reclen < offsetof(struct dirent, d_name) + 1
        || record->d_reclen > state->length - state->offset) {
        errno = EIO;
        return NULL;
    }
    name_capacity = record->d_reclen - offsetof(struct dirent, d_name);
    while (name_length < name_capacity
        && record->d_name[name_length] != '\0') {
        ++name_length;
    }
    if (name_length == name_capacity
        || name_length >= sizeof(state->entry.d_name)) {
        errno = EIO;
        return NULL;
    }
    state->entry.d_ino = record->d_ino;
    state->entry.d_off = record->d_off;
    state->entry.d_reclen = record->d_reclen;
    state->entry.d_type = record->d_type;
    for (size_t index = 0; index <= name_length; ++index) {
        state->entry.d_name[index] = record->d_name[index];
    }
    state->offset += record->d_reclen;
    return &state->entry;
}

int closedir(DIR *directory)
{
    typephp_directory_stream *state = directory_state(directory);
    int result;
    if (state == NULL) {
        return -1;
    }
    result = close(state->fd);
    state->used = 0;
    state->fd = -1;
    state->offset = 0;
    state->length = 0;
    return result;
}

void rewinddir(DIR *directory)
{
    typephp_directory_stream *state = directory_state(directory);
    if (state != NULL && lseek(state->fd, 0, SEEK_SET) >= 0) {
        state->offset = 0;
        state->length = 0;
    }
}

int dirfd(DIR *directory)
{
    typephp_directory_stream *state = directory_state(directory);
    return state == NULL ? -1 : state->fd;
}

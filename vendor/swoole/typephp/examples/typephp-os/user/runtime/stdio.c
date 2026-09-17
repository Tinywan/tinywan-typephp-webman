/* Minimal descriptor-backed stdio used by OpenSSL certificate loading. */

#include <errno.h>
#include <fcntl.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

typedef struct {
    uint32_t magic;
    int fd;
    int error;
    int eof;
} typephp_file;

enum { TYPEPHP_FILE_MAGIC = 0x54504649u };

static typephp_file *private_file(FILE *stream)
{
    typephp_file *file = (typephp_file *) stream;
    return file != 0 && file->magic == TYPEPHP_FILE_MAGIC ? file : 0;
}

static int mode_flags(const char *mode)
{
    int flags;
    if (mode == 0 || *mode == '\0') {
        return -1;
    }
    flags = *mode == 'r' ? O_RDONLY
        : (*mode == 'w' ? O_WRONLY | O_CREAT | O_TRUNC
        : (*mode == 'a' ? O_WRONLY | O_CREAT | O_APPEND : -1));
    if (flags >= 0 && mode[1] == '+') {
        flags = (flags & ~(O_RDONLY | O_WRONLY)) | O_RDWR;
    }
    return flags;
}

FILE *fopen(const char *path, const char *mode)
{
    const int flags = mode_flags(mode);
    typephp_file *file;
    int fd;
    if (flags < 0) {
        errno = EINVAL;
        return 0;
    }
    fd = open(path, flags, 0666);
    if (fd < 0) {
        return 0;
    }
    file = (typephp_file *) malloc(sizeof(*file));
    if (file == 0) {
        close(fd);
        errno = ENOMEM;
        return 0;
    }
    file->magic = TYPEPHP_FILE_MAGIC;
    file->fd = fd;
    file->error = 0;
    file->eof = 0;
    return (FILE *) file;
}

FILE *fopen64(const char *path, const char *mode)
{
    return fopen(path, mode);
}

int fclose(FILE *stream)
{
    typephp_file *file = private_file(stream);
    int status;
    if (file == 0) {
        errno = EBADF;
        return EOF;
    }
    status = close(file->fd);
    file->magic = 0;
    free(file);
    return status;
}

size_t fread(void *buffer, size_t size, size_t count, FILE *stream)
{
    typephp_file *file = private_file(stream);
    size_t bytes;
    ssize_t received;
    if (file == 0 || (size != 0 && count > SIZE_MAX / size)) {
        errno = EINVAL;
        return 0;
    }
    bytes = size * count;
    received = read(file->fd, buffer, bytes);
    if (received < 0) {
        file->error = 1;
        return 0;
    }
    if ((size_t) received < bytes) {
        file->eof = 1;
    }
    return size == 0 ? 0 : (size_t) received / size;
}

char *fgets(char *buffer, int size, FILE *stream)
{
    typephp_file *file = private_file(stream);
    int offset = 0;
    if (file == 0 || buffer == 0 || size <= 0) {
        errno = EINVAL;
        return 0;
    }
    while (offset + 1 < size) {
        ssize_t received = read(file->fd, buffer + offset, 1);
        if (received < 0) {
            file->error = 1;
            return offset == 0 ? 0 : buffer;
        }
        if (received == 0) {
            file->eof = 1;
            break;
        }
        if (buffer[offset++] == '\n') {
            break;
        }
    }
    if (offset == 0) {
        return 0;
    }
    buffer[offset] = '\0';
    return buffer;
}

int ferror(FILE *stream)
{
    typephp_file *file = private_file(stream);
    return file != 0 ? file->error : 1;
}

int feof(FILE *stream)
{
    typephp_file *file = private_file(stream);
    return file != 0 ? file->eof : 0;
}

void clearerr(FILE *stream)
{
    typephp_file *file = private_file(stream);
    if (file != 0) {
        file->error = 0;
        file->eof = 0;
    }
}

int fseeko(FILE *stream, off_t offset, int whence)
{
    typephp_file *file = private_file(stream);
    if (file == 0) {
        errno = EBADF;
        return -1;
    }
    file->eof = 0;
    return lseek(file->fd, offset, whence) < 0 ? -1 : 0;
}

off_t ftello(FILE *stream)
{
    typephp_file *file = private_file(stream);
    if (file == 0) {
        errno = EBADF;
        return -1;
    }
    return lseek(file->fd, 0, SEEK_CUR);
}

int fseek(FILE *stream, long offset, int whence)
{
    return fseeko(stream, (off_t) offset, whence);
}

long ftell(FILE *stream)
{
    return (long) ftello(stream);
}

void rewind(FILE *stream)
{
    (void) fseeko(stream, 0, SEEK_SET);
    clearerr(stream);
}

void setbuf(FILE *stream, char *buffer)
{
    (void) stream;
    (void) buffer;
}

size_t __fread_chk(void *buffer, size_t buffer_size, size_t size,
    size_t count, FILE *stream)
{
    if (size != 0 && count > buffer_size / size) {
        errno = ERANGE;
        return 0;
    }
    return fread(buffer, size, count, stream);
}

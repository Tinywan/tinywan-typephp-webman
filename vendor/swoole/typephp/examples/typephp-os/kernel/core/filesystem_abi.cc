/*
   +----------------------------------------------------------------------+
   | TypePHP OS                                                          |
   +----------------------------------------------------------------------+
   | POSIX ABI forwarding layer for the TypePHP FAT16 implementation.    |
   | SPDX-License-Identifier: BSD-3-Clause                               |
   +----------------------------------------------------------------------+
*/

#include <phpx.h>
#include <php_kernel64_func_decl.h>
#include <typephp_os_abi.h>

#include <cerrno>
#include <cstdarg>
#include <cstddef>
#include <cstdint>
#include <cstring>
#include <dirent.h>
#include <fcntl.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>
#include <utility>

namespace {

php::Object installed_filesystem;

bool filesystem_installed()
{
    return installed_filesystem.isObject();
}

php::Int fs_open(php::Str path, php::Int flags)
{
    return filesystem_installed() ? php_kernelfilesystem__open(installed_filesystem, path, flags) : -EIO;
}

php::Int fs_close(php::Int fd)
{
    return filesystem_installed() ? php_kernelfilesystem__close(installed_filesystem, fd) : -EIO;
}

php::Str fs_read(php::Int fd, php::Int count)
{
    return filesystem_installed() ? php_kernelfilesystem__read(installed_filesystem, fd, count) : php::Str{};
}

php::Int fs_write(php::Int fd, php::Str data)
{
    return filesystem_installed() ? php_kernelfilesystem__write(installed_filesystem, fd, data) : -EIO;
}

php::Int fs_seek(php::Int fd, php::Int offset, php::Int whence)
{
    return filesystem_installed() ? php_kernelfilesystem__seek(installed_filesystem, fd, offset, whence) : -EIO;
}

php::Int fs_flush(php::Int fd)
{
    return filesystem_installed() ? php_kernelfilesystem__flush(installed_filesystem, fd) : -EIO;
}

php::Int fs_truncate(php::Int fd, php::Int size)
{
    return filesystem_installed() ? php_kernelfilesystem__truncate(installed_filesystem, fd, size) : -EIO;
}

php::Int fs_size(php::Str path)
{
    return filesystem_installed() ? php_kernelfilesystem__filesize(installed_filesystem, path) : -EIO;
}

php::Int fs_fd_size(php::Int fd)
{
    return filesystem_installed() ? php_kernelfilesystem__fdsize(installed_filesystem, fd) : -EIO;
}

php::Int fs_type(php::Str path)
{
    return filesystem_installed()
        ? php_kernelfilesystem__pathtype(installed_filesystem, path)
        : 0;
}

php::Int fs_mkdir(php::Str path)
{
    return filesystem_installed() ? php_kernelfilesystem__makedirectory(installed_filesystem, path) : -EIO;
}

php::Int fs_rmdir(php::Str path)
{
    return filesystem_installed() ? php_kernelfilesystem__removedirectory(installed_filesystem, path) : -EIO;
}

php::Int fs_unlink(php::Str path)
{
    return filesystem_installed() ? php_kernelfilesystem__removefile(installed_filesystem, path) : -EIO;
}

php::Int fs_rename(php::Str old_path, php::Str new_path)
{
    return filesystem_installed()
        ? php_kernelfilesystem__rename(installed_filesystem, old_path, new_path)
        : -EIO;
}

php::Str fs_entries(php::Str path)
{
    return filesystem_installed() ? php_kernelfilesystem__entries(installed_filesystem, path) : php::Str{};
}

int posix_result(php::Int result)
{
    if (result >= 0) {
        return static_cast<int>(result);
    }
    errno = static_cast<int>(-result);
    return -1;
}

off_t posix_offset(php::Int result)
{
    if (result >= 0) {
        return static_cast<off_t>(result);
    }
    errno = static_cast<int>(-result);
    return static_cast<off_t>(-1);
}

void fill_stat(struct stat *value, php::Int type, php::Int size)
{
    std::memset(value, 0, sizeof(*value));
    value->st_mode = (type == 2 ? S_IFDIR | 0777 : S_IFREG | 0666);
    value->st_nlink = 1;
    value->st_size = static_cast<off_t>(size);
    value->st_blksize = 512;
    value->st_blocks = static_cast<blkcnt_t>((size + 511) / 512);
}

struct DirectoryState {
    php::Str entries;
    std::size_t offset = 0;
    struct dirent entry{};

    explicit DirectoryState(php::Str value) : entries(std::move(value)) {}
};

enum {
    DIRECTORY_FD_BEGIN = 64,
    DIRECTORY_FD_COUNT = 8,
    LINUX_O_ACCMODE = 3,
    LINUX_O_CREAT = 64,
    LINUX_O_TRUNC = 512,
    LINUX_O_APPEND = 1024,
    LINUX_O_NONBLOCK = 2048,
    LINUX_O_DIRECTORY = 65536,
    LINUX_O_CLOEXEC = 524288,
    LINUX_F_GETFD = 1,
    LINUX_F_SETFD = 2,
    LINUX_F_GETFL = 3,
    LINUX_F_SETFL = 4,
    LINUX_FD_CLOEXEC = 1,
};

DirectoryState *open_directories[DIRECTORY_FD_COUNT]{};
int descriptor_status_flags[DIRECTORY_FD_BEGIN + DIRECTORY_FD_COUNT]{};
int descriptor_fd_flags[DIRECTORY_FD_BEGIN + DIRECTORY_FD_COUNT]{};

DirectoryState *directory_state(int fd)
{
    if (fd < DIRECTORY_FD_BEGIN
        || fd >= DIRECTORY_FD_BEGIN + DIRECTORY_FD_COUNT) {
        return nullptr;
    }
    return open_directories[fd - DIRECTORY_FD_BEGIN];
}

int open_directory(php::Str path, int flags)
{
    for (int index = 0; index < DIRECTORY_FD_COUNT; ++index) {
        if (open_directories[index] == nullptr) {
            open_directories[index] = new DirectoryState(fs_entries(std::move(path)));
            const int fd = DIRECTORY_FD_BEGIN + index;
            descriptor_status_flags[fd] = O_RDONLY | LINUX_O_DIRECTORY;
            descriptor_fd_flags[fd] =
                (flags & LINUX_O_CLOEXEC) != 0 ? LINUX_FD_CLOEXEC : 0;
            return fd;
        }
    }
    errno = EMFILE;
    return -1;
}

struct LinuxDirent64 {
    std::uint64_t inode;
    std::int64_t offset;
    std::uint16_t record_length;
    std::uint8_t type;
    char name[1];
};

static_assert(offsetof(LinuxDirent64, name) == 19,
    "Linux x86-64 dirent64 prefix layout changed");

} // namespace

php::Bool php_kernel_fs_install(php::Object filesystem)
{
    installed_filesystem = filesystem;
    return installed_filesystem.isObject();
}

extern "C" long typephp_os_fs_path_type(const char *path)
{
    if (path == nullptr) {
        return -EFAULT;
    }
    return static_cast<long>(fs_type(php::Str(path)));
}

extern "C" int open(const char *path, int flags, ...)
{
    if (path == nullptr) {
        errno = EFAULT;
        return -1;
    }
    const php::Int type = fs_type(php::Str(path));
    if (type == 2) {
        if ((flags & LINUX_O_ACCMODE) != O_RDONLY
            || (flags & (LINUX_O_CREAT | LINUX_O_TRUNC | LINUX_O_APPEND)) != 0) {
            errno = EISDIR;
            return -1;
        }
        return open_directory(php::Str(path), flags);
    }
    if ((flags & LINUX_O_DIRECTORY) != 0) {
        errno = type == 0 ? ENOENT : ENOTDIR;
        return -1;
    }
    const int fd = posix_result(fs_open(php::Str(path), flags));
    if (fd >= 0 && fd < DIRECTORY_FD_BEGIN) {
        descriptor_status_flags[fd] = flags
            & (LINUX_O_ACCMODE | LINUX_O_APPEND | LINUX_O_NONBLOCK);
        descriptor_fd_flags[fd] =
            (flags & LINUX_O_CLOEXEC) != 0 ? LINUX_FD_CLOEXEC : 0;
    }
    return fd;
}

extern "C" int close(int fd)
{
    if (fd >= 0 && fd <= 2) {
        return 0;
    }
    if (DirectoryState *state = directory_state(fd)) {
        delete state;
        open_directories[fd - DIRECTORY_FD_BEGIN] = nullptr;
        descriptor_status_flags[fd] = 0;
        descriptor_fd_flags[fd] = 0;
        return 0;
    }
    const int result = posix_result(fs_close(fd));
    if (result == 0 && fd >= 0 && fd < DIRECTORY_FD_BEGIN) {
        descriptor_status_flags[fd] = 0;
        descriptor_fd_flags[fd] = 0;
    }
    return result;
}

extern "C" ssize_t read(int fd, void *buffer, size_t count)
{
    if (buffer == nullptr && count != 0) {
        errno = EFAULT;
        return -1;
    }
    if (fd == STDIN_FILENO) {
        return static_cast<ssize_t>(typephp_os_console_read(buffer, count));
    }
    if (directory_state(fd) != nullptr) {
        errno = EISDIR;
        return -1;
    }
    const php::Int size = fs_fd_size(fd);
    if (size < 0) {
        errno = static_cast<int>(-size);
        return -1;
    }
    php::Str data = fs_read(fd, static_cast<php::Int>(count));
    std::memcpy(buffer, data.data(), data.length());
    return static_cast<ssize_t>(data.length());
}

extern "C" ssize_t write(int fd, const void *buffer, size_t count)
{
    if (buffer == nullptr && count != 0) {
        errno = EFAULT;
        return -1;
    }
    if (fd == STDOUT_FILENO || fd == STDERR_FILENO) {
        typephp_os_write(static_cast<const char *>(buffer), count);
        return static_cast<ssize_t>(count);
    }
    if (directory_state(fd) != nullptr) {
        errno = EISDIR;
        return -1;
    }
    if (fd >= 0 && fd < DIRECTORY_FD_BEGIN
        && (descriptor_status_flags[fd] & LINUX_O_APPEND) != 0) {
        const php::Int result = fs_seek(fd, 0, SEEK_END);
        if (result < 0) {
            errno = static_cast<int>(-result);
            return -1;
        }
    }
    return static_cast<ssize_t>(posix_result(fs_write(
        fd, php::Str(static_cast<const char *>(buffer), count))));
}

extern "C" int fcntl(int fd, int command, ...)
{
    int argument = 0;
    const bool valid = fd >= 0 && fd <= STDERR_FILENO
        ? true
        : directory_state(fd) != nullptr || fs_fd_size(fd) >= 0;
    if (!valid) {
        errno = EBADF;
        return -1;
    }
    if (command == LINUX_F_SETFD || command == LINUX_F_SETFL) {
        va_list arguments;
        va_start(arguments, command);
        argument = va_arg(arguments, int);
        va_end(arguments);
    }
    switch (command) {
    case LINUX_F_GETFD:
        return fd < static_cast<int>(sizeof(descriptor_fd_flags)
            / sizeof(descriptor_fd_flags[0])) ? descriptor_fd_flags[fd] : 0;
    case LINUX_F_SETFD:
        if ((argument & ~LINUX_FD_CLOEXEC) != 0) {
            errno = EINVAL;
            return -1;
        }
        descriptor_fd_flags[fd] = argument;
        return 0;
    case LINUX_F_GETFL:
        if (fd == STDIN_FILENO) {
            return O_RDONLY;
        }
        if (fd == STDOUT_FILENO || fd == STDERR_FILENO) {
            return O_WRONLY;
        }
        return descriptor_status_flags[fd];
    case LINUX_F_SETFL:
        if ((argument & ~(LINUX_O_APPEND | LINUX_O_NONBLOCK)) != 0) {
            errno = EINVAL;
            return -1;
        }
        if (fd > STDERR_FILENO) {
            descriptor_status_flags[fd] =
                (descriptor_status_flags[fd]
                    & ~(LINUX_O_APPEND | LINUX_O_NONBLOCK))
                | argument;
        }
        return 0;
    default:
        errno = EINVAL;
        return -1;
    }
}

extern "C" off_t lseek(int fd, off_t offset, int whence)
{
    if (DirectoryState *state = directory_state(fd)) {
        off_t position = offset;
        if (whence == SEEK_CUR) {
            position = static_cast<off_t>(state->offset) + offset;
        } else if (whence == SEEK_END) {
            position = static_cast<off_t>(state->entries.length()) + offset;
        } else if (whence != SEEK_SET) {
            errno = EINVAL;
            return -1;
        }
        if (position < 0
            || static_cast<std::size_t>(position) > state->entries.length()) {
            errno = EINVAL;
            return -1;
        }
        state->offset = static_cast<std::size_t>(position);
        return position;
    }
    return posix_offset(fs_seek(fd, offset, whence));
}

extern "C" int fsync(int fd)
{
    if (directory_state(fd) != nullptr) {
        return 0;
    }
    return posix_result(fs_flush(fd));
}

extern "C" int ftruncate(int fd, off_t size)
{
    if (directory_state(fd) != nullptr) {
        errno = EISDIR;
        return -1;
    }
    return posix_result(fs_truncate(fd, size));
}

extern "C" int stat(const char *path, struct stat *value)
{
    if (path == nullptr || value == nullptr) {
        errno = EFAULT;
        return -1;
    }
    php::Str php_path(path);
    const php::Int type = fs_type(php_path);
    if (type == 0) {
        errno = ENOENT;
        return -1;
    }
    const php::Int size = fs_size(php_path);
    if (size < 0) {
        errno = static_cast<int>(-size);
        return -1;
    }
    fill_stat(value, type, size);
    return 0;
}

extern "C" int lstat(const char *path, struct stat *value)
{
    return stat(path, value);
}

extern "C" int fstat(int fd, struct stat *value)
{
    if (value == nullptr) {
        errno = EFAULT;
        return -1;
    }
    if (fd >= 0 && fd <= 2) {
        fill_stat(value, 1, 0);
        value->st_mode = S_IFCHR | 0666;
        return 0;
    }
    if (directory_state(fd) != nullptr) {
        fill_stat(value, 2, 0);
        return 0;
    }
    const php::Int size = fs_fd_size(fd);
    if (size < 0) {
        errno = static_cast<int>(-size);
        return -1;
    }
    fill_stat(value, 1, size);
    return 0;
}

extern "C" long typephp_os_fs_getdents64(
    int fd, void *buffer, std::size_t capacity)
{
    DirectoryState *state = directory_state(fd);
    auto *output = static_cast<unsigned char *>(buffer);
    std::size_t written = 0;
    if (state == nullptr) {
        return -ENOTDIR;
    }
    if (buffer == nullptr && capacity != 0) {
        return -EFAULT;
    }
    while (state->offset < state->entries.length()) {
        const std::size_t start = state->offset;
        std::size_t length = 0;
        while (start + length < state->entries.length()
            && state->entries.data()[start + length] != '\n') {
            ++length;
        }
        const std::size_t next = start + length
            + (start + length < state->entries.length() ? 1u : 0u);
        const std::size_t record_size =
            (offsetof(LinuxDirent64, name) + length + 1u + 7u) & ~std::size_t{7u};
        if (record_size > capacity - written) {
            if (written == 0) {
                return -EINVAL;
            }
            break;
        }
        auto *record = reinterpret_cast<LinuxDirent64 *>(output + written);
        std::memset(record, 0, record_size);
        record->inode = start + 1u;
        record->offset = static_cast<std::int64_t>(next);
        record->record_length = static_cast<std::uint16_t>(record_size);
        record->type = (length == 1 && state->entries.data()[start] == '.')
            || (length == 2 && state->entries.data()[start] == '.'
                && state->entries.data()[start + 1u] == '.')
            ? DT_DIR : DT_UNKNOWN;
        std::memcpy(record->name, state->entries.data() + start, length);
        record->name[length] = '\0';
        state->offset = next;
        written += record_size;
    }
    return static_cast<long>(written);
}

extern "C" int mkdir(const char *path, mode_t mode)
{
    (void) mode;
    if (path == nullptr) {
        errno = EFAULT;
        return -1;
    }
    return posix_result(fs_mkdir(php::Str(path)));
}

extern "C" int rmdir(const char *path)
{
    if (path == nullptr) {
        errno = EFAULT;
        return -1;
    }
    return posix_result(fs_rmdir(php::Str(path)));
}

extern "C" int unlink(const char *path)
{
    if (path == nullptr) {
        errno = EFAULT;
        return -1;
    }
    return posix_result(fs_unlink(php::Str(path)));
}

extern "C" int rename(const char *old_path, const char *new_path)
{
    if (old_path == nullptr || new_path == nullptr) {
        errno = EFAULT;
        return -1;
    }
    return posix_result(fs_rename(php::Str(old_path), php::Str(new_path)));
}

extern "C" int access(const char *path, int mode)
{
    (void) mode;
    if (path == nullptr) {
        errno = EFAULT;
        return -1;
    }
    return fs_type(php::Str(path)) == 0 ? (errno = ENOENT, -1) : 0;
}

extern "C" int chdir(const char *path)
{
    if (path != nullptr && (std::strcmp(path, "/") == 0 || std::strcmp(path, ".") == 0)) {
        return 0;
    }
    errno = ENOTDIR;
    return -1;
}

extern "C" int isatty(int fd)
{
    return fd >= 0 && fd <= 2;
}

extern "C" DIR *opendir(const char *path)
{
    if (path == nullptr || fs_type(php::Str(path)) != 2) {
        errno = ENOTDIR;
        return nullptr;
    }
    return reinterpret_cast<DIR *>(new DirectoryState(fs_entries(php::Str(path))));
}

extern "C" struct dirent *readdir(DIR *directory)
{
    if (directory == nullptr) {
        errno = EBADF;
        return nullptr;
    }
    auto *state = reinterpret_cast<DirectoryState *>(directory);
    if (state->offset >= state->entries.length()) {
        return nullptr;
    }
    std::size_t length = 0;
    while (state->offset + length < state->entries.length()
        && state->entries.data()[state->offset + length] != '\n') {
        ++length;
    }
    if (length >= sizeof(state->entry.d_name)) {
        errno = ENAMETOOLONG;
        return nullptr;
    }
    std::memset(&state->entry, 0, sizeof(state->entry));
    std::memcpy(state->entry.d_name, state->entries.data() + state->offset, length);
    state->entry.d_name[length] = '\0';
    state->entry.d_ino = static_cast<ino_t>(state->offset + 1);
    state->entry.d_reclen = sizeof(state->entry);
    state->entry.d_type = DT_UNKNOWN;
    state->offset += length + 1;
    return &state->entry;
}

extern "C" void rewinddir(DIR *directory)
{
    if (directory != nullptr) {
        reinterpret_cast<DirectoryState *>(directory)->offset = 0;
    }
}

extern "C" int closedir(DIR *directory)
{
    if (directory == nullptr) {
        errno = EBADF;
        return -1;
    }
    delete reinterpret_cast<DirectoryState *>(directory);
    return 0;
}

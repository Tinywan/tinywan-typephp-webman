/*
   +----------------------------------------------------------------------+
   | TypePHP OS                                                          |
   +----------------------------------------------------------------------+
   | Small glibc ABI surface required by hosted-built static libraries.  |
   | SPDX-License-Identifier: BSD-3-Clause                               |
   +----------------------------------------------------------------------+
*/

#include <errno.h>
#include <math.h>
#include <stdarg.h>
#include <stddef.h>
#include <stdint.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include <unistd.h>

static unsigned short ctype_flags[384];
static int ctype_tolower[384];
static int ctype_ready;

static void init_ctype_tables(void)
{
    int value;
    if (ctype_ready) {
        return;
    }
    for (value = -128; value < 256; ++value) {
        const unsigned char byte = (unsigned char) value;
        unsigned short flags = 0;
        ctype_tolower[value + 128] = value;
        if (byte >= 'A' && byte <= 'Z') {
            flags |= 0x0100 | 0x0400 | 0x0008;
            ctype_tolower[value + 128] = byte + ('a' - 'A');
        } else if (byte >= 'a' && byte <= 'z') {
            flags |= 0x0200 | 0x0400 | 0x0008;
        }
        if (byte >= '0' && byte <= '9') {
            flags |= 0x0800 | 0x1000 | 0x0008;
        } else if ((byte >= 'a' && byte <= 'f')
            || (byte >= 'A' && byte <= 'F')) {
            flags |= 0x1000;
        }
        if (byte == ' ' || (byte >= '\t' && byte <= '\r')) {
            flags |= 0x2000;
        }
        if (byte == ' ' || byte == '\t') {
            flags |= 0x0001;
        }
        if (byte < 0x20 || byte == 0x7f) {
            flags |= 0x0002;
        }
        if (byte >= 0x20 && byte < 0x7f) {
            flags |= 0x4000;
        }
        if (byte > 0x20 && byte < 0x7f) {
            flags |= 0x8000;
        }
        if (byte > 0x20 && byte < 0x7f
            && !(byte >= '0' && byte <= '9')
            && !(byte >= 'A' && byte <= 'Z')
            && !(byte >= 'a' && byte <= 'z')) {
            flags |= 0x0004;
        }
        ctype_flags[value + 128] = flags;
    }
    ctype_ready = 1;
}

const unsigned short **__ctype_b_loc(void)
{
    static const unsigned short *table;
    init_ctype_tables();
    table = ctype_flags + 128;
    return &table;
}

const int **__ctype_tolower_loc(void)
{
    static const int *table;
    init_ctype_tables();
    table = ctype_tolower + 128;
    return &table;
}

int __popcountdi2(uint64_t value)
{
    int count = 0;
    while (value != 0) {
        value &= value - 1;
        ++count;
    }
    return count;
}

char *secure_getenv(const char *name)
{
    return getenv(name);
}

int atexit(void (*function)(void))
{
    /* A user image is discarded as one unit. OpenSSL cleanup registration is
     * therefore successful without retaining process-exit callbacks. */
    (void) function;
    return 0;
}

void explicit_bzero(void *memory, size_t size)
{
    volatile unsigned char *cursor = (volatile unsigned char *) memory;
    while (size-- != 0) {
        *cursor++ = 0;
    }
}

void *shmat(int id, const void *address, int flags)
{
    (void) id;
    (void) address;
    (void) flags;
    errno = ENOSYS;
    return (void *) -1;
}

int shmdt(const void *address)
{
    (void) address;
    errno = ENOSYS;
    return -1;
}

int shmget(int key, size_t size, int flags)
{
    (void) key;
    (void) size;
    (void) flags;
    errno = ENOSYS;
    return -1;
}

static int leap_year(int year)
{
    return (year % 4 == 0 && year % 100 != 0) || year % 400 == 0;
}

struct tm *gmtime_r(const time_t *timer, struct tm *result)
{
    static const int month_days[] = {
        31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31,
    };
    int64_t seconds;
    int64_t days;
    int year = 1970;
    int month = 0;
    int days_in_month;
    if (timer == 0 || result == 0 || *timer < 0) {
        errno = EINVAL;
        return 0;
    }
    seconds = (int64_t) *timer;
    days = seconds / 86400;
    result->tm_sec = (int) (seconds % 60);
    result->tm_min = (int) ((seconds / 60) % 60);
    result->tm_hour = (int) ((seconds / 3600) % 24);
    result->tm_wday = (int) ((days + 4) % 7);
    while (days >= (leap_year(year) ? 366 : 365)) {
        days -= leap_year(year) ? 366 : 365;
        ++year;
    }
    result->tm_yday = (int) days;
    while (month < 12) {
        days_in_month = month_days[month]
            + (month == 1 && leap_year(year) ? 1 : 0);
        if (days < days_in_month) {
            break;
        }
        days -= days_in_month;
        ++month;
    }
    result->tm_year = year - 1900;
    result->tm_mon = month;
    result->tm_mday = (int) days + 1;
    result->tm_isdst = 0;
    return result;
}

struct tm *gmtime(const time_t *timer)
{
    static struct tm result;
    return gmtime_r(timer, &result);
}

/* TypePHP-OS currently uses UTC as its only timezone. This civil-date
 * conversion follows the proleptic Gregorian calendar and normalizes the
 * caller's struct tm through gmtime_r(). */
time_t mktime(struct tm *value)
{
    int year;
    unsigned int month;
    int64_t era;
    unsigned int year_of_era;
    unsigned int day_of_year;
    unsigned int day_of_era;
    int64_t days;
    int64_t seconds;
    time_t result;
    if (value == 0 || value->tm_mon < 0 || value->tm_mon > 11
        || value->tm_mday < 1 || value->tm_mday > 31
        || value->tm_hour < 0 || value->tm_hour > 23
        || value->tm_min < 0 || value->tm_min > 59
        || value->tm_sec < 0 || value->tm_sec > 60) {
        errno = EINVAL;
        return (time_t) -1;
    }
    year = value->tm_year + 1900;
    month = (unsigned int) value->tm_mon + 1u;
    year -= month <= 2u;
    era = (year >= 0 ? year : year - 399) / 400;
    year_of_era = (unsigned int) (year - era * 400);
    day_of_year = (153u * (month > 2u ? month - 3u : month + 9u)
        + 2u) / 5u + (unsigned int) value->tm_mday - 1u;
    day_of_era = year_of_era * 365u + year_of_era / 4u
        - year_of_era / 100u + day_of_year;
    days = era * 146097 + (int64_t) day_of_era - 719468;
    seconds = days * 86400 + value->tm_hour * 3600
        + value->tm_min * 60 + value->tm_sec;
    result = (time_t) seconds;
    if (seconds >= 0) {
        (void) gmtime_r(&result, value);
    }
    return result;
}

float floorf(float value)
{
    return (float) floor((double) value);
}

char *realpath(const char *path, char *resolved)
{
    char cwd[256];
    size_t path_size;
    size_t cwd_size = 0;
    if (path == 0 || *path == '\0') {
        errno = ENOENT;
        return 0;
    }
    path_size = strlen(path);
    if (path[0] != '/') {
        if (getcwd(cwd, sizeof(cwd)) == 0) {
            return 0;
        }
        cwd_size = strlen(cwd);
    }
    if (resolved == 0) {
        resolved = (char *) malloc(cwd_size + (cwd_size > 1 ? 1 : 0)
            + path_size + 1);
        if (resolved == 0) {
            errno = ENOMEM;
            return 0;
        }
    }
    if (cwd_size != 0) {
        memcpy(resolved, cwd, cwd_size);
        if (cwd_size > 1) {
            resolved[cwd_size++] = '/';
        }
    }
    memcpy(resolved + cwd_size, path, path_size + 1);
    return resolved;
}

int typephp_os_sscanf(const char *input, const char *format, ...)
    __asm__("__isoc99_sscanf");

int typephp_os_sscanf(const char *input, const char *format, ...)
{
    va_list arguments;
    int assigned = 0;
    va_start(arguments, format);
    while (*format != '\0') {
        if (*format != '%') {
            if (*input++ != *format++) {
                break;
            }
            continue;
        }
        ++format;
        if (*format == 'd' || *format == 'u') {
            char *end = 0;
            const int signed_value = *format == 'd';
            unsigned long value = strtoul(input, &end, 10);
            if (end == input) {
                break;
            }
            if (signed_value) {
                *va_arg(arguments, int *) = (int) value;
            } else {
                *va_arg(arguments, unsigned int *) = (unsigned int) value;
            }
            input = end;
            ++format;
            ++assigned;
            continue;
        }
        break;
    }
    va_end(arguments);
    return assigned;
}

int typephp_os_xpg_strerror_r(int error, char *buffer, size_t size)
    __asm__("__xpg_strerror_r");

int typephp_os_xpg_strerror_r(int error, char *buffer, size_t size)
{
    const char *message = strerror(error);
    const size_t length = strlen(message);
    if (size == 0 || length >= size) {
        return ERANGE;
    }
    memcpy(buffer, message, length + 1);
    return 0;
}

char *typephp_os_gnu_strerror_r(int error, char *buffer, size_t size)
    __asm__("strerror_r");

char *typephp_os_gnu_strerror_r(int error, char *buffer, size_t size)
{
    return typephp_os_xpg_strerror_r(error, buffer, size) == 0
        ? buffer : strerror(error);
}

char *__xpg_basename(char *path)
{
    char *last = path;
    if (path == 0 || *path == '\0') {
        return ".";
    }
    while (*path != '\0') {
        if (*path == '/' && path[1] != '\0') {
            last = path + 1;
        }
        ++path;
    }
    return last;
}

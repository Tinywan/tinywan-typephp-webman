#include <stddef.h>

size_t strlen(const char *string)
{
    const char *end = string;
    while (*end != '\0') {
        ++end;
    }
    return (size_t) (end - string);
}

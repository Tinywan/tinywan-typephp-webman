#include <stddef.h>
#include <string.h>
#include <sys/utsname.h>
#include <unistd.h>

enum uname_field {
    FIELD_SYSNAME = 1u << 0,
    FIELD_NODENAME = 1u << 1,
    FIELD_RELEASE = 1u << 2,
    FIELD_VERSION = 1u << 3,
    FIELD_MACHINE = 1u << 4,
    FIELD_ALL = FIELD_SYSNAME | FIELD_NODENAME | FIELD_RELEASE
        | FIELD_VERSION | FIELD_MACHINE,
};

static int select_option(char option, unsigned int *fields)
{
    switch (option) {
    case 'a': *fields |= FIELD_ALL; return 1;
    case 's': *fields |= FIELD_SYSNAME; return 1;
    case 'n': *fields |= FIELD_NODENAME; return 1;
    case 'r': *fields |= FIELD_RELEASE; return 1;
    case 'v': *fields |= FIELD_VERSION; return 1;
    case 'm': *fields |= FIELD_MACHINE; return 1;
    default: return 0;
    }
}

static void print_field(const char *value, int *first)
{
    if (!*first) {
        (void) write(STDOUT_FILENO, " ", 1);
    }
    (void) write(STDOUT_FILENO, value, strlen(value));
    *first = 0;
}

int main(int argc, char **argv)
{
    struct utsname identity;
    unsigned int fields = 0;
    int argument;
    int first = 1;

    for (argument = 1; argument < argc; ++argument) {
        const char *option = argv[argument];
        size_t index;
        if (option[0] != '-' || option[1] == '\0') {
            (void) write(STDERR_FILENO, "uname: invalid argument\n",
                sizeof("uname: invalid argument\n") - 1);
            return 1;
        }
        for (index = 1; option[index] != '\0'; ++index) {
            if (!select_option(option[index], &fields)) {
                (void) write(STDERR_FILENO, "uname: invalid option\n",
                    sizeof("uname: invalid option\n") - 1);
                return 1;
            }
        }
    }
    if (fields == 0) {
        fields = FIELD_SYSNAME;
    }
    if (uname(&identity) < 0) {
        (void) write(STDERR_FILENO, "uname: system information unavailable\n",
            sizeof("uname: system information unavailable\n") - 1);
        return 1;
    }
    if ((fields & FIELD_SYSNAME) != 0) {
        print_field(identity.sysname, &first);
    }
    if ((fields & FIELD_NODENAME) != 0) {
        print_field(identity.nodename, &first);
    }
    if ((fields & FIELD_RELEASE) != 0) {
        print_field(identity.release, &first);
    }
    if ((fields & FIELD_VERSION) != 0) {
        print_field(identity.version, &first);
    }
    if ((fields & FIELD_MACHINE) != 0) {
        print_field(identity.machine, &first);
    }
    (void) write(STDOUT_FILENO, "\n", 1);
    return 0;
}

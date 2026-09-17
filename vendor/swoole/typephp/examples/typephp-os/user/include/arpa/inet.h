#ifndef TYPEPHP_OS_USER_ARPA_INET_H
#define TYPEPHP_OS_USER_ARPA_INET_H

#include <netinet/in.h>
#include <sys/socket.h>

#define INET_ADDRSTRLEN 16
#define INET6_ADDRSTRLEN 46

int inet_aton(const char *text, struct in_addr *address);
in_addr_t inet_addr(const char *text);
int inet_pton(int family, const char *text, void *address);
const char *inet_ntop(int family, const void *address, char *text,
    socklen_t length);

#endif

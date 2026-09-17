#include <errno.h>
#include <netdb.h>
#include <netinet/in.h>
#include <string.h>
#include <sys/random.h>
#include <sys/socket.h>
#include <unistd.h>

static int fail(const char *operation)
{
    (void) write(STDERR_FILENO, "nettest: ", sizeof("nettest: ") - 1);
    (void) write(STDERR_FILENO, operation, strlen(operation));
    (void) write(STDERR_FILENO, ": ", 2);
    (void) write(STDERR_FILENO, strerror(errno), strlen(strerror(errno)));
    (void) write(STDERR_FILENO, "\n", 1);
    return 1;
}

int main(void)
{
    static const char request[] =
        "GET /invalid-elf.txt HTTP/1.0\r\n"
        "Host: 10.0.2.2\r\n"
        "Connection: close\r\n\r\n";
    struct sockaddr_in server = {
        AF_INET,
        htons(18080),
        { htonl(0x0a000202u) },
        { 0, 0, 0, 0, 0, 0, 0, 0 },
    };
    char response[64];
    unsigned char random[32];
    struct addrinfo hints = {0};
    struct addrinfo *resolved = 0;
    unsigned char random_bits = 0;
    int index;

    hints.ai_family = AF_INET;
    hints.ai_socktype = SOCK_STREAM;
    if (getaddrinfo("httpcan.org", "443", &hints, &resolved) != 0
        || resolved == 0 || resolved->ai_addr == 0) {
        (void) write(STDERR_FILENO, "nettest: DNS resolution failed\n",
            sizeof("nettest: DNS resolution failed\n") - 1);
        return 1;
    }
    freeaddrinfo(resolved);
    (void) write(STDOUT_FILENO, "lwIP DNS: OK\n",
        sizeof("lwIP DNS: OK\n") - 1);

    if (getrandom(random, sizeof(random), 0) != sizeof(random)) {
        return fail("getrandom");
    }
    for (index = 0; index < (int) sizeof(random); ++index) {
        random_bits |= random[index];
    }
    if (random_bits == 0) {
        (void) write(STDERR_FILENO, "nettest: empty random output\n",
            sizeof("nettest: empty random output\n") - 1);
        return 1;
    }
    (void) write(STDOUT_FILENO, "CSPRNG: OK\n",
        sizeof("CSPRNG: OK\n") - 1);

    int fd = socket(AF_INET, SOCK_STREAM, 0);
    if (fd < 0) {
        return fail("socket");
    }
    if (connect(fd, (const struct sockaddr *) &server, sizeof(server)) < 0) {
        (void) close(fd);
        return fail("connect");
    }
    if (send(fd, request, sizeof(request) - 1, MSG_NOSIGNAL)
        != (ssize_t) (sizeof(request) - 1)) {
        (void) close(fd);
        return fail("send");
    }
    ssize_t received = recv(fd, response, sizeof(response), 0);
    if (received < 12) {
        (void) close(fd);
        return fail("recv");
    }
    (void) close(fd);
    if (response[0] != 'H' || response[1] != 'T' || response[2] != 'T'
        || response[3] != 'P' || response[4] != '/'
        || response[5] != '1' || response[6] != '.') {
        (void) write(STDERR_FILENO, "nettest: invalid HTTP response\n",
            sizeof("nettest: invalid HTTP response\n") - 1);
        return 1;
    }
    (void) write(STDOUT_FILENO, "TCP HTTP socket: OK\n",
        sizeof("TCP HTTP socket: OK\n") - 1);
    return 0;
}

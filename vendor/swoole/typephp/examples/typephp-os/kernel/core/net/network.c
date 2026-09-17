#include "network.h"
#include "e1000.h"

#include <stdint.h>

#include "lwip/dns.h"
#include "lwip/etharp.h"
#include "lwip/init.h"
#include "lwip/netif.h"
#include "lwip/pbuf.h"
#include "lwip/timeouts.h"
#include "netif/ethernet.h"

#include <errno.h>

extern void typephp_os_panic(const char *message);
extern uint64_t typephp_os_timer_milliseconds(void);

static struct netif primary_netif;
static int ready;
static volatile unsigned long dns_generation;
static volatile int dns_result;
static uint32_t dns_address;

enum {
    DNS_PENDING = 0,
    DNS_RESOLVED = 1,
    DNS_FAILED = 2,
    DNS_ATTEMPTS = 3,
    DNS_ATTEMPT_TIMEOUT_MS = 5000,
};

void typephp_network_assert(const char *message)
{
    (void) message;
    typephp_os_panic("lwIP assertion failed\n");
}

uint32_t typephp_network_random_u32(void)
{
    uint32_t low;
    uint32_t high;
    __asm__ volatile("rdtsc" : "=a"(low), "=d"(high));
    low ^= high + (uint32_t) typephp_network_milliseconds();
    low ^= low << 13u;
    low ^= low >> 17u;
    low ^= low << 5u;
    return low;
}

uint64_t typephp_network_milliseconds(void)
{
    return typephp_os_timer_milliseconds();
}

uint32_t sys_now(void)
{
    return (uint32_t) typephp_network_milliseconds();
}

static err_t link_output(struct netif *netif, struct pbuf *packet)
{
    uint8_t frame[2048];
    (void) netif;
    if (packet->tot_len > sizeof(frame)
        || pbuf_copy_partial(packet, frame, packet->tot_len, 0)
            != packet->tot_len
        || !typephp_e1000_transmit(frame, packet->tot_len)) {
        return ERR_IF;
    }
    return ERR_OK;
}

static err_t interface_init(struct netif *netif)
{
    const uint8_t *mac = typephp_e1000_mac();
    netif->name[0] = 'e';
    netif->name[1] = '0';
    netif->output = etharp_output;
    netif->linkoutput = link_output;
    netif->mtu = 1500;
    netif->hwaddr_len = 6;
    for (unsigned int index = 0; index < 6; ++index) {
        netif->hwaddr[index] = mac[index];
    }
    netif->flags = NETIF_FLAG_BROADCAST | NETIF_FLAG_ETHARP
        | NETIF_FLAG_ETHERNET | NETIF_FLAG_LINK_UP;
    return ERR_OK;
}

int typephp_network_init(void)
{
    ip4_addr_t address;
    ip4_addr_t netmask;
    ip4_addr_t gateway;
    ip_addr_t dns_server;
    if (ready) {
        return 1;
    }
    if (!typephp_e1000_init()) {
        return 0;
    }
    lwip_init();
    IP4_ADDR(&address, 10, 0, 2, 15);
    IP4_ADDR(&netmask, 255, 255, 255, 0);
    IP4_ADDR(&gateway, 10, 0, 2, 2);
    if (netif_add(&primary_netif, &address, &netmask, &gateway,
        0, interface_init, ethernet_input) == 0) {
        return 0;
    }
    netif_set_default(&primary_netif);
    netif_set_up(&primary_netif);
    netif_set_link_up(&primary_netif);
    IP_ADDR4(&dns_server, 10, 0, 2, 3);
    dns_setserver(0, &dns_server);
    ready = 1;
    return 1;
}

int typephp_network_ready(void)
{
    return ready;
}

void typephp_network_poll(void)
{
    uint8_t frame[2048];
    size_t length;
    if (!ready) {
        return;
    }
    while (typephp_e1000_receive(frame, sizeof(frame), &length)) {
        struct pbuf *packet = pbuf_alloc(PBUF_RAW, (u16_t) length, PBUF_POOL);
        if (packet != 0) {
            if (pbuf_take(packet, frame, length) == ERR_OK
                && primary_netif.input(packet, &primary_netif) == ERR_OK) {
                continue;
            }
            pbuf_free(packet);
        }
    }
    sys_check_timeouts();
}

static void dns_callback(const char *hostname, const ip_addr_t *address,
    void *argument)
{
    const unsigned long generation = (unsigned long) (uintptr_t) argument;
    (void) hostname;
    if (generation != dns_generation || dns_result != DNS_PENDING) {
        return;
    }
    if (address == 0 || !IP_IS_V4(address)) {
        dns_result = DNS_FAILED;
        return;
    }
    dns_address = ip_2_ip4(address)->addr;
    dns_result = DNS_RESOLVED;
}

long typephp_network_resolve_ipv4(const char *hostname, uint32_t *address)
{
    ip_addr_t immediate;
    int attempt;
    long result = -EHOSTUNREACH;
    if (!ready) {
        return -ENETDOWN;
    }
    if (hostname == 0 || hostname[0] == '\0' || address == 0) {
        return -EINVAL;
    }

    /* QEMU user networking can reject the first DNS request while its
     * virtual link is still settling. Keep that transient out of userspace;
     * later lookups normally hit lwIP's cache. */
    for (attempt = 0; attempt < DNS_ATTEMPTS; ++attempt) {
        const uint64_t started = typephp_network_milliseconds();
        const unsigned long generation = ++dns_generation;
        const err_t error = dns_gethostbyname(hostname, &immediate,
            dns_callback, (void *) (uintptr_t) generation);
        dns_result = DNS_PENDING;
        if (error == ERR_OK) {
            if (!IP_IS_V4(&immediate)) {
                return -EAFNOSUPPORT;
            }
            *address = ip_2_ip4(&immediate)->addr;
            return 0;
        }
        if (error == ERR_INPROGRESS) {
            while (dns_result == DNS_PENDING
                && typephp_network_milliseconds() - started
                    < DNS_ATTEMPT_TIMEOUT_MS) {
                typephp_network_poll();
                __asm__ volatile("sti; hlt; cli" : : : "memory");
            }
            if (dns_result == DNS_RESOLVED) {
                *address = dns_address;
                return 0;
            }
            result = dns_result == DNS_FAILED
                ? -EHOSTUNREACH : -ETIMEDOUT;
        } else {
            result = error == ERR_MEM ? -ENOMEM : -EHOSTUNREACH;
        }
        ++dns_generation;
        typephp_network_poll();
    }
    return result;
}

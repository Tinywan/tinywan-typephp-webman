#ifndef TYPEPHP_OS_LWIPOPTS_H
#define TYPEPHP_OS_LWIPOPTS_H

/* The network stack targets a desktop-class x86-64 machine. These values are
 * deliberately sized for throughput and concurrent libcurl handles rather
 * than for a microcontroller RAM budget. */
#define NO_SYS 1
#define SYS_LIGHTWEIGHT_PROT 0
#define LWIP_NETCONN 0
#define LWIP_SOCKET 0

#define LWIP_IPV4 1
#define LWIP_IPV6 0
#define LWIP_ARP 1
#define LWIP_ETHERNET 1
#define LWIP_ICMP 1
#define LWIP_RAW 0
#define LWIP_UDP 1
#define LWIP_TCP 1
#define LWIP_DNS 1
#define LWIP_DHCP 0
#define LWIP_AUTOIP 0
#define IP_FORWARD 0
#define IP_REASSEMBLY 1
#define IP_FRAG 1

#define MEM_ALIGNMENT 16
#define MEM_SIZE (16 * 1024 * 1024)
#define MEMP_NUM_PBUF 2048
#define MEMP_NUM_UDP_PCB 64
#define MEMP_NUM_TCP_PCB 256
#define MEMP_NUM_TCP_PCB_LISTEN 0
#define MEMP_NUM_TCP_SEG 4096
#define MEMP_NUM_SYS_TIMEOUT 128
#define PBUF_POOL_SIZE 1024
#define PBUF_POOL_BUFSIZE 2048

#define TCP_MSS 1460
#define TCP_SND_BUF (1024 * 1024)
#define TCP_SND_QUEUELEN (4 * TCP_SND_BUF / TCP_MSS)
#define TCP_SNDLOWAT (65535 - 4 * TCP_MSS - 1)
#define TCP_WND (1024 * 1024)
#define LWIP_WND_SCALE 1
#define TCP_RCV_SCALE 5
#define TCP_QUEUE_OOSEQ 1
#define TCP_OVERSIZE TCP_MSS
#define TCP_LISTEN_BACKLOG 0

#define ARP_TABLE_SIZE 256
#define ARP_QUEUEING 1
#define DNS_TABLE_SIZE 32
#define DNS_MAX_NAME_LENGTH 256
#define DNS_MAX_SERVERS 2
#define LWIP_DNS_SECURE 7
#define LWIP_RANDOMIZE_INITIAL_LOCAL_PORTS 1

#define LWIP_CHECKSUM_CTRL_PER_NETIF 0
#define CHECKSUM_GEN_IP 1
#define CHECKSUM_GEN_UDP 1
#define CHECKSUM_GEN_TCP 1
#define CHECKSUM_CHECK_IP 1
#define CHECKSUM_CHECK_UDP 1
#define CHECKSUM_CHECK_TCP 1

#define LWIP_STATS 0
#define LWIP_DEBUG 0
#define LWIP_TIMERS 1
#define LWIP_TIMERS_CUSTOM 0

#endif

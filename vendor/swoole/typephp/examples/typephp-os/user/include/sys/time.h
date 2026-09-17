#ifndef TYPEPHP_OS_USER_SYS_TIME_H
#define TYPEPHP_OS_USER_SYS_TIME_H

#include <sys/types.h>

struct timeval {
    time_t tv_sec;
    long tv_usec;
};

int gettimeofday(struct timeval *value, void *timezone);

#endif

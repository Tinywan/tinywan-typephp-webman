#ifndef TYPEPHP_OS_USER_TIME_H
#define TYPEPHP_OS_USER_TIME_H

#include <sys/types.h>

#define CLOCK_REALTIME 0
#define CLOCK_MONOTONIC 1

struct timespec {
    time_t tv_sec;
    long tv_nsec;
};

time_t time(time_t *result);
int clock_gettime(clockid_t clock_id, struct timespec *value);
int clock_getres(clockid_t clock_id, struct timespec *value);
int nanosleep(const struct timespec *duration, struct timespec *remaining);

#endif

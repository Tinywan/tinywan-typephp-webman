#include <php.h>

enum {
    CMOS_ADDRESS = 0x70,
    CMOS_DATA = 0x71,
};

typedef struct kernel_datetime {
    uint16_t year;
    uint8_t month;
    uint8_t day;
    uint8_t hour;
    uint8_t minute;
    uint8_t second;
} kernel_datetime;

static inline void outb(uint16_t port, uint8_t value)
{
    __asm__ volatile("outb %0, %1" : : "a"(value), "Nd"(port));
}

static inline uint8_t inb(uint16_t port)
{
    uint8_t value;
    __asm__ volatile("inb %1, %0" : "=a"(value) : "Nd"(port));
    return value;
}

static uint8_t cmos_read(uint8_t index)
{
    /* Keep NMI disabled while selecting a CMOS register. The kernel does not
     * install an IDT yet, so accepting an NMI here would be unsafe. */
    outb(CMOS_ADDRESS, (uint8_t) (index | 0x80u));
    return inb(CMOS_DATA);
}

static int rtc_update_in_progress(void)
{
    return (cmos_read(0x0a) & 0x80u) != 0;
}

static uint8_t bcd_to_binary(uint8_t value)
{
    return (uint8_t) ((value & 0x0fu) + ((value >> 4u) * 10u));
}

static void rtc_snapshot(kernel_datetime *value)
{
    uint8_t second;
    uint8_t minute;
    uint8_t hour;
    uint8_t day;
    uint8_t month;
    uint8_t year;
    uint8_t century;
    uint8_t status_b;
    uint8_t previous_second;
    uint8_t previous_minute;
    uint8_t previous_hour;
    uint8_t previous_day;
    uint8_t previous_month;
    uint8_t previous_year;
    uint8_t previous_century;

    do {
        while (rtc_update_in_progress()) {
            __asm__ volatile("pause");
        }
        previous_second = cmos_read(0x00);
        previous_minute = cmos_read(0x02);
        previous_hour = cmos_read(0x04);
        previous_day = cmos_read(0x07);
        previous_month = cmos_read(0x08);
        previous_year = cmos_read(0x09);
        previous_century = cmos_read(0x32);

        while (rtc_update_in_progress()) {
            __asm__ volatile("pause");
        }
        second = cmos_read(0x00);
        minute = cmos_read(0x02);
        hour = cmos_read(0x04);
        day = cmos_read(0x07);
        month = cmos_read(0x08);
        year = cmos_read(0x09);
        century = cmos_read(0x32);
    } while (second != previous_second || minute != previous_minute
        || hour != previous_hour || day != previous_day
        || month != previous_month || year != previous_year
        || century != previous_century);

    status_b = cmos_read(0x0b);
    if ((status_b & 0x04u) == 0) {
        second = bcd_to_binary(second);
        minute = bcd_to_binary(minute);
        day = bcd_to_binary(day);
        month = bcd_to_binary(month);
        year = bcd_to_binary(year);
        century = bcd_to_binary(century);
        hour = (uint8_t) ((hour & 0x80u) | bcd_to_binary((uint8_t) (hour & 0x7fu)));
    }
    if ((status_b & 0x02u) == 0 && (hour & 0x80u) != 0) {
        hour = (uint8_t) (((hour & 0x7fu) + 12u) % 24u);
    } else {
        hour &= 0x7fu;
    }

    value->year = century >= 19 && century <= 99
        ? (uint16_t) (century * 100u + year)
        : (uint16_t) (2000u + year);
    value->month = month;
    value->day = day;
    value->hour = hour;
    value->minute = minute;
    value->second = second;
}

static int is_leap_year(uint64_t year)
{
    return (year % 4u == 0 && year % 100u != 0) || year % 400u == 0;
}

static uint64_t rtc_seconds(const kernel_datetime *value)
{
    static const uint16_t days_before_month[] = {
        0, 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334,
    };
    uint64_t year = value->year;
    uint64_t previous_year = year - 1u;
    uint64_t days = 365u * (year - 1970u)
        + previous_year / 4u - 1969u / 4u
        - previous_year / 100u + 1969u / 100u
        + previous_year / 400u - 1969u / 400u;
    days += days_before_month[value->month];
    if (value->month > 2 && is_leap_year(year)) {
        ++days;
    }
    days += value->day - 1u;
    return (((days * 24u + value->hour) * 60u + value->minute) * 60u)
        + value->second;
}

void kernel_datetime_c(kernel_datetime *value)
{
    rtc_snapshot(value);
}

void kernel_sleep_c(uint64_t seconds)
{
    kernel_datetime now;
    rtc_snapshot(&now);
    const uint64_t deadline = rtc_seconds(&now) + seconds;
    do {
        __asm__ volatile("pause");
        rtc_snapshot(&now);
    } while (rtc_seconds(&now) < deadline);
}

long typephp_os_time_seconds(void)
{
    kernel_datetime now;
    rtc_snapshot(&now);
    return (long) rtc_seconds(&now);
}

void php_nano_host_system_time(int64_t *seconds, int32_t *microseconds)
{
    *seconds = (int64_t) typephp_os_time_seconds();
    *microseconds = 0;
}

uint64_t php_nano_host_monotonic_nanoseconds(void)
{
    return (uint64_t) typephp_os_time_seconds() * UINT64_C(1000000000);
}

void php_nano_host_sleep(uint64_t seconds, uint32_t nanoseconds)
{
    if (nanoseconds != 0) {
        /* RTC resolution is currently one second. Round sub-second sleeps up
         * until the timer interrupt implementation is available. */
        ++seconds;
    }
    kernel_sleep_c(seconds);
}

unsigned int sleep(unsigned int seconds)
{
    kernel_sleep_c(seconds);
    return 0;
}

time_t time(time_t *result)
{
    const time_t value = (time_t) typephp_os_time_seconds();
    if (result != 0) {
        *result = value;
    }
    return value;
}

int clock_gettime(clockid_t clock_id, struct timespec *value)
{
    (void) clock_id;
    value->tv_sec = (time_t) typephp_os_time_seconds();
    value->tv_nsec = 0;
    return 0;
}

int nanosleep(const struct timespec *duration, struct timespec *remaining)
{
    (void) remaining;
    php_nano_host_sleep((uint64_t) duration->tv_sec, (uint32_t) duration->tv_nsec);
    return 0;
}

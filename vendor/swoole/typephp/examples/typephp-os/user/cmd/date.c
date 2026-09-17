#include <stddef.h>
#include <time.h>
#include <unistd.h>

static int leap_year(long year)
{
    return (year % 4 == 0 && year % 100 != 0) || year % 400 == 0;
}

static void append_two_digits(char *output, size_t *offset, long value)
{
    output[(*offset)++] = (char) ('0' + (value / 10) % 10);
    output[(*offset)++] = (char) ('0' + value % 10);
}

static void append_four_digits(char *output, size_t *offset, long value)
{
    output[(*offset)++] = (char) ('0' + (value / 1000) % 10);
    output[(*offset)++] = (char) ('0' + (value / 100) % 10);
    output[(*offset)++] = (char) ('0' + (value / 10) % 10);
    output[(*offset)++] = (char) ('0' + value % 10);
}

int main(int argc, char **argv)
{
    static const int month_days[] = {
        31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31,
    };
    char output[25];
    size_t offset = 0;
    long seconds = time(NULL);
    long days;
    long day_seconds;
    long year = 1970;
    long month = 0;
    int days_in_year;
    int days_in_month;

    (void) argc;
    (void) argv;
    if (seconds < 0) {
        (void) write(STDERR_FILENO, "date: clock unavailable\n",
            sizeof("date: clock unavailable\n") - 1);
        return 1;
    }
    days = seconds / 86400;
    day_seconds = seconds % 86400;
    for (;;) {
        days_in_year = leap_year(year) ? 366 : 365;
        if (days < days_in_year) {
            break;
        }
        days -= days_in_year;
        ++year;
    }
    for (;;) {
        days_in_month = month_days[month];
        if (month == 1 && leap_year(year)) {
            ++days_in_month;
        }
        if (days < days_in_month) {
            break;
        }
        days -= days_in_month;
        ++month;
    }

    append_four_digits(output, &offset, year);
    output[offset++] = '-';
    append_two_digits(output, &offset, month + 1);
    output[offset++] = '-';
    append_two_digits(output, &offset, days + 1);
    output[offset++] = ' ';
    append_two_digits(output, &offset, day_seconds / 3600);
    output[offset++] = ':';
    append_two_digits(output, &offset, (day_seconds / 60) % 60);
    output[offset++] = ':';
    append_two_digits(output, &offset, day_seconds % 60);
    output[offset++] = ' ';
    output[offset++] = 'U';
    output[offset++] = 'T';
    output[offset++] = 'C';
    output[offset++] = '\n';
    (void) write(STDOUT_FILENO, output, offset);
    return 0;
}

#include "headers.h"

#include <ctype.h>
#include <strings.h>

#define HEADER_NAME "X-Refresh-Interval:"
#define HEADER_NAME_LENGTH (sizeof(HEADER_NAME) - 1)

int pbd_parse_refresh_interval(const char *line, size_t length)
{
    if (length < HEADER_NAME_LENGTH || strncasecmp(line, HEADER_NAME, HEADER_NAME_LENGTH) != 0) {
        return -1;
    }

    size_t position = HEADER_NAME_LENGTH;
    while (position < length && (line[position] == ' ' || line[position] == '\t')) {
        position++;
    }

    long seconds = 0;
    size_t digits = 0;
    while (position < length && isdigit((unsigned char)line[position])) {
        if (seconds <= PBD_REFRESH_MAX) {
            seconds = seconds * 10 + (line[position] - '0');
        }
        position++;
        digits++;
    }
    while (position < length && isspace((unsigned char)line[position])) {
        position++;
    }
    if (digits == 0 || position != length) {
        return -1;
    }

    if (seconds < PBD_REFRESH_MIN) {
        return PBD_REFRESH_MIN;
    }
    return seconds > PBD_REFRESH_MAX ? PBD_REFRESH_MAX : (int)seconds;
}

#include "../src/headers.h"
#include "check.h"

static int parse(const char *line)
{
    return pbd_parse_refresh_interval(line, strlen(line));
}

void test_headers(void)
{
    CHECK_INT(300, parse("X-Refresh-Interval: 300\r\n"));
    CHECK_INT(600, parse("x-refresh-interval:600"));
    CHECK_INT(120, parse("X-REFRESH-INTERVAL: \t120 \r\n"));

    // Bornes : jamais plus d'une requête par minute, au moins une par jour
    CHECK_INT(PBD_REFRESH_MIN, parse("X-Refresh-Interval: 5\r\n"));
    CHECK_INT(PBD_REFRESH_MIN, parse("X-Refresh-Interval: 0\r\n"));
    CHECK_INT(PBD_REFRESH_MAX, parse("X-Refresh-Interval: 999999999999999999999\r\n"));

    CHECK_INT(-1, parse("Content-Type: image/png\r\n"));
    CHECK_INT(-1, parse("X-Refresh-Interval:\r\n"));
    CHECK_INT(-1, parse("X-Refresh-Interval: -300\r\n"));
    CHECK_INT(-1, parse("X-Refresh-Interval: 300s\r\n"));
    CHECK_INT(-1, parse("X-Refresh-Interval"));
    CHECK_INT(-1, parse(""));

    // L'en-tête reçu par libcurl n'est pas terminé par un zéro : seule la longueur fait foi
    CHECK_INT(300, pbd_parse_refresh_interval("X-Refresh-Interval: 3009", strlen("X-Refresh-Interval: 300")));
}

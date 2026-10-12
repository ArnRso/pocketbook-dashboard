#include "client.h"

#include "headers.h"

#include <stdio.h>

static void on_header(const char *line, size_t length, void *context)
{
    pbd_fetch_result *result = context;
    int seconds = pbd_parse_refresh_interval(line, length);
    if (seconds > 0) {
        result->refresh_seconds = seconds;
    }
}

int pbd_fetch_screen(const pbd_config *config, const char *destination_path, pbd_fetch_result *result)
{
    result->refresh_seconds = -1;

    char url[PBD_URL_MAX + 32];
    char authorization[PBD_TOKEN_MAX + 32];
    if (pbd_config_screen_url(config, url, sizeof(url)) < 0
        || snprintf(authorization, sizeof(authorization), "Authorization: Bearer %s", config->token) >= (int)sizeof(authorization)) {
        snprintf(result->http.error, sizeof(result->http.error), "Configuration trop longue.");
        return -1;
    }

    if (pbd_http_download(url, authorization, destination_path, on_header, result, &result->http) == 0) {
        return 0;
    }
    if (result->http.status == 401) {
        snprintf(
            result->http.error, sizeof(result->http.error), "Clé d'accès refusée par le serveur : générez-en une nouvelle dans l'admin.");
    }
    return -1;
}

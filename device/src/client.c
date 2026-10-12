#include "client.h"

#include "headers.h"

#include <curl/curl.h>
#include <stdio.h>
#include <string.h>

#ifndef PBD_VERSION
#define PBD_VERSION "dev"
#endif

#define CONNECT_TIMEOUT_SECONDS 15L
#define TOTAL_TIMEOUT_SECONDS 60L

static size_t on_header(char *buffer, size_t size, size_t count, void *userdata)
{
    pbd_fetch_result *result = userdata;
    size_t length = size * count;
    int seconds = pbd_parse_refresh_interval(buffer, length);
    if (seconds > 0) {
        result->refresh_seconds = seconds;
    }
    return length;
}

static int fail(pbd_fetch_result *result, const char *message)
{
    snprintf(result->error, sizeof(result->error), "%s", message);
    return -1;
}

int pbd_fetch_screen(const pbd_config *config, const char *destination_path, pbd_fetch_result *result)
{
    memset(result, 0, sizeof(*result));
    result->refresh_seconds = -1;

    char url[PBD_URL_MAX + 32];
    char authorization[PBD_TOKEN_MAX + 32];
    char partial_path[512];
    if (pbd_config_screen_url(config, url, sizeof(url)) < 0
        || snprintf(authorization, sizeof(authorization), "Authorization: Bearer %s", config->token) >= (int)sizeof(authorization)
        || snprintf(partial_path, sizeof(partial_path), "%s.part", destination_path) >= (int)sizeof(partial_path)) {
        return fail(result, "Configuration trop longue.");
    }

    FILE *file = fopen(partial_path, "wb");
    if (file == NULL) {
        return fail(result, "Impossible d'écrire l'image téléchargée.");
    }

    CURL *curl = curl_easy_init();
    if (curl == NULL) {
        fclose(file);
        return fail(result, "Initialisation réseau impossible.");
    }

    struct curl_slist *headers = curl_slist_append(NULL, authorization);
    curl_easy_setopt(curl, CURLOPT_URL, url);
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);
    curl_easy_setopt(curl, CURLOPT_USERAGENT, "pocketbook-dashboard/" PBD_VERSION);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, file);
    curl_easy_setopt(curl, CURLOPT_HEADERFUNCTION, on_header);
    curl_easy_setopt(curl, CURLOPT_HEADERDATA, result);
    curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
    curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, CONNECT_TIMEOUT_SECONDS);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, TOTAL_TIMEOUT_SECONDS);

    CURLcode code = curl_easy_perform(curl);
    curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &result->http_status);
    curl_slist_free_all(headers);
    curl_easy_cleanup(curl);

    if (fclose(file) != 0 && code == CURLE_OK) {
        code = CURLE_WRITE_ERROR;
    }

    if (code != CURLE_OK) {
        remove(partial_path);
        return fail(result, curl_easy_strerror(code));
    }
    if (result->http_status == 401) {
        remove(partial_path);
        return fail(result, "Clé d'accès refusée par le serveur : générez-en une nouvelle dans l'admin.");
    }
    if (result->http_status != 200) {
        remove(partial_path);
        snprintf(result->error, sizeof(result->error), "Le serveur a répondu HTTP %ld.", result->http_status);
        return -1;
    }
    if (rename(partial_path, destination_path) != 0) {
        remove(partial_path);
        return fail(result, "Impossible d'enregistrer l'image téléchargée.");
    }
    return 0;
}

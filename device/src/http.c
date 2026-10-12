#include "http.h"

#include <curl/curl.h>
#include <stdio.h>
#include <string.h>

#ifndef PBD_VERSION
#define PBD_VERSION "dev"
#endif

#define CONNECT_TIMEOUT_SECONDS 15L
#define TOTAL_TIMEOUT_SECONDS 120L

typedef struct {
    pbd_header_handler handler;
    void *context;
} header_forward;

static size_t on_curl_header(char *buffer, size_t size, size_t count, void *userdata)
{
    const header_forward *forward = userdata;
    size_t length = size * count;
    forward->handler(buffer, length, forward->context);
    return length;
}

static int fail(pbd_http_result *result, const char *message)
{
    snprintf(result->error, sizeof(result->error), "%s", message);
    return -1;
}

int pbd_http_download(
    const char *url, const char *authorization, const char *path, pbd_header_handler on_header, void *context, pbd_http_result *result)
{
    memset(result, 0, sizeof(*result));

    char partial_path[512];
    if (snprintf(partial_path, sizeof(partial_path), "%s.part", path) >= (int)sizeof(partial_path)) {
        return fail(result, "Chemin de téléchargement trop long.");
    }

    FILE *file = fopen(partial_path, "wb");
    if (file == NULL) {
        return fail(result, "Impossible d'écrire le fichier téléchargé.");
    }

    CURL *curl = curl_easy_init();
    if (curl == NULL) {
        fclose(file);
        remove(partial_path);
        return fail(result, "Initialisation réseau impossible.");
    }

    struct curl_slist *headers = authorization != NULL ? curl_slist_append(NULL, authorization) : NULL;
    header_forward forward = { on_header, context };
    curl_easy_setopt(curl, CURLOPT_URL, url);
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);
    curl_easy_setopt(curl, CURLOPT_USERAGENT, "pocketbook-dashboard/" PBD_VERSION);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, file);
    curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
    curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, CONNECT_TIMEOUT_SECONDS);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, TOTAL_TIMEOUT_SECONDS);
    if (on_header != NULL) {
        curl_easy_setopt(curl, CURLOPT_HEADERFUNCTION, on_curl_header);
        curl_easy_setopt(curl, CURLOPT_HEADERDATA, &forward);
    }

    CURLcode code = curl_easy_perform(curl);
    curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &result->status);
    curl_slist_free_all(headers);
    curl_easy_cleanup(curl);

    if (fclose(file) != 0 && code == CURLE_OK) {
        code = CURLE_WRITE_ERROR;
    }
    if (code != CURLE_OK) {
        remove(partial_path);
        return fail(result, curl_easy_strerror(code));
    }
    if (result->status != 200) {
        remove(partial_path);
        snprintf(result->error, sizeof(result->error), "Le serveur a répondu HTTP %ld.", result->status);
        return -1;
    }
    if (rename(partial_path, path) != 0) {
        remove(partial_path);
        return fail(result, "Impossible d'enregistrer le fichier téléchargé.");
    }
    return 0;
}

#include "config.h"

#include <ctype.h>
#include <stdio.h>
#include <string.h>

#define KEY_URL "server_url"
#define KEY_TOKEN "token"

static const char *skip_spaces(const char *start, const char *end)
{
    while (start < end && isspace((unsigned char)*start)) {
        start++;
    }
    return start;
}

static const char *trim_end(const char *start, const char *end)
{
    while (end > start && isspace((unsigned char)end[-1])) {
        end--;
    }
    return end;
}

static int copy_value(char *destination, size_t size, const char *start, const char *end)
{
    size_t length = (size_t)(end - start);
    if (length >= size) {
        return -1;
    }
    memcpy(destination, start, length);
    destination[length] = '\0';
    return 0;
}

static int key_equals(const char *start, const char *end, const char *key)
{
    size_t length = (size_t)(end - start);
    return length == strlen(key) && strncmp(start, key, length) == 0;
}

pbd_config_status pbd_config_parse(const char *text, pbd_config *config)
{
    memset(config, 0, sizeof(*config));

    const char *line = text;
    while (*line != '\0') {
        const char *line_end = strchr(line, '\n');
        if (line_end == NULL) {
            line_end = line + strlen(line);
        }

        const char *start = skip_spaces(line, line_end);
        const char *equals = memchr(start, '=', (size_t)(line_end - start));
        if (start < line_end && *start != '#' && equals != NULL) {
            const char *key_end = trim_end(start, equals);
            const char *value = skip_spaces(equals + 1, line_end);
            const char *value_end = trim_end(value, line_end);

            if (key_equals(start, key_end, KEY_URL) && copy_value(config->server_url, sizeof(config->server_url), value, value_end) != 0) {
                return PBD_CONFIG_TOO_LONG;
            }
            if (key_equals(start, key_end, KEY_TOKEN) && copy_value(config->token, sizeof(config->token), value, value_end) != 0) {
                return PBD_CONFIG_TOO_LONG;
            }
        }

        line = *line_end == '\n' ? line_end + 1 : line_end;
    }

    return pbd_config_validate(config);
}

pbd_config_status pbd_config_validate(pbd_config *config)
{
    size_t length = strlen(config->server_url);
    while (length > 0 && config->server_url[length - 1] == '/') {
        config->server_url[--length] = '\0';
    }

    if (length == 0) {
        return PBD_CONFIG_MISSING_URL;
    }
    if (strncmp(config->server_url, "http://", 7) != 0 && strncmp(config->server_url, "https://", 8) != 0) {
        return PBD_CONFIG_INVALID_URL;
    }
    if (config->token[0] == '\0') {
        return PBD_CONFIG_MISSING_TOKEN;
    }
    return PBD_CONFIG_OK;
}

int pbd_config_format(const pbd_config *config, char *out, size_t size)
{
    int written = snprintf(
        out, size, "# Configuration de PocketBook Dashboard\n" KEY_URL " = %s\n" KEY_TOKEN " = %s\n", config->server_url, config->token);
    return written < 0 || (size_t)written >= size ? -1 : written;
}

static pbd_config_status set_value(char *destination, size_t size, const char *value)
{
    const char *start = skip_spaces(value, value + strlen(value));
    const char *end = trim_end(start, start + strlen(start));
    return copy_value(destination, size, start, end) == 0 ? PBD_CONFIG_OK : PBD_CONFIG_TOO_LONG;
}

pbd_config_status pbd_config_set_url(pbd_config *config, const char *url)
{
    return set_value(config->server_url, sizeof(config->server_url), url);
}

pbd_config_status pbd_config_set_token(pbd_config *config, const char *token)
{
    return set_value(config->token, sizeof(config->token), token);
}

int pbd_config_screen_url(const pbd_config *config, int battery, int charging, char *out, size_t size)
{
    int written = battery >= 0 && battery <= 100
        ? snprintf(out, size, "%s/api/v1/screen?battery=%d&charging=%d", config->server_url, battery, charging ? 1 : 0)
        : snprintf(out, size, "%s/api/v1/screen", config->server_url);
    return written < 0 || (size_t)written >= size ? -1 : written;
}

const char *pbd_config_status_message(pbd_config_status status)
{
    switch (status) {
    case PBD_CONFIG_OK:
        return "Configuration valide.";
    case PBD_CONFIG_MISSING_URL:
        return "Adresse du serveur manquante.";
    case PBD_CONFIG_INVALID_URL:
        return "L'adresse du serveur doit commencer par http:// ou https://.";
    case PBD_CONFIG_MISSING_TOKEN:
        return "Clé d'accès manquante : générez-la dans l'admin, rubrique Liseuses.";
    case PBD_CONFIG_TOO_LONG:
        return "Une valeur de la configuration est trop longue.";
    }
    return "Configuration invalide.";
}

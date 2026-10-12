#ifndef PBD_CLIENT_H
#define PBD_CLIENT_H

#include "config.h"
#include "http.h"

typedef struct {
    pbd_http_result http;
    /* Délai annoncé par le serveur avant le prochain écran, -1 s'il n'en a pas donné. */
    int refresh_seconds;
} pbd_fetch_result;

/* Télécharge l'écran dans destination_path en transmettant le niveau de batterie (-1 s'il est inconnu).
 * Renvoie 0 en cas de succès. */
int pbd_fetch_screen(const pbd_config *config, int battery, int charging, const char *destination_path, pbd_fetch_result *result);

#endif

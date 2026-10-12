#ifndef PBD_CLIENT_H
#define PBD_CLIENT_H

#include "config.h"

#define PBD_ERROR_MAX 256

typedef struct {
    long http_status;
    /* Délai annoncé par le serveur avant le prochain écran, -1 s'il n'en a pas donné. */
    int refresh_seconds;
    char error[PBD_ERROR_MAX];
} pbd_fetch_result;

/*
 * Télécharge l'écran dans destination_path. Le fichier n'est remplacé qu'une fois
 * l'image entièrement reçue. Renvoie 0 en cas de succès.
 */
int pbd_fetch_screen(const pbd_config *config, const char *destination_path, pbd_fetch_result *result);

#endif

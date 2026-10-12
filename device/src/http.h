#ifndef PBD_HTTP_H
#define PBD_HTTP_H

#include <stddef.h>

#define PBD_ERROR_MAX 256

typedef struct {
    long status;
    char error[PBD_ERROR_MAX];
} pbd_http_result;

typedef void (*pbd_header_handler)(const char *line, size_t length, void *context);

/*
 * Télécharge url dans path. Le fichier n'est remplacé qu'une fois la réponse 200 entièrement
 * reçue : une coupure réseau ne laisse jamais un fichier tronqué. Renvoie 0 en cas de succès.
 * authorization (en-tête complet) et on_header sont facultatifs.
 */
int pbd_http_download(
    const char *url, const char *authorization, const char *path, pbd_header_handler on_header, void *context, pbd_http_result *result);

#endif

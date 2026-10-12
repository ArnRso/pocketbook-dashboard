#ifndef PBD_UPDATE_H
#define PBD_UPDATE_H

#include "sha256.h"

#include <stddef.h>

#define PBD_VERSION_MAX 41

/* Contenu de version.txt publié par la CI : la version (commit) puis l'empreinte SHA-256 du binaire. */
typedef struct {
    char version[PBD_VERSION_MAX];
    char sha256[PBD_SHA256_HEX_LENGTH + 1];
} pbd_manifest;

/* Renvoie 0 si text contient une version ([A-Za-z0-9._-], 40 caractères au plus) puis une empreinte valide. */
int pbd_manifest_parse(const char *text, pbd_manifest *manifest);

int pbd_update_available(const char *current_version, const pbd_manifest *manifest);

/* Vérifie l'en-tête d'un exécutable ELF 32 bits ARM : un fichier quelconque ne remplace jamais l'application. */
int pbd_is_arm_executable(const unsigned char *header, size_t length);

#endif

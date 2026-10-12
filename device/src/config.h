#ifndef PBD_CONFIG_H
#define PBD_CONFIG_H

#include <stddef.h>

#define PBD_URL_MAX 256
#define PBD_TOKEN_MAX 128

/* Configuration de la liseuse : adresse du serveur et clé d'accès générée dans l'admin. */
typedef struct {
    char server_url[PBD_URL_MAX];
    char token[PBD_TOKEN_MAX];
} pbd_config;

typedef enum {
    PBD_CONFIG_OK,
    PBD_CONFIG_MISSING_URL,
    PBD_CONFIG_INVALID_URL,
    PBD_CONFIG_MISSING_TOKEN,
    PBD_CONFIG_TOO_LONG,
} pbd_config_status;

/*
 * Lit un fichier « clé = valeur » (server_url, token). Les lignes vides et les
 * commentaires (#) sont ignorés, les espaces autour des valeurs aussi.
 */
pbd_config_status pbd_config_parse(const char *text, pbd_config *config);

/* Vérifie la configuration et normalise l'URL (sans « / » final). */
pbd_config_status pbd_config_validate(pbd_config *config);

/* Écrit la configuration au format lu par pbd_config_parse. Renvoie -1 si out est trop petit. */
int pbd_config_format(const pbd_config *config, char *out, size_t size);

/* Remplace une valeur saisie par l'utilisateur ; renvoie PBD_CONFIG_TOO_LONG si elle ne tient pas. */
pbd_config_status pbd_config_set_url(pbd_config *config, const char *url);
pbd_config_status pbd_config_set_token(pbd_config *config, const char *token);

/* Adresse de l'écran à afficher : <server_url>/api/v1/screen?battery=87&charging=0, sans le niveau de
 * batterie s'il est inconnu (hors de 0 à 100). Renvoie -1 si out est trop petit. */
int pbd_config_screen_url(const pbd_config *config, int battery, int charging, char *out, size_t size);

/* Explication lisible par l'utilisateur. */
const char *pbd_config_status_message(pbd_config_status status);

#endif

#include "updater.h"

#include <stdio.h>
#include <string.h>

static int fail(pbd_http_result *result, const char *message)
{
    snprintf(result->error, sizeof(result->error), "%s", message);
    return -1;
}

int pbd_updater_check(const char *work_directory, pbd_manifest *manifest, pbd_http_result *result)
{
    char path[512];
    snprintf(path, sizeof(path), "%s/pocketbook-dashboard.version", work_directory);
    if (pbd_http_download(PBD_RELEASE_URL "/version.txt", NULL, path, NULL, NULL, result) != 0) {
        return -1;
    }

    char text[256] = "";
    FILE *file = fopen(path, "r");
    if (file == NULL) {
        return fail(result, "Impossible de lire la version publiée.");
    }
    size_t length = fread(text, 1, sizeof(text) - 1, file);
    text[length] = '\0';
    fclose(file);
    remove(path);

    return pbd_manifest_parse(text, manifest) == 0 ? 0 : fail(result, "Version publiée illisible.");
}

/* Calcule l'empreinte du fichier et lit son en-tête ; renvoie 0 si les deux sont conformes. */
static int verify(const char *path, const pbd_manifest *manifest, pbd_http_result *result)
{
    FILE *file = fopen(path, "rb");
    if (file == NULL) {
        return fail(result, "Impossible de relire la mise à jour téléchargée.");
    }

    pbd_sha256 sha256;
    pbd_sha256_init(&sha256);
    unsigned char buffer[4096];
    unsigned char header[20] = { 0 };
    size_t header_length = 0;
    size_t length;
    while ((length = fread(buffer, 1, sizeof(buffer), file)) > 0) {
        if (header_length == 0) {
            header_length = length < sizeof(header) ? length : sizeof(header);
            memcpy(header, buffer, header_length);
        }
        pbd_sha256_update(&sha256, buffer, length);
    }
    fclose(file);

    char hex[PBD_SHA256_HEX_LENGTH + 1];
    pbd_sha256_final_hex(&sha256, hex);
    if (strcmp(hex, manifest->sha256) != 0) {
        return fail(result, "Empreinte de la mise à jour incorrecte.");
    }
    if (!pbd_is_arm_executable(header, header_length)) {
        return fail(result, "La mise à jour n'est pas une application PocketBook.");
    }
    return 0;
}

int pbd_updater_install(const pbd_manifest *manifest, const char *app_path, pbd_http_result *result)
{
    char new_path[512];
    if (snprintf(new_path, sizeof(new_path), "%s.new", app_path) >= (int)sizeof(new_path)) {
        return fail(result, "Chemin de l'application trop long.");
    }

    if (pbd_http_download(PBD_RELEASE_URL "/pocketbook-dashboard", NULL, new_path, NULL, NULL, result) != 0) {
        return -1;
    }
    if (verify(new_path, manifest, result) != 0) {
        remove(new_path);
        return -1;
    }
    // Les droits d'exécution viennent du montage de la mémoire de la liseuse : pas de chmod nécessaire
    if (rename(new_path, app_path) != 0) {
        remove(new_path);
        return fail(result, "Impossible de remplacer l'application.");
    }
    return 0;
}

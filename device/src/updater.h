#ifndef PBD_UPDATER_H
#define PBD_UPDATER_H

#include "http.h"
#include "update.h"

/* Release publiée par la CI à chaque push sur main. */
#define PBD_RELEASE_URL "https://github.com/ArnRso/pocketbook-dashboard/releases/download/device-latest"

/* Télécharge et lit version.txt. Renvoie 0 en cas de succès. */
int pbd_updater_check(const char *work_directory, pbd_manifest *manifest, pbd_http_result *result);

/*
 * Télécharge le binaire à côté de app_path, vérifie son empreinte et son en-tête, puis remplace
 * app_path. En cas d'échec, l'application installée reste intacte. Renvoie 0 en cas de succès.
 */
int pbd_updater_install(const pbd_manifest *manifest, const char *app_path, pbd_http_result *result);

#endif

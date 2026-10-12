#ifndef PBD_HEADERS_H
#define PBD_HEADERS_H

#include <stddef.h>

#define PBD_REFRESH_MIN 60
#define PBD_REFRESH_MAX 86400

/*
 * Lit une ligne d'en-tête HTTP. S'il s'agit de « X-Refresh-Interval: <secondes> »,
 * renvoie le délai borné entre PBD_REFRESH_MIN et PBD_REFRESH_MAX, sinon -1.
 */
int pbd_parse_refresh_interval(const char *line, size_t length);

#endif

#ifndef PBD_SHA256_H
#define PBD_SHA256_H

#include <stddef.h>
#include <stdint.h>

#define PBD_SHA256_HEX_LENGTH 64

typedef struct {
    uint32_t state[8];
    uint64_t length;
    unsigned char block[64];
    size_t used;
} pbd_sha256;

void pbd_sha256_init(pbd_sha256 *context);
void pbd_sha256_update(pbd_sha256 *context, const void *data, size_t length);

/* Écrit l'empreinte en hexadécimal minuscule ; hex doit pouvoir contenir PBD_SHA256_HEX_LENGTH + 1 caractères. */
void pbd_sha256_final_hex(pbd_sha256 *context, char *hex);

#endif

#include "sha256.h"

#include <stdio.h>
#include <string.h>

/* SHA-256 (FIPS 180-4) : l'OpenSSL du SDK est trop ancien pour qu'on dépende de lui ici. */

static const uint32_t K[64] = {
    0x428a2f98,
    0x71374491,
    0xb5c0fbcf,
    0xe9b5dba5,
    0x3956c25b,
    0x59f111f1,
    0x923f82a4,
    0xab1c5ed5,
    0xd807aa98,
    0x12835b01,
    0x243185be,
    0x550c7dc3,
    0x72be5d74,
    0x80deb1fe,
    0x9bdc06a7,
    0xc19bf174,
    0xe49b69c1,
    0xefbe4786,
    0x0fc19dc6,
    0x240ca1cc,
    0x2de92c6f,
    0x4a7484aa,
    0x5cb0a9dc,
    0x76f988da,
    0x983e5152,
    0xa831c66d,
    0xb00327c8,
    0xbf597fc7,
    0xc6e00bf3,
    0xd5a79147,
    0x06ca6351,
    0x14292967,
    0x27b70a85,
    0x2e1b2138,
    0x4d2c6dfc,
    0x53380d13,
    0x650a7354,
    0x766a0abb,
    0x81c2c92e,
    0x92722c85,
    0xa2bfe8a1,
    0xa81a664b,
    0xc24b8b70,
    0xc76c51a3,
    0xd192e819,
    0xd6990624,
    0xf40e3585,
    0x106aa070,
    0x19a4c116,
    0x1e376c08,
    0x2748774c,
    0x34b0bcb5,
    0x391c0cb3,
    0x4ed8aa4a,
    0x5b9cca4f,
    0x682e6ff3,
    0x748f82ee,
    0x78a5636f,
    0x84c87814,
    0x8cc70208,
    0x90befffa,
    0xa4506ceb,
    0xbef9a3f7,
    0xc67178f2,
};

static uint32_t rotate_right(uint32_t value, unsigned int bits)
{
    return (value >> bits) | (value << (32 - bits));
}

static void process_block(pbd_sha256 *context, const unsigned char *block)
{
    uint32_t w[64];
    for (int i = 0; i < 16; i++) {
        w[i] = (uint32_t)block[i * 4] << 24 | (uint32_t)block[i * 4 + 1] << 16 | (uint32_t)block[i * 4 + 2] << 8
            | (uint32_t)block[i * 4 + 3];
    }
    for (int i = 16; i < 64; i++) {
        uint32_t s0 = rotate_right(w[i - 15], 7) ^ rotate_right(w[i - 15], 18) ^ (w[i - 15] >> 3);
        uint32_t s1 = rotate_right(w[i - 2], 17) ^ rotate_right(w[i - 2], 19) ^ (w[i - 2] >> 10);
        w[i] = w[i - 16] + s0 + w[i - 7] + s1;
    }

    uint32_t a = context->state[0], b = context->state[1], c = context->state[2], d = context->state[3];
    uint32_t e = context->state[4], f = context->state[5], g = context->state[6], h = context->state[7];
    for (int i = 0; i < 64; i++) {
        uint32_t t1 = h + (rotate_right(e, 6) ^ rotate_right(e, 11) ^ rotate_right(e, 25)) + ((e & f) ^ (~e & g)) + K[i] + w[i];
        uint32_t t2 = (rotate_right(a, 2) ^ rotate_right(a, 13) ^ rotate_right(a, 22)) + ((a & b) ^ (a & c) ^ (b & c));
        h = g;
        g = f;
        f = e;
        e = d + t1;
        d = c;
        c = b;
        b = a;
        a = t1 + t2;
    }

    context->state[0] += a;
    context->state[1] += b;
    context->state[2] += c;
    context->state[3] += d;
    context->state[4] += e;
    context->state[5] += f;
    context->state[6] += g;
    context->state[7] += h;
}

void pbd_sha256_init(pbd_sha256 *context)
{
    static const uint32_t initial[8] = { 0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19 };
    memcpy(context->state, initial, sizeof(initial));
    context->length = 0;
    context->used = 0;
}

void pbd_sha256_update(pbd_sha256 *context, const void *data, size_t length)
{
    const unsigned char *bytes = data;
    context->length += length;
    while (length > 0) {
        size_t chunk = sizeof(context->block) - context->used;
        if (chunk > length) {
            chunk = length;
        }
        memcpy(context->block + context->used, bytes, chunk);
        context->used += chunk;
        bytes += chunk;
        length -= chunk;
        if (context->used == sizeof(context->block)) {
            process_block(context, context->block);
            context->used = 0;
        }
    }
}

void pbd_sha256_final_hex(pbd_sha256 *context, char *hex)
{
    uint64_t bits = context->length * 8;
    unsigned char padding[72] = { 0x80 };
    size_t padding_length = (context->used < 56 ? 56 : 120) - context->used;
    for (int i = 0; i < 8; i++) {
        padding[padding_length + (size_t)i] = (unsigned char)(bits >> (56 - 8 * i));
    }
    pbd_sha256_update(context, padding, padding_length + 8);

    for (int i = 0; i < 8; i++) {
        snprintf(hex + i * 8, 9, "%08x", context->state[i]);
    }
}

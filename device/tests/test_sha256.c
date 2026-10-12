#include "../src/sha256.h"
#include "check.h"

static void hash(const char *message, char *hex)
{
    pbd_sha256 sha256;
    pbd_sha256_init(&sha256);
    pbd_sha256_update(&sha256, message, strlen(message));
    pbd_sha256_final_hex(&sha256, hex);
}

void test_sha256(void)
{
    char hex[PBD_SHA256_HEX_LENGTH + 1];

    // Vecteurs du NIST (FIPS 180-4)
    hash("", hex);
    CHECK_STR("e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855", hex);
    hash("abc", hex);
    CHECK_STR("ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad", hex);
    hash("abcdbcdecdefdefgefghfghighijhijkijkljklmklmnlmnomnopnopq", hex);
    CHECK_STR("248d6a61d20638b8e5c026930c3e6039a33ce45964ff2167f6ecedd419db06c1", hex);

    // Un million de « a », par morceaux de taille irrégulière qui chevauchent les blocs de 64 octets
    pbd_sha256 sha256;
    pbd_sha256_init(&sha256);
    char chunk[997];
    memset(chunk, 'a', sizeof(chunk));
    size_t remaining = 1000000;
    while (remaining > 0) {
        size_t length = remaining < sizeof(chunk) ? remaining : sizeof(chunk);
        pbd_sha256_update(&sha256, chunk, length);
        remaining -= length;
    }
    pbd_sha256_final_hex(&sha256, hex);
    CHECK_STR("cdc76e5c9914fb9281a1c7e284d73e67f1809a48a497200e046d39ccc7112cd0", hex);

    // Longueurs proches de la limite du remplissage (55, 56 et 64 octets)
    hash("aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa", hex);
    CHECK_STR("9f4390f8d30c2dd92ec9f095b65e2b9ae9b0a925a5258e241c9f1e910f734318", hex);
    hash("aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa", hex);
    CHECK_STR("b35439a4ac6f0948b6d6f9e3c6af0f5f590ce20f1bde7090ef7970686ec6738a", hex);
    hash("aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa", hex);
    CHECK_STR("ffe054fe7ae0cb6dc65c3af9b61d5209f439851db43d0ba5997337df154668eb", hex);
}

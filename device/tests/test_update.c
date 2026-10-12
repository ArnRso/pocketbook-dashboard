#include "../src/update.h"
#include "check.h"

#define HASH "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad"

static void parses_the_published_manifest(void)
{
    pbd_manifest manifest;

    CHECK_INT(0, pbd_manifest_parse("ca12213\n" HASH "\n", &manifest));
    CHECK_STR("ca12213", manifest.version);
    CHECK_STR(HASH, manifest.sha256);

    CHECK_INT(0, pbd_manifest_parse("v1.2.0-rc_1\r\n" HASH, &manifest));
    CHECK_STR("v1.2.0-rc_1", manifest.version);
}

static void rejects_invalid_manifests(void)
{
    pbd_manifest manifest;

    CHECK_INT(-1, pbd_manifest_parse("", &manifest));
    CHECK_INT(-1, pbd_manifest_parse("ca12213\n", &manifest));
    CHECK_INT(-1, pbd_manifest_parse("\n" HASH "\n", &manifest));
    CHECK_INT(-1, pbd_manifest_parse("ca 12213\n" HASH "\n", &manifest));
    CHECK_INT(-1, pbd_manifest_parse("ca12213\nBA7816BF8F01CFEA414140DE5DAE2223B00361A396177A9CB410FF61F20015AD\n", &manifest));
    CHECK_INT(-1, pbd_manifest_parse("ca12213\nba7816bf\n", &manifest));
    CHECK_INT(-1, pbd_manifest_parse("ca12213\n" HASH "0\n", &manifest));
    CHECK_INT(-1, pbd_manifest_parse("<html>Not Found</html>", &manifest));
    CHECK_STR("", manifest.version);

    char long_version[64];
    memset(long_version, 'a', 41);
    snprintf(long_version + 41, sizeof(long_version) - 41, "\n%s", "x");
    CHECK_INT(-1, pbd_manifest_parse(long_version, &manifest));
}

static void compares_versions(void)
{
    pbd_manifest manifest;
    pbd_manifest_parse("ca12213\n" HASH "\n", &manifest);

    CHECK(pbd_update_available("dc18775", &manifest));
    CHECK(!pbd_update_available("ca12213", &manifest));

    pbd_manifest empty = { "", "" };
    CHECK(!pbd_update_available("dc18775", &empty));
}

static void recognizes_arm_executables(void)
{
    // En-tête ELF de l'application compilée par le SDK : 32 bits, little-endian, exécutable, ARM
    unsigned char header[20] = { 0x7f, 'E', 'L', 'F', 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 0, 40, 0 };
    CHECK(pbd_is_arm_executable(header, sizeof(header)));
    CHECK(!pbd_is_arm_executable(header, 19));

    unsigned char other[20];
    memcpy(other, header, sizeof(other));
    other[0] = '<';
    CHECK(!pbd_is_arm_executable(other, sizeof(other)));

    memcpy(other, header, sizeof(other));
    other[4] = 2; // 64 bits
    CHECK(!pbd_is_arm_executable(other, sizeof(other)));

    memcpy(other, header, sizeof(other));
    other[5] = 2; // big-endian
    CHECK(!pbd_is_arm_executable(other, sizeof(other)));

    memcpy(other, header, sizeof(other));
    other[16] = 3; // bibliothèque partagée
    CHECK(!pbd_is_arm_executable(other, sizeof(other)));

    memcpy(other, header, sizeof(other));
    other[18] = 62; // x86-64
    CHECK(!pbd_is_arm_executable(other, sizeof(other)));
}

void test_update(void)
{
    parses_the_published_manifest();
    rejects_invalid_manifests();
    compares_versions();
    recognizes_arm_executables();
}

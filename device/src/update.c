#include "update.h"

#include <ctype.h>
#include <string.h>

#define ELF_HEADER_LENGTH 20
#define ELF_CLASS_32 1
#define ELF_LITTLE_ENDIAN 1
#define ELF_TYPE_EXECUTABLE 2
#define ELF_MACHINE_ARM 40

static int is_version_character(char character)
{
    return isalnum((unsigned char)character) || character == '.' || character == '_' || character == '-';
}

static int is_lowercase_hex(char character)
{
    return (character >= '0' && character <= '9') || (character >= 'a' && character <= 'f');
}

/* Copie le mot qui commence à *cursor tant que accept() l'accepte, puis passe la fin de ligne. */
static size_t read_line(const char **cursor, char *destination, size_t size, int (*accept)(char))
{
    size_t length = 0;
    while ((*cursor)[length] != '\0' && accept((*cursor)[length])) {
        if (length + 1 >= size) {
            return 0;
        }
        destination[length] = (*cursor)[length];
        length++;
    }
    destination[length] = '\0';

    const char *end = *cursor + length;
    if (*end == '\r') {
        end++;
    }
    if (*end != '\n' && *end != '\0') {
        return 0;
    }
    *cursor = *end == '\n' ? end + 1 : end;
    return length;
}

int pbd_manifest_parse(const char *text, pbd_manifest *manifest)
{
    memset(manifest, 0, sizeof(*manifest));
    const char *cursor = text;

    if (read_line(&cursor, manifest->version, sizeof(manifest->version), is_version_character) == 0
        || read_line(&cursor, manifest->sha256, sizeof(manifest->sha256), is_lowercase_hex) != PBD_SHA256_HEX_LENGTH) {
        memset(manifest, 0, sizeof(*manifest));
        return -1;
    }
    return 0;
}

int pbd_update_available(const char *current_version, const pbd_manifest *manifest)
{
    return manifest->version[0] != '\0' && strcmp(current_version, manifest->version) != 0;
}

int pbd_is_arm_executable(const unsigned char *header, size_t length)
{
    return length >= ELF_HEADER_LENGTH && memcmp(header, "\177ELF", 4) == 0 && header[4] == ELF_CLASS_32 && header[5] == ELF_LITTLE_ENDIAN
        && (header[16] | header[17] << 8) == ELF_TYPE_EXECUTABLE && (header[18] | header[19] << 8) == ELF_MACHINE_ARM;
}

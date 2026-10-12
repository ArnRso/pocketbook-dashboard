#ifndef PBD_CHECK_H
#define PBD_CHECK_H

#include <stdio.h>
#include <string.h>

/* Mini-framework de test : chaque vérification ratée est affichée et fait échouer l'exécution. */
extern int pbd_failures;

#define CHECK(condition)                                                            \
    do {                                                                            \
        if (!(condition)) {                                                         \
            fprintf(stderr, "%s:%d: échec : %s\n", __FILE__, __LINE__, #condition); \
            pbd_failures++;                                                         \
        }                                                                           \
    } while (0)

#define CHECK_INT(expected, actual)                                                                                  \
    do {                                                                                                             \
        long long check_expected = (expected);                                                                       \
        long long check_actual = (actual);                                                                           \
        if (check_expected != check_actual) {                                                                        \
            fprintf(stderr, "%s:%d: attendu %lld, obtenu %lld\n", __FILE__, __LINE__, check_expected, check_actual); \
            pbd_failures++;                                                                                          \
        }                                                                                                            \
    } while (0)

#define CHECK_STR(expected, actual)                                                                              \
    do {                                                                                                         \
        if (strcmp((expected), (actual)) != 0) {                                                                 \
            fprintf(stderr, "%s:%d: attendu « %s », obtenu « %s »\n", __FILE__, __LINE__, (expected), (actual)); \
            pbd_failures++;                                                                                      \
        }                                                                                                        \
    } while (0)

void test_config(void);
void test_headers(void);
void test_sha256(void);
void test_update(void);

#endif

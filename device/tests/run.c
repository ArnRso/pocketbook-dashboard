#include "check.h"

int pbd_failures = 0;

int main(void)
{
    test_config();
    test_headers();
    test_sha256();
    test_update();

    if (pbd_failures > 0) {
        fprintf(stderr, "%d vérification(s) en échec\n", pbd_failures);
        return 1;
    }
    printf("Tous les tests passent\n");
    return 0;
}

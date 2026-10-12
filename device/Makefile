# Application PocketBook : compilation croisée avec le SDK officiel, tests natifs et analyse statique.
#
# Dans la chaîne de compilation (Docker) : make qa | lint | test | build
# Depuis le Mac :                         make docker-qa | docker-build, puis make install

APP := pocketbook-dashboard.app
VERSION ?= dev

SDK ?= /opt/pocketbook-sdk
SYSROOT := $(SDK)/usr/arm-obreey-linux-gnueabi/sysroot
CROSS_CC := $(SDK)/usr/bin/arm-obreey-linux-gnueabi-gcc

WARNINGS := -Wall -Wextra -Wpedantic -Werror -Wshadow -Wconversion -Wformat=2 -Wstrict-prototypes -Wmissing-prototypes
CFLAGS := -std=c11 -D_DEFAULT_SOURCE -O2 $(WARNINGS) -DPBD_VERSION='"$(VERSION)"'

CORE := src/config.c src/headers.c src/sha256.c src/update.c
SOURCES := $(CORE) src/http.c src/client.c src/updater.c src/main.c
HEADERS := $(wildcard src/*.h)
TESTS := $(wildcard tests/*.c)
FORMATTED := $(wildcard src/*.[ch] tests/*.[ch])

# AddressSanitizer ne fonctionne pas sous l'émulation x86 des Mac Apple Silicon (Rosetta) :
# il n'est désactivé que là, la CI (x86 natif) l'exécute.
SANITIZE ?= address,undefined
DOCKER_SANITIZE := $(if $(filter arm64,$(shell uname -m)),undefined,$(SANITIZE))

IMAGE := pocketbook-dashboard-sdk
DEVICE_APPS := /Volumes/PB741/applications

.PHONY: qa lint test build format clean docker-image install

qa: lint test build

lint:
	clang-format --dry-run --Werror $(FORMATTED)
	cppcheck --quiet --std=c11 --error-exitcode=1 --inline-suppr \
		--enable=warning,style,performance,portability --suppress=missingIncludeSystem \
		-D_DEFAULT_SOURCE -I src src tests

# Toujours recompilés : les options (sanitizers) changent selon la machine
test:
	mkdir -p build
	$(CC) $(CFLAGS) -O1 -g -fsanitize=$(SANITIZE) -fno-sanitize-recover=all -o build/run-tests $(CORE) $(TESTS)
	./build/run-tests

build: dist/$(APP)

dist/$(APP): $(SOURCES) $(HEADERS)
	mkdir -p dist
	$(CROSS_CC) $(CFLAGS) -march=armv7-a -mtune=cortex-a8 -mfpu=neon -mfloat-abi=softfp -s -o $@ $(SOURCES) -linkview -lcurl

format:
	clang-format -i $(FORMATTED)

clean:
	rm -rf build dist

docker-image:
	docker build --platform linux/amd64 -t $(IMAGE) .

docker-%: docker-image
	docker run --rm --platform linux/amd64 -v "$(CURDIR)":/src $(IMAGE) make $* VERSION=$(VERSION) SANITIZE=$(DOCKER_SANITIZE)

install: dist/$(APP)
	@test -d $(DEVICE_APPS) || { echo "Liseuse non montée : branchez-la en USB et choisissez « Connexion à l'ordinateur »."; exit 1; }
	cp dist/$(APP) $(DEVICE_APPS)/
	@# macOS ajoute un fichier de métadonnées que la liseuse afficherait comme une application « ._… »
	rm -f $(DEVICE_APPS)/._$(APP)
	@echo "Installée : lancez « pocketbook-dashboard » depuis les Applications de la liseuse."

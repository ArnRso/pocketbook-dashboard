# syntax=docker/dockerfile:1

# Chaîne de compilation PocketBook : SDK officiel 6.5, plateforme B288 (InkPad Color et liseuses récentes).
# Le compilateur du SDK est un binaire Linux x86-64 : l'image est toujours construite en amd64.
FROM debian:bookworm-slim

ARG SDK_COMMIT=dd8de463733a2bc0d53d1214b64734586fac427e

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates clang-format cppcheck gcc git libc6-dev libmpc3 make \
    && rm -rf /var/lib/apt/lists/* \
    # Le compilateur du SDK a été lié contre libmpfr 4, absente des distributions récentes ; la version 6 la remplace
    && ln -s /usr/lib/x86_64-linux-gnu/libmpfr.so.6 /usr/lib/x86_64-linux-gnu/libmpfr.so.4

RUN git init -q /tmp/sdk \
    && git -C /tmp/sdk fetch -q --depth 1 https://github.com/pocketbook/SDK_6.3.0.git "$SDK_COMMIT" \
    && git -C /tmp/sdk checkout -q FETCH_HEAD \
    && mv /tmp/sdk/SDK-B288 /opt/pocketbook-sdk \
    && rm -rf /tmp/sdk

WORKDIR /src

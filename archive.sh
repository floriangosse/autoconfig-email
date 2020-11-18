#!/usr/bin/env bash

if [[ -z "$1" ]] || [[ "$1" == "-h" ]] || [[ "$1" == "--help" ]]; then
    echo "Usage: $0 [<version>]";

    if [[ -z "$1" ]]; then
        exit 1
    else
        exit 0
    fi
fi

VERSION=$1
FILENAME="release/autodiscover-email-${VERSION}.tar.gz"

tar --create \
    --gzip \
    --file "${FILENAME}" \
    --exclude app/config/config.json \
        index.php \
        vendor \
        app
#!/usr/bin/env bash
# Levanta Sail y actualiza los alquileres (rentals:analyze desde local, contra la base del contenedor).
set -euo pipefail

cd "$(dirname "$0")/.."

./vendor/bin/sail up -d --wait

DB_HOST=127.0.0.1 DB_PORT=3307 php artisan rentals:analyze

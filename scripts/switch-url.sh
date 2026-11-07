#!/usr/bin/env bash
# Cambia APP_URL entre localhost y un túnel de ngrok (para probar desde el celu).
#
#   ./scripts/switch-url.sh ngrok   # levanta ngrok (si no está corriendo) y usa su URL https
#   ./scripts/switch-url.sh local   # vuelve a http://localhost y apaga ngrok
#
# Solo reemplaza la línea activa "APP_URL=..." del .env; las comentadas quedan como están.
set -euo pipefail

cd "$(dirname "$0")/.."

LOCAL_URL="http://localhost"
NGROK_API="http://localhost:4040/api/tunnels"
NGROK_LOG="storage/logs/ngrok.log"

set_app_url() {
    sed -i.bak "s|^APP_URL=.*|APP_URL=$1|" .env && rm -f .env.bak
}

clear_config() {
    # Sail if it's running, local PHP otherwise.
    ./vendor/bin/sail artisan config:clear >/dev/null 2>&1 || php artisan config:clear >/dev/null
}

ngrok_url() {
    curl -s "$NGROK_API" 2>/dev/null \
        | python3 -c "import sys, json; print(next((t['public_url'] for t in json.load(sys.stdin)['tunnels'] if t['public_url'].startswith('https')), ''))" 2>/dev/null \
        || true
}

use_ngrok() {
    local url
    url="$(ngrok_url)"

    if [ -z "$url" ]; then
        echo "Levantando ngrok (log en $NGROK_LOG)..."
        nohup ngrok http 80 --log=stdout >"$NGROK_LOG" 2>&1 &
        for _ in $(seq 1 20); do
            url="$(ngrok_url)"
            [ -n "$url" ] && break
            sleep 1
        done
    fi

    if [ -z "$url" ]; then
        echo "❌ ngrok no levantó. Revisá $NGROK_LOG" >&2
        exit 1
    fi

    set_app_url "$url"
    clear_config
    echo "✅ APP_URL=$url"
    echo "   Abrí desde el celu: $url"
}

use_local() {
    pkill -f "ngrok http" 2>/dev/null && echo "ngrok apagado." || true

    set_app_url "$LOCAL_URL"
    clear_config
    echo "✅ APP_URL=$LOCAL_URL"
}

case "${1:-}" in
    ngrok) use_ngrok ;;
    local) use_local ;;
    *)
        echo "Uso: $0 ngrok|local" >&2
        echo "Actual: $(grep '^APP_URL=' .env)" >&2
        exit 1
        ;;
esac

# Extiende la imagen oficial de Sail
FROM laravelsail/php84-composer

# Instala Chrome y dependencias para Puppeteer
RUN apt-get update && apt-get install -y \
    chromium \
    fonts-noto-color-emoji \
    libatk-bridge2.0-0 \
    libgtk-3-0 \
    libgbm1 \
    libasound2 \
    libnss3 \
    libxss1 \
    xdg-utils \
    --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*



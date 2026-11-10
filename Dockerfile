# Ambiente de desenvolvimento: PHP 8.1 (versão mínima do projeto) com Composer 2.
FROM php:8.1-cli

# unzip: necessário para o Composer extrair os pacotes baixados.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# HOME gravável para qualquer UID (o container roda com o usuário do host).
ENV HOME=/tmp \
    COMPOSER_HOME=/tmp/composer

WORKDIR /app

# Profit Funnel engine runner.
#
# Runs ONLY the funnel engine (bin/funnel) on a schedule so it can be deployed
# to a $5 droplet / Railway / Fly without standing up the full web app.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --no-interaction --no-progress --no-dev \
    && mkdir -p storage/funnel

# The engine stays dormant until FUNNEL_ENABLED=true is set in the environment.
ENV FUNNEL_ENABLED=false
ENV FUNNEL_RUN_INTERVAL=300

ENTRYPOINT ["bash", "deploy/run-loop.sh"]

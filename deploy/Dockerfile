# Profit Funnel engine runner.
#
# Runs ONLY the funnel engine (bin/funnel) on a schedule so it can be deployed
# to a $5 droplet / Railway / Fly without standing up the full legacy web app.
# The engine needs PHP 8.1+ (readonly properties); the legacy framework's
# lockfile pins PHP 7 packages, so we install with the platform check relaxed
# and skip the framework's post-install scripts (the engine never boots Laravel).
FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --no-interaction --no-progress --no-dev \
        --ignore-platform-reqs --no-scripts \
    && mkdir -p storage/funnel

# The engine stays dormant until FUNNEL_ENABLED=true is set in the environment.
ENV FUNNEL_ENABLED=false
ENV FUNNEL_RUN_INTERVAL=300

ENTRYPOINT ["bash", "deploy/run-loop.sh"]

FROM php:8.4-fpm-trixie

# Install common tools
RUN apt-get update && \
    apt-get upgrade --yes && \
    apt-get install --no-install-recommends --yes \
        zip \
        unzip \
        && \
    rm -r /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version
FROM php:7-fpm-buster

# Install common tools
RUN apt-get update && \
    apt-get install --no-install-recommends -y \
        zip \
        unzip \
        && \
    rm -r /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version
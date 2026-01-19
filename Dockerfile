FROM php:8.1-apache

# Install SQLite extension
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Configure PHP
RUN echo "short_open_tag = On" >> /usr/local/etc/php/php.ini && \
    echo "display_errors = On" >> /usr/local/etc/php/php.ini && \
    echo "error_reporting = E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING" >> /usr/local/etc/php/php.ini && \
    echo "upload_max_filesize = 10M" >> /usr/local/etc/php/php.ini && \
    echo "post_max_size = 10M" >> /usr/local/etc/php/php.ini

# Create directories
RUN mkdir -p /var/www/html/uploads /var/www/html/pdf /var/www/html/data && \
    chown -R www-data:www-data /var/www/html

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

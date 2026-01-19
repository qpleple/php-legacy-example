FROM php:5.6-apache

# Install MySQL extension (legacy style)
RUN docker-php-ext-install mysql mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Configure PHP for legacy behavior
RUN echo "short_open_tag = On" >> /usr/local/etc/php/php.ini && \
    echo "display_errors = On" >> /usr/local/etc/php/php.ini && \
    echo "error_reporting = E_ALL & ~E_NOTICE & ~E_DEPRECATED" >> /usr/local/etc/php/php.ini && \
    echo "upload_max_filesize = 10M" >> /usr/local/etc/php/php.ini && \
    echo "post_max_size = 10M" >> /usr/local/etc/php/php.ini

# Create upload directories
RUN mkdir -p /var/www/html/uploads /var/www/html/pdf && \
    chown -R www-data:www-data /var/www/html

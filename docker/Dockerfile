FROM php:8.2-apache

# Install PDO MySQL extension for MySQL database connectivity
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy PHP application source code into the web server directory
COPY src/ /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Give correct ownership and permissions for safety
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose HTTP port
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]

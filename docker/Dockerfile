FROM php:8.2-apache

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libcurl4-openssl-dev libssl-dev \
    zip unzip git \
    && docker-php-ext-install pdo pdo_mysql mysqli curl \
    && docker-php-ext-enable pdo_mysql mysqli \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# ── Set DocumentRoot to /var/www/html/dashboard ──────────────────────────────
ENV APACHE_DOCUMENT_ROOT=/var/www/html/dashboard

RUN sed -ri -e 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/dashboard|g' \
        /etc/apache2/sites-available/000-default.conf && \
    sed -ri -e 's|<Directory /var/www/html>|<Directory /var/www/html/dashboard>|g' \
        /etc/apache2/apache2.conf 2>/dev/null || true

# Allow .htaccess overrides in the dashboard directory
RUN printf '<Directory /var/www/html/dashboard>\n\tAllowOverride All\n\tOptions -Indexes +FollowSymLinks\n\tRequire all granted\n</Directory>\n' \
    >> /etc/apache2/apache2.conf

# PHP tuning
RUN printf 'upload_max_filesize=64M\npost_max_size=64M\nmax_execution_time=120\nmemory_limit=256M\n' \
    > /usr/local/etc/php/conf.d/custom.ini

# Copy entire project
COPY . /var/www/html/

# Set permissions and create a symlink to seamlessly route hardcoded "/dashboard/" paths
RUN mkdir -p /var/www/html/logs /var/www/html/dashboard && \
    ln -s . /var/www/html/dashboard/dashboard && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /var/www/html/logs

EXPOSE 80

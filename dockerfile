# Use PHP 7.4 with Apache
FROM php:7.4-apache

# Set working directory
WORKDIR /var/www/html

# Install necessary packages
RUN apt-get update && apt-get install -y \
    bash \
    libpq-dev \
    iputils-ping \
    nano \
    openssh-server \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install pgsql pdo_pgsql bcmath

# Enable Apache rewrite
RUN a2enmod rewrite

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy app files
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Setup SSH
RUN mkdir /var/run/sshd

# Add an SFTP user with password login
RUN useradd -m ftpuser && echo 'ftpuser:ftppass' | chpasswd

# Optional: limit this user to SFTP only and only the /var/www/html child directories
# RUN echo "Match User ftpuser\n\
#     ChrootDirectory /var/www/html\n\
#     ForceCommand internal-sftp\n\
#     X11Forwarding no\n\
#     AllowTcpForwarding no" >> /etc/ssh/sshd_config

# Change ownership for chroot to work properly
RUN chown root:root /var/www/html && chmod 755 /var/www/html

# Create config folder and log file if not already there
# RUN mkdir -p /var/www/html/jtm-fh-local-1.0/config && \
#     touch /var/www/html/jtm-fh-local-1.0/config/errors.log && \
#     chown -R www-data:www-data /var/www/html/jtm-fh-local-1.0/config && \
#     chmod 664 /var/www/html/jtm-fh-local-1.0/config/errors.log

# SSH must be run as root, so avoid changing user

# Copy supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose Apache and SSH ports
EXPOSE 80 22

# Start Apache and SSH via Supervisor
CMD ["/usr/bin/supervisord"]

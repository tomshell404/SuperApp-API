# Use the official lightweight PHP Apache production image
FROM php:8.3-apache

# Install and enable the mysqli driver required to talk to your Aiven database
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy your local PHP API files straight into Apache's web folder
COPY . /var/www/html/

# Expose standard web traffic ports internally
EXPOSE 80

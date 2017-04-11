FROM php:7.1-apache

RUN a2enmod alias ssl rewrite headers deflate expires

RUN apt-get update && apt-get -y install vim curl && apt-get clean

# Install php's PDO and PDOMySQL Drivers
RUN docker-php-ext-install pdo pdo_mysql

RUN rm -Rf /app
RUN mkdir -p /app/roadbot /app/certs

CMD ["apache2-foreground"]

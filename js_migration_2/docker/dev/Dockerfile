FROM romeoz/docker-apache-php:5.6

RUN chown -R :www-data /var/www
RUN chmod -R 0777 /var/www

RUN apt update
RUN apt-get -y install software-properties-common
RUN add-apt-repository -y ppa:ondrej/php

RUN apt update
RUN apt -y install php5.6-dev
RUN pecl install stats

RUN echo "extension=stats.so" >> /etc/php/5.6/apache2/php.ini

# RUN pecl install xdebug-2.6.0
# RUN yes | pecl install xdebug-2.5.5
RUN yes | pecl install xdebug-2.2.5
# RUN apt-get install php5.6-xdebug

# RUN echo ""
# RUN docker-php-ext-enable xdebug


FROM php:8.2-cli

RUN apt-get update && \
    apt-get install -y git libicu-dev libzip-dev make nano net-tools wget zip zlib1g-dev

RUN docker-php-ext-install intl zip && docker-php-ext-enable intl zip

# install composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# add custom PHP.ini settings
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY ./custom.ini /usr/local/etc/php/conf.d/custom.ini

RUN rm -rf /var/www/html/*
WORKDIR /var/www/html/

# adds user "sweetrdf", adds him to group "www-data" and sets his home folder
# for more background information see:
# https://medium.com/@mccode/understanding-how-uid-and-gid-work-in-docker-containers-c37a01d01cf
RUN useradd -r --home /home/sweetrdf -u 1000 sweetrdf
RUN usermod -a -G www-data sweetrdf
RUN mkdir /home/sweetrdf
RUN chown sweetrdf:www-data /home/sweetrdf

CMD ["tail -f /dev/null"]

FROM php:8.3-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite headers

WORKDIR /var/www/html
COPY . /var/www/html/
COPY render-entrypoint.sh /usr/local/bin/render-entrypoint

RUN chmod 755 /usr/local/bin/render-entrypoint \
    && mkdir -p /var/www/html/uploads/profile \
        /var/www/html/uploads/payments \
        /var/www/html/uploads/payment_proofs \
        /var/www/html/uploads/commission_settlements \
    && chown -R www-data:www-data /var/www/html/images /var/www/html/uploads

EXPOSE 10000
CMD ["/usr/local/bin/render-entrypoint"]
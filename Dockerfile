FROM php:8.2-apache

# Habilitar mod_rewrite de Apache (útil para URLs amigables y frameworks)
RUN a2enmod rewrite

# Copiar los archivos del proyecto al directorio raíz de Apache
COPY . /var/www/html/

# Ajustar los permisos para el usuario de Apache (www-data)
RUN chown -R www-data:www-data /var/www/html/

# Exponer el puerto 80
EXPOSE 80

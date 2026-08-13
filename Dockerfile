# Fork of azerothcore/playermap (2026-08-13) -- upstream ships no
# Dockerfile at all, this was added here. Plain PHP app, no build step,
# just needs mysqli (confirmed via func.php's DBLayer class -- uses raw
# mysqli_* functions, not PDO).
FROM php:8.1-apache

RUN docker-php-ext-install mysqli

COPY . /var/www/html/
